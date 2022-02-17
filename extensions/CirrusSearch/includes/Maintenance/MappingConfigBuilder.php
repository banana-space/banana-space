<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Connection;
use CirrusSearch\Search\CirrusIndexField;
use CirrusSearch\Search\CirrusSearchIndexFieldFactory;
use CirrusSearch\Search\SourceTextIndexField;
use CirrusSearch\Search\TextIndexField;
use CirrusSearch\SearchConfig;
use Hooks;
use SearchIndexField;
use Wikimedia\Assert\Assert;

/**
 * Builds elasticsearch mapping configuration arrays.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class MappingConfigBuilder {
	// Bit field parameters for buildConfig
	const PREFIX_START_WITH_ANY = 1;
	const PHRASE_SUGGEST_USE_TEXT = 2;
	const OPTIMIZE_FOR_EXPERIMENTAL_HIGHLIGHTER = 4;

	/**
	 * Version number for the core analysis. Increment the major
	 * version when the analysis changes in an incompatible way,
	 * and change the minor version when it changes but isn't
	 * incompatible
	 */
	const VERSION = '1.10';

	/**
	 * @var bool should the index be optimized for the experimental highlighter?
	 */
	private $optimizeForExperimentalHighlighter;

	/**
	 * @var SearchConfig
	 */
	private $config;

	/**
	 * @var CirrusSearch
	 */
	protected $engine;

	/**
	 * @var CirrusSearchIndexFieldFactory
	 */
	protected $searchIndexFieldFactory;

	/**
	 * @var int
	 */
	protected $flags = 0;

	/**
	 * @param bool $optimizeForExperimentalHighlighter should the index be optimized for the experimental highlighter?
	 * @param int $flags
	 * @param SearchConfig|null $config
	 * @throws \ConfigException
	 */
	public function __construct( $optimizeForExperimentalHighlighter, $flags = 0, SearchConfig $config = null ) {
		$this->optimizeForExperimentalHighlighter = $optimizeForExperimentalHighlighter;
		if ( $this->optimizeForExperimentalHighlighter ) {
			$flags |= self::OPTIMIZE_FOR_EXPERIMENTAL_HIGHLIGHTER;
		}
		$this->flags = $flags;
		$this->engine = new CirrusSearch( $config );
		$this->config = $this->engine->getConfig();
		$this->searchIndexFieldFactory = new CirrusSearchIndexFieldFactory( $this->config );
	}

	/**
	 * Get definitions for default index fields.
	 * These fields are always present in the index.
	 * @return array
	 */
	private function getDefaultFields() {
		// Note never to set something as type='object' here because that isn't returned by elasticsearch
		// and is inferred anyway.
		$titleExtraAnalyzers = [
			[ 'analyzer' => 'prefix', 'search_analyzer' => 'near_match', 'index_options' => 'docs', 'norms' => false ],
			[
				'analyzer' => 'prefix_asciifolding',
				'search_analyzer' => 'near_match_asciifolding',
				'index_options' => 'docs',
				'norms' => false
			],
			[ 'analyzer' => 'near_match', 'index_options' => 'docs', 'norms' => false ],
			[ 'analyzer' => 'near_match_asciifolding', 'index_options' => 'docs', 'norms' => false ],
			[ 'analyzer' => 'keyword', 'index_options' => 'docs', 'norms' => false ],
		];
		if ( $this->flags & self::PREFIX_START_WITH_ANY ) {
			$titleExtraAnalyzers[] = [
				'analyzer' => 'word_prefix',
				'search_analyzer' => 'plain_search',
				'index_options' => 'docs'
			];
		}

		$suggestField = [
			'type' => 'text',
			'similarity' => TextIndexField::getSimilarity( $this->config, 'suggest' ),
			'index_options' => 'freqs',
			'analyzer' => 'suggest',
		];

		if ( $this->config->getElement( 'CirrusSearchPhraseSuggestReverseField', 'build' ) ) {
			$suggestField['fields'] = [
				'reverse' => [
					'type' => 'text',
					'similarity' => TextIndexField::getSimilarity( $this->config, 'suggest', 'reverse' ),
					'index_options' => 'freqs',
					'analyzer' => 'suggest_reverse',
				],
			];
		}

		$page = [
			'dynamic' => false,
			'properties' => [
				'timestamp' => [
					'type' => 'date',
					'format' => 'dateOptionalTime',
				],
				'create_timestamp' => [
					'type' => 'date',
					'format' => 'dateOptionalTime',
				],
				'wiki' => $this->searchIndexFieldFactory
					->newKeywordField( 'wiki' )
					->getMapping( $this->engine ),
				'namespace' => $this->searchIndexFieldFactory
					->newLongField( 'namespace' )
					->getMapping( $this->engine ),
				'namespace_text' => $this->searchIndexFieldFactory
					->newKeywordField( 'namespace_text' )
					->getMapping( $this->engine ),
				'title' => $this->searchIndexFieldFactory->newStringField( 'title',
					TextIndexField::ENABLE_NORMS | TextIndexField::COPY_TO_SUGGEST |
					TextIndexField::SUPPORT_REGEX,
					$titleExtraAnalyzers )->setMappingFlags( $this->flags )->getMapping( $this->engine ),
				'text' => $this->getTextFieldMapping(),
				'text_bytes' => $this->searchIndexFieldFactory
					->newLongField( 'text_bytes' )
					->setFlag( SearchIndexField::FLAG_NO_INDEX )
					->getMapping( $this->engine ),
				'source_text' => $this->buildSourceTextStringField( 'source_text' )
					->setMappingFlags( $this->flags )->getMapping( $this->engine ),
				'redirect' => [
					'dynamic' => false,
					'properties' => [
						'namespace' => $this->searchIndexFieldFactory
							->newLongField( 'namespace' )
							->getMapping( $this->engine ),
						'title' => $this->searchIndexFieldFactory
							->newStringField( 'redirect.title', TextIndexField::ENABLE_NORMS
								| TextIndexField::SPEED_UP_HIGHLIGHTING
								| TextIndexField::COPY_TO_SUGGEST
								| TextIndexField::SUPPORT_REGEX,
								$titleExtraAnalyzers
							)
							->setMappingFlags( $this->flags )
							->getMapping( $this->engine ),
					]
				],
				'incoming_links' => $this->searchIndexFieldFactory
					->newLongField( 'incoming_links' )
					->getMapping( $this->engine ),
				'local_sites_with_dupe' => $this->searchIndexFieldFactory
					->newKeywordField( 'local_sites_with_dupe' )
					->setFlag( SearchIndexField::FLAG_CASEFOLD )
					->getMapping( $this->engine ),
				'suggest' => $suggestField,
			]
		];

		return $page;
	}

	/**
	 * Build the mapping config.
	 * @return array the mapping config
	 */
	public function buildConfig() {
		global $wgCirrusSearchAllFields, $wgCirrusSearchWeights;

		$page = $this->getDefaultFields();

		$fields = $this->engine->getSearchIndexFields();

		foreach ( $fields as $fieldName => $field ) {
			if ( $field instanceof CirrusIndexField ) {
				$field->setMappingFlags( $this->flags );
			}
			$config = $field->getMapping( $this->engine );
			if ( $config ) {
				$page['properties'][$fieldName] = $config;
			}
		}

		if ( $wgCirrusSearchAllFields[ 'build' ] ) {
			// Now layer all the fields into the all field once per weight.  Querying it isn't strictly the
			// same as querying each field - in some ways it is better!  In others it is worse....

			// Better because theoretically tf/idf based scoring works better this way.
			// Worse because we have to analyze each field multiple times....  Bleh!
			// This field can't be used for the fvh/experimental highlighter for several reasons:
			// 1. It is built with copy_to and not stored.
			// 2. The term frequency information is all whoppy compared to the "real" source text.
			$allField = $this->searchIndexFieldFactory->
				newStringField( 'all', TextIndexField::ENABLE_NORMS );
			$page['properties']['all'] =
				$allField->setMappingFlags( $this->flags )->getMapping( $this->engine );
			$page = $this->setupCopyTo( $page, $wgCirrusSearchWeights, 'all' );

			// Now repeat for near_match fields.  The same considerations above apply except near_match
			// is never used in phrase queries or highlighting.
			$page[ 'properties' ][ 'all_near_match' ] = [
				'type' => 'text',
				'analyzer' => 'near_match',
				'index_options' => 'freqs',
				'norms' => false,
				'similarity' => TextIndexField::getSimilarity( $this->config, 'all_near_match' ),
				'fields' => [
					'asciifolding' => [
						'type' => 'text',
						'analyzer' => 'near_match_asciifolding',
						'index_options' => 'freqs',
						'norms' => false,
						'similarity' => TextIndexField::getSimilarity( $this->config, 'all_near_match', 'asciifolding' ),
					],
				],
			];
			$nearMatchFields = [
				'title' => $wgCirrusSearchWeights[ 'title' ],
				'redirect' => $wgCirrusSearchWeights[ 'redirect' ],
			];
			$page = $this->setupCopyTo( $page, $nearMatchFields, 'all_near_match' );
		}

		$mappingConfig = [ $this->getMainType() => $page ];

		if ( $this->getMainType() === Connection::PAGE_TYPE_NAME ) {
			// For now only trigger the hook on the "page" indices.
			// It's probably that implementors don't pay attention to the new getMainType()
			// method.
			Hooks::run( 'CirrusSearchMappingConfig', [ &$mappingConfig, $this ] );
			Assert::postcondition( count( $mappingConfig ) === 1,
				'CirrusSearchMappingConfig implementations must not add a new mapping type' );
		}
		return $mappingConfig;
	}

	/**
	 * Setup copy_to for some fields to $destination.
	 * @param array $config to modify
	 * @param array $fields field name to number of times copied
	 * @param string $destination destination of the copy
	 * @return array $config modified with the copy_to setup
	 */
	private function setupCopyTo( $config, $fields, $destination ) {
		foreach ( $fields as $field => $weight ) {
			// Note that weights this causes weights that are not whole numbers to be rounded up.
			// We're ok with that because we don't have a choice.
			for ( $r = 0; $r < $weight; $r++ ) {
				if ( $field === 'redirect' ) {
					// Redirect is in a funky place
					$config[ 'properties' ][ 'redirect' ][ 'properties' ][ 'title' ][ 'copy_to' ][] = $destination;
				} else {
					$config[ 'properties' ][ $field ][ 'copy_to' ][] = $destination;
				}
			}
		}

		return $config;
	}

	/**
	 * Build the source_text index field
	 *
	 * @param string $fieldName usually "source_text"
	 * @return SourceTextIndexField
	 */
	protected function buildSourceTextStringField( $fieldName ) {
		return new SourceTextIndexField( $fieldName, SearchIndexField::INDEX_TYPE_TEXT, $this->config );
	}

	/**
	 * @return array
	 */
	private function getTextFieldMapping() {
		$stringFieldMapping = $this->searchIndexFieldFactory->newStringField(
			'text',
			null,
			[]
		)->setMappingFlags( $this->flags )->getMapping( $this->engine );

		$extraFieldMapping = [
			'fields' => [
				'word_count' => [
					'type' => 'token_count',
					'store' => true,
					'analyzer' => 'plain',
				]
			]
		];

		$textFieldMapping = array_merge_recursive( $stringFieldMapping, $extraFieldMapping );

		return $textFieldMapping;
	}

	/**
	 * The elastic type name used by this index
	 * @return string
	 */
	public function getMainType() {
		return Connection::PAGE_TYPE_NAME;
	}

	/**
	 * Whether or not it's safe to optimize the analysis config.
	 * It's generally safe to optimize if all the analyzers needed are
	 * properly referenced in the mapping.
	 * In the case an analyzer is used directly in a query but not referenced
	 * in the mapping it's not safe to optimize.
	 *
	 * @return bool
	 */
	public function canOptimizeAnalysisConfig() {
		return false;
	}
}
