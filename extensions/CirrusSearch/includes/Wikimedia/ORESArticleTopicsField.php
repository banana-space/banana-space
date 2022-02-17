<?php

namespace CirrusSearch\Wikimedia;

use SearchEngine;
use SearchIndexFieldDefinition;

/**
 * Field definitions for the (Wikimedia-specific) articletopic search feature.
 * @package CirrusSearch\Wikimedia
 * @see ORESArticleTopicsHooks
 */
class ORESArticleTopicsField extends SearchIndexFieldDefinition {
	/**
	 * @var string
	 */
	private $indexAnalyzer;
	/**
	 * @var string
	 */
	private $searchAnalyzer;
	/**
	 * @var string
	 */
	private $similarity;

	/**
	 * @param string $name name of the field
	 * @param string $type type of the field
	 * @param string $indexAnalyzer index analyzer
	 * @param string $searchAnalyzer search analyzer
	 * @param string $similarity similiraty name to use
	 */
	public function __construct(
		$name,
		$type,
		string $indexAnalyzer,
		string $searchAnalyzer,
		string $similarity
	) {
		parent::__construct( $name, $type );
		$this->indexAnalyzer = $indexAnalyzer;
		$this->searchAnalyzer = $searchAnalyzer;
		$this->similarity = $similarity;
	}

	/**
	 * @param SearchEngine $engine the search engine requesting this mapping
	 * @return array the elasticsearch mapping for this field
	 */
	public function getMapping( SearchEngine $engine ) {
		return [
			'type' => 'text',
			'analyzer' => $this->indexAnalyzer,
			'search_analyzer' => $this->searchAnalyzer,
			'index_options' => 'freqs',
			'norms' => false,
			'similarity' => $this->similarity,
		];
	}
}
