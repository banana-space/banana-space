<?php

namespace CirrusSearch\Maintenance;

/**
 * Filter unused and duplicate entries from elasticsearch index configuration
 */
class AnalysisFilter {
	/** @var string[] List of key's in mappings that reference analyzers */
	private static $ANALYZER_FIELDS = [ 'analyzer', 'search_analyzer', 'search_quote_analyzer' ];

	/** @var string[] List of key's in mappings that must be recursively searched */
	private static $SUBFIELD_FIELDS = [ 'fields', 'properties' ];

	/**
	 * Recursively finds used analyzers from elasticsearch mappings
	 *
	 * @param array $properties a 'properties' or 'fields' list from the mappings
	 * @return Set The set of referenced analyzers
	 */
	private function findUsedFromField( array $properties ) {
		$analyzers = new Set();
		foreach ( $properties as $name => $config ) {
			foreach ( self::$ANALYZER_FIELDS as $key ) {
				if ( isset( $config[$key] ) ) {
					$analyzers->add( $config[$key] );
				}
			}
			foreach ( self::$SUBFIELD_FIELDS as $key ) {
				if ( isset( $config[$key] ) ) {
					$analyzers->union( $this->findUsedFromField( $config[$key] ) );
				}
			}
		}
		return $analyzers;
	}

	/**
	 * @param array[] $mappings Elasticsearch mapping configuration
	 * @return Set The set of analyzer names referenced in $mappings
	 */
	public function findUsedAnalyzersInMappings( array $mappings ) {
		$analyzers = new Set();
		foreach ( $mappings as $indexType => $config ) {
			$analyzers->union(
				$this->findUsedFromField( $config['properties'] ) );
		}
		return $analyzers;
	}

	/**
	 * Recursively applies analyzer aliases to elasticsearch mappings
	 *
	 * @param array $properties a 'properties' or 'fields' list from the mappings
	 * @param string[] $aliases Map from current analyzer name to replacement name
	 * @return array $properties with analyzer aliases applied
	 */
	private function pushAnalyzerAliasesIntoField( array $properties, array $aliases ) {
		foreach ( $properties as &$config ) {
			foreach ( self::$ANALYZER_FIELDS as $key ) {
				if ( isset( $config[$key] ) && isset( $aliases[$config[$key]] ) ) {
					$config[$key] = $aliases[$config[$key]];
				}
			}
			foreach ( self::$SUBFIELD_FIELDS as $key ) {
				if ( isset( $config[$key] ) && is_array( $config[$key] ) ) {
					$config[$key] = $this->pushAnalyzerAliasesIntoField(
						$config[$key], $aliases
					);
				}
			}
		}
		return $properties;
	}

	/**
	 * @param array[] $mappings Elasticsearch index mapping configuration
	 * @param string[] $aliases Mapping from old name to new name for analyzers
	 * @return array Updated index mapping configuration
	 */
	public function pushAnalyzerAliasesIntoMappings( array $mappings, $aliases ) {
		foreach ( $mappings as $indexType => $config ) {
			$mappings[$indexType]['properties'] = $this->pushAnalyzerAliasesIntoField(
				$config['properties'], $aliases
			);
		}
		return $mappings;
	}

	private function filter( $data, Set $keysToKeep ) {
		foreach ( $data as $k => $v ) {
			if ( !$keysToKeep->contains( $k ) ) {
				unset( $data[$k] );
			}
		}
		return $data;
	}

	/**
	 * @param array $analysis The index.analysis field of elasticsearch index settings
	 * @param Set $usedAnalyzers Set of analyzers to keep configurations for
	 * @return array The $analysis array filtered to only pieces needed for $usedAnalyzers
	 */
	public function filterUnusedAnalysisChain( $analysis, Set $usedAnalyzers ) {
		$sets = [
			'analyzer' => $usedAnalyzers,
			'filter' => new Set(),
			'char_filter' => new Set(),
			'tokenizer' => new Set(),
		];
		foreach ( $analysis['analyzer'] as $name => $config ) {
			if ( !$usedAnalyzers->contains( $name ) ) {
				continue;
			}
			foreach ( [ 'filter', 'char_filter' ] as $k ) {
				if ( isset( $config[$k] ) ) {
					$sets[$k]->addAll( $config[$k] );
				}
			}
			if ( isset( $config['tokenizer'] ) ) {
				$sets['tokenizer']->add( $config['tokenizer'] );
			}
		}

		foreach ( $sets as $k => $used ) {
			if ( isset( $analysis[$k] ) ) {
				$analysis[$k] = $this->filter( $analysis[$k], $used );
			}
		}

		return $analysis;
	}

	private function recursiveKsort( array $array ) {
		foreach ( $array as $k => $v ) {
			if ( is_array( $v ) ) {
				$array[$k] = $this->recursiveKsort( $v );
			}
		}
		ksort( $array );
		return $array;
	}

	private function calcDeduplicationAliases( array $input ) {
		$keysByContent = [];
		foreach ( $input as $k => $v ) {
			$sorted = $this->recursiveKsort( $v );
			$content = \FormatJson::encode( $sorted );
			$keysByContent[$content][] = $k;
		}
		$aliases = [];
		foreach ( $keysByContent as $keys ) {
			// Sort to give a stable winner for each group
			// 5.6: $winner = min(...$keys);
			asort( $keys );
			$winner = reset( $keys );
			foreach ( $keys as $key ) {
				$aliases[$key] = $winner;
			}
		}
		return $aliases;
	}

	/**
	 * Remove duplicate analysis chain elements and report aliases that need
	 * to be applied to mapping configuration.
	 *
	 * This is necessary for indices such as wikibase that eagerly create
	 * analysis chains for many languages. Quite a few languages result in the
	 * same elements and this deduplication can remove a large fraction of the
	 * configuration.
	 *
	 * @param array $analysis The index.analysis field of elasticsearch index settings
	 * @return string[] map from old analyzer name to new analyzer name.
	 */
	public function deduplicateAnalysisConfig( array $analysis ) {
		// Deduplicate children first to normalize analyzer configuration.
		foreach ( [ 'tokenizer', 'filter', 'char_filter' ] as $k ) {
			if ( !isset( $analysis[$k] ) ) {
				continue;
			}
			$aliases = $this->calcDeduplicationAliases( $analysis[$k] );
			$analysis[$k] = $this->filter( $analysis[$k], new Set( $aliases ) );

			// Push deduplications into analyzers that reference them
			foreach ( $analysis['analyzer'] as $name => $analyzerConfig ) {
				if ( !isset( $analyzerConfig[$k] ) ) {
					continue;
				}
				if ( is_array( $analyzerConfig[$k] ) ) {
					// filter, char_filter
					foreach ( $analyzerConfig[$k] as $i => $value ) {
						// TODO: in theory, all values should be set already?
						if ( isset( $aliases[$value] ) ) {
							$analysis['analyzer'][$name][$k][$i] = $aliases[$value];
						}
					}
				} elseif ( isset( $aliases[$analyzerConfig[$k]] ) ) {
					// tokenizer
					$analysis['analyzer'][$name][$k] = $aliases[$analyzerConfig[$k]];
				}
			}
		}

		// Once the analyzer configuration has been normalized by deduplication
		// we can figure out which of the analyzers are duplicates as well.
		return $this->calcDeduplicationAliases( $analysis['analyzer'] );
	}

	/**
	 * Shrink the size of elasticsearch index configuration
	 *
	 * Removes analysis chain elements that are defined but never referenced
	 * from the mappings. Optionally deduplicates elements of the analysis
	 * chain.
	 *
	 * @param array $analysis Elasticsearch index analysis configuration
	 * @param array $mappings Elasticsearch index mapping configuration
	 * @param bool $deduplicate When true deduplicate the analysis chain
	 * @return array [$settings, $mappings]
	 */
	public function filterAnalysis( array $analysis, array $mappings, $deduplicate = false ) {
		if ( $deduplicate ) {
			$aliases = $this->deduplicateAnalysisConfig( $analysis );
			$mappings = $this->pushAnalyzerAliasesIntoMappings( $mappings, $aliases );
		}
		$usedAnalyzers = $this->findUsedAnalyzersInMappings( $mappings );
		$analysis = $this->filterUnusedAnalysisChain( $analysis, $usedAnalyzers );
		return [ $analysis, $mappings ];
	}
}
