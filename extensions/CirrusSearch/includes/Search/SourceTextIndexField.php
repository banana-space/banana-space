<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusSearch;
use CirrusSearch\SearchConfig;
use SearchEngine;

/**
 * Index field representing the source_text data.
 * @package CirrusSearch
 */
class SourceTextIndexField extends TextIndexField {
	/** @var bool enable trigram index for accelerated regex query */
	private $withTrigrams;

	public function __construct( $name, $type, SearchConfig $config ) {
		parent::__construct( $name, $type, $config );

		if ( $config->getElement( 'CirrusSearchWikimediaExtraPlugin', 'regex' ) &&
			in_array( 'build', $config->getElement( 'CirrusSearchWikimediaExtraPlugin', 'regex' ) )
		) {
			$this->withTrigrams = true;
		}
	}

	/**
	 * @param SearchEngine $engine
	 * @return array|void
	 */
	public function getMapping( SearchEngine $engine ) {
		if ( !( $engine instanceof CirrusSearch ) ) {
			throw new \LogicException( "Cannot map CirrusSearch fields for another engine." );
		}
		$this->initFlags();

		$field = [
			'index' => false, // We only use the .plain field
			'type' => 'text',
			'fields' => [
				'plain' => [
					'type' => 'text',
					'norms' => false,
					'analyzer' => 'source_text_plain',
					'search_analyzer' => 'source_text_plain_search',
					'position_increment_gap' => self::POSITION_INCREMENT_GAP,
					'similarity' => self::getSimilarity( $this->config, $this->name, 'plain' ),
				],
			]
		];

		if ( $this->withTrigrams ) {
			$field['fields']['trigram'] = [
				'norms' => false,
				'type' => 'text',
				'analyzer' => 'trigram',
				'index_options' => 'docs',
			];
		}
		$this->configureHighlighting( $field, [ 'plain' ], false );
		return $field;
	}
}
