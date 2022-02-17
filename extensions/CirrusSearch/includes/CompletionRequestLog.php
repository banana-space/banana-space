<?php

namespace CirrusSearch;

use SearchSuggestion;

class CompletionRequestLog extends BaseRequestLog {

	/**
	 * @var array
	 */
	private $hits = [];

	/**
	 * @var float|null
	 */
	private $maxScore = null;

	/**
	 * @var string[]
	 */
	private $indices = [];

	/**
	 * @var int
	 */
	private $suggestTookMs = 0;

	/**
	 * @var int
	 */
	private $prefixTookMs = 0;

	/**
	 * @var int
	 */
	private $hitsTotal;

	/**
	 * @var int[]|null
	 */
	private $namespaces;

	public function __construct( $description, $queryType, $extra = [], $namespaces = null ) {
		parent::__construct( $description, $queryType, $extra );
		$this->namespaces = $namespaces;
	}

	/**
	 * @param SearchSuggestion[] $result The set of suggestion results that
	 *  will be returned to the user.
	 * @param string[][] $suggestionMetadataByDocId A map from elasticsearch
	 *  document id to the completion profile that provided the highest score
	 *  for that document id.
	 */
	public function setResult( array $result, array $suggestionMetadataByDocId ) {
		$maxScore = $this->maxScore;
		foreach ( $result as $docId => $suggestion ) {
			$index = $suggestionMetadataByDocId[$docId]['index'] ?? '';
			$title = $suggestion->getSuggestedTitle();
			$pageId = $suggestion->getSuggestedTitleID() ?: -1;
			$maxScore = $maxScore !== null ? max( $maxScore, $suggestion->getScore() ) : $suggestion->getScore();
			$this->hits[] = [
				'title' => $title ? $title->getPrefixedText() : $suggestion->getText(),
				'index' => $index,
				'pageId' => (int)$pageId,
				'score' => $suggestion->getScore(),
				'profileName' => $suggestionMetadataByDocId[$docId]['profile'] ?? '',
			];
		}
		$this->maxScore = $maxScore !== null ? (float)$maxScore : null;
	}

	/**
	 * @return int
	 */
	public function getElasticTookMs() {
		return $this->suggestTookMs;
	}

	/**
	 * @return bool
	 */
	public function isCachedResponse() {
		return false;
	}

	/**
	 * @return array
	 */
	public function getLogVariables() {
		// Note this intentionally extracts data from $this->extra, rather than
		// using it directly. The use case is small enough for this class we can
		// be more explicit about returned variables.
		return [
			'query' => $this->extra['query'] ?? '',
			'queryType' => $this->getQueryType(),
			'index' => implode( ',', $this->indices ),
			'elasticTookMs' => $this->getElasticTookMs(),
			'hitsTotal' => $this->hitsTotal,
			'maxScore' => $this->maxScore ?? 0.0,
			'hitsReturned' => count( $this->hits ),
			'hitsOffset' => $this->extra['offset'] ?? 0,
			'tookMs' => $this->getTookMs(),
		];
	}

	/**
	 * @return array[]
	 */
	public function getRequests() {
		$vars = $this->getLogVariables() + [
			'hits' => $this->hits,
			'namespaces' => $this->namespaces,
		];
		return [ $vars ];
	}

	/**
	 * @param int $totalHits
	 */
	public function setTotalHits( $totalHits ) {
		$this->hitsTotal = $totalHits;
	}

	/**
	 * @param int $suggestTookMs
	 */
	public function setSuggestTookMs( $suggestTookMs ) {
		$this->suggestTookMs = $suggestTookMs;
	}

	/**
	 * @param int $prefixTookMs
	 */
	public function setPrefixTookMs( $prefixTookMs ) {
		$this->prefixTookMs = $prefixTookMs;
	}

	/**
	 * Add an index used by this request
	 * @param string $indexName
	 */
	public function addIndex( $indexName ) {
		$this->indices[$indexName] = $indexName;
	}
}
