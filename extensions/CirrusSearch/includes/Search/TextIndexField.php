<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Maintenance\MappingConfigBuilder;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\SearchConfig;
use SearchEngine;
use SearchIndexField;

/**
 * Index field representing keyword.
 * Keywords use special analyzer.
 * @package CirrusSearch
 */
class TextIndexField extends CirrusIndexField {
	/**
	 * Distance that lucene places between multiple values of the same field.
	 * Set pretty high to prevent accidental phrase queries between those values.
	 */
	const POSITION_INCREMENT_GAP = 10;

	/* Bit field parameters for string fields.
	 *   ENABLE_NORMS: Enable norms on the field.  Good for text you search against but useless
	 *     for fields that don't get involved in the score.
	 *   COPY_TO_SUGGEST: Copy the contents of this field to the suggest field for "Did you mean".
	 *   SPEED_UP_HIGHLIGHTING: Store extra data in the field to speed up highlighting.  This is important for
	 *     long strings or fields with many values.
	 *   SUPPORT_REGEX: If the wikimedia-extra plugin is available add a trigram
	 *     index to speed up search.
	 */
	const ENABLE_NORMS = 0x1000000;
	// FIXME: when exactly we want to disable norms for text fields?
	const COPY_TO_SUGGEST = 0x2000000;
	const SPEED_UP_HIGHLIGHTING = 0x4000000;
	const SUPPORT_REGEX = 0x8000000;
	const STRING_FIELD_MASK = 0xFFFFFF;

	/**
	 * Extra definitions.
	 * @var array
	 */
	protected $extra;
	/**
	 * Text options for this field
	 * @var int
	 */
	private $textOptions;

	/**
	 * Name of the type in Elastic
	 * @var string
	 */
	protected $typeName = 'text';

	/**
	 * Are trigrams useful?
	 * @var bool
	 */
	protected $allowTrigrams = false;

	public function __construct( $name, $type, SearchConfig $config, $extra = [] ) {
		parent::__construct( $name, $type, $config );

		$this->extra = $extra;

		if ( $config->getElement( 'CirrusSearchWikimediaExtraPlugin', 'regex' ) &&
			in_array( 'build', $config->getElement( 'CirrusSearchWikimediaExtraPlugin', 'regex' ) )
		) {
			$this->allowTrigrams = true;
		}
	}

	/**
	 * Set text options for this field if non-default
	 * @param int $options
	 * @return self
	 */
	public function setTextOptions( $options ) {
		$this->textOptions = $options;
		return $this;
	}

	/**
	 * Get text options for this field
	 * @param int $mappingFlags
	 * @return int
	 */
	protected function getTextOptions( $mappingFlags ) {
		if ( $this->textOptions !== null ) {
			return $this->textOptions;
		}
		$options = self::ENABLE_NORMS | self::SPEED_UP_HIGHLIGHTING;
		if ( $this->config->get( 'CirrusSearchEnablePhraseSuggest' ) &&
			$mappingFlags & MappingConfigBuilder::PHRASE_SUGGEST_USE_TEXT &&
			!$this->checkFlag( SearchIndexField::FLAG_SCORING )
		) {
			// SCORING fields are not copied since this info is already in other fields
			$options |= self::COPY_TO_SUGGEST;
		}
		if ( $this->checkFlag( SearchIndexField::FLAG_NO_HIGHLIGHT ) ) {
			// Disable highlighting is asked to
			$options &= ~self::SPEED_UP_HIGHLIGHTING;
		}
		return $options;
	}

	/**
	 * @param SearchEngine $engine
	 * @return array
	 */
	public function getMapping( SearchEngine $engine ) {
		if ( !( $engine instanceof CirrusSearch ) ) {
			throw new \LogicException( "Cannot map CirrusSearch fields for another engine." );
		}
		$this->initFlags();
		/**
		 * @var CirrusSearch $engine
		 */
		$field = parent::getMapping( $engine );

		if ( $this->config->get( 'CirrusSearchEnablePhraseSuggest' ) &&
			 $this->checkFlag( self::COPY_TO_SUGGEST )
		) {
			$field[ 'copy_to' ] = [ 'suggest' ];
		}

		if ( $this->checkFlag( self::FLAG_NO_INDEX ) ) {
			// no need to configure further a not-indexed field
			return $field;
		}

		$extra = $this->extra;

		if ( $this->mappingFlags & MappingConfigBuilder::PREFIX_START_WITH_ANY ) {
			$extra[] = [
				'analyzer' => 'word_prefix',
				'search_analyzer' => 'plain_search',
				'index_options' => 'docs'
			];
		}
		if ( $this->checkFlag( SearchIndexField::FLAG_CASEFOLD ) ) {
			$extra[] = [
				'analyzer' => 'lowercase_keyword',
				'norms' => false,
				'index_options' => 'docs',
				// TODO: Re-enable in ES 5.2 with keyword type and s/analyzer/normalizer/
				// 'ignore_above' => KeywordIndexField::KEYWORD_IGNORE_ABOVE,
			];
		}

		if ( $this->allowTrigrams && $this->checkFlag( self::SUPPORT_REGEX ) ) {
			$extra[] = [
				'norms' => false,
				'type' => 'text',
				'analyzer' => 'trigram',
				'index_options' => 'docs',
			];
		}

		// multi_field is dead in 1.0 so we do this which actually looks less gnarly.
		$field += [
			'analyzer' => 'text',
			'search_analyzer' => 'text_search',
			'position_increment_gap' => self::POSITION_INCREMENT_GAP,
			'similarity' => self::getSimilarity( $this->config, $this->name ),
			'fields' => [
				'plain' => [
					'type' => 'text',
					'analyzer' => 'plain',
					'search_analyzer' => 'plain_search',
					'position_increment_gap' => self::POSITION_INCREMENT_GAP,
					'similarity' => self::getSimilarity( $this->config, $this->name, 'plain' ),
				],
			]
		];
		$disableNorms = !$this->checkFlag( self::ENABLE_NORMS );
		if ( $disableNorms ) {
			$disableNorms = [ 'norms' => false ];
			$field = array_merge( $field, $disableNorms );
			$field[ 'fields' ][ 'plain' ] = array_merge( $field[ 'fields' ][ 'plain' ], $disableNorms );
		}
		foreach ( $extra as $extraField ) {
			$extraName = $extraField[ 'analyzer' ];

			$field[ 'fields' ][ $extraName ] = array_merge( [
				'similarity' => self::getSimilarity( $this->config, $this->name, $extraName ),
				'type' => 'text',
			], $extraField );
			if ( $disableNorms ) {
				$field[ 'fields' ][ $extraName ] = array_merge(
					$field[ 'fields' ][ $extraName ], $disableNorms );
			}
		}
		$this->configureHighlighting( $field,
			[ 'plain', 'prefix', 'prefix_asciifolding', 'near_match', 'near_match_asciifolding' ] );
		return $field;
	}

	/**
	 * Adapt the field options according to the highlighter used
	 * @param mixed[] &$field the mapping options being built
	 * @param string[] $subFields list of subfields to configure
	 * @param bool $rootField configure the root field (defaults to true)
	 */
	protected function configureHighlighting( array &$field, array $subFields, $rootField = true ) {
		if ( $this->mappingFlags & MappingConfigBuilder::OPTIMIZE_FOR_EXPERIMENTAL_HIGHLIGHTER ) {
			if ( $this->checkFlag( self::SPEED_UP_HIGHLIGHTING ) ) {
				if ( $rootField ) {
					$field[ 'index_options' ] = 'offsets';
				}
				foreach ( $subFields as $fieldName ) {
					if ( isset( $field[ 'fields' ][ $fieldName ] ) ) {
						$field[ 'fields' ][ $fieldName ][ 'index_options' ] = 'offsets';
					}
				}
			}
		} else {
			// We use the FVH on all fields so turn on term vectors
			if ( $rootField ) {
				$field[ 'term_vector' ] = 'with_positions_offsets';
			}
			foreach ( $subFields as $fieldName ) {
				if ( isset( $field[ 'fields' ][ $fieldName ] ) ) {
					$field[ 'fields' ][ $fieldName ][ 'term_vector' ] = 'with_positions_offsets';
				}
			}
		}
	}

	/**
	 * Init the field flags
	 */
	protected function initFlags() {
		$this->flags =
			( $this->flags & self::STRING_FIELD_MASK ) | $this->getTextOptions( $this->mappingFlags );
	}

	/**
	 * Get the field similarity
	 * @param SearchConfig $config
	 * @param string $field
	 * @param string|null $analyzer
	 * @return string
	 */
	public static function getSimilarity( SearchConfig $config, $field, $analyzer = null ) {
		$similarity = $config->getProfileService()->loadProfile( SearchProfileService::SIMILARITY );
		$fieldSimilarity = null;
		if ( isset( $similarity['fields'] ) ) {
			if ( isset( $similarity['fields'][$field] ) ) {
				$fieldSimilarity = $similarity['fields'][$field];
			} elseif ( $similarity['fields']['__default__'] ) {
				$fieldSimilarity = $similarity['fields']['__default__'];
			}

			if ( $analyzer != null && isset( $similarity['fields']["$field.$analyzer"] ) ) {
				$fieldSimilarity = $similarity['fields']["$field.$analyzer"];
			}
		}
		if ( $fieldSimilarity === null ) {
			throw new \RuntimeException( "Invalid similarity profile, unable to infer the similarity for " .
				"the field $field, (defining a __default__ field might solve the issue" );
		}
		return $fieldSimilarity;
	}
}
