<?php

namespace CirrusSearch\Dispatch;

use Wikimedia\Assert\Assert;

/**
 * Basic SearchQuery routing functionality which produces a constant
 * score when successful, 0.0 otherwise.
 * Inspect the requested namespaces and the classes of the query.
 */
class BasicSearchQueryRoute implements SearchQueryRoute {
	/** @var string */
	private $searchEngineEntryPoint;

	/** @var int[] */
	private $namespaces = [];

	/** @var string[] */
	private $acceptableQueryClasses;

	/** @var string */
	private $profileContext;

	/** @var float */
	private $score;

	/**
	 * @param string $searchEngineEntryPoint
	 * @param int[] $namespaces
	 * @param string[] $acceptableQueryClasses
	 * @param string $profileContext
	 * @param float $score
	 */
	public function __construct(
		$searchEngineEntryPoint,
		array $namespaces,
		array $acceptableQueryClasses,
		$profileContext,
		$score
	) {
		$this->searchEngineEntryPoint = $searchEngineEntryPoint;
		$this->namespaces = $namespaces;
		$this->acceptableQueryClasses = $acceptableQueryClasses;
		$this->profileContext = $profileContext;
		$this->score = $score;
	}

	/**
	 * @param \CirrusSearch\Search\SearchQuery $query
	 * @return float
	 */
	public function score( \CirrusSearch\Search\SearchQuery $query ) {
		Assert::parameter( $query->getSearchEngineEntryPoint() === $this->searchEngineEntryPoint,
			'query',
			"must be {$this->searchEngineEntryPoint} but {$query->getSearchEngineEntryPoint()} given." );

		if ( $this->namespaces !== [] ) {
			$qNs = $query->getNamespaces();
			if ( $qNs === [] ) {
				return 0.0;
			}
			if ( count( array_intersect( $this->namespaces, $qNs ) ) !== count( $qNs ) ) {
				return 0.0;
			}
		}
		if ( $this->acceptableQueryClasses !== [] ) {
			$parsedQuery = $query->getParsedQuery();
			$match = false;
			foreach ( $this->acceptableQueryClasses as $qClass ) {
				if ( $parsedQuery->isQueryOfClass( $qClass ) ) {
					$match = true;
					break;
				}
			}
			if ( !$match ) {
				return 0.0;
			}
		}
		return $this->score;
	}

	/**
	 * The entry point used in the search engine:
	 * - searchText
	 * - nearMatch
	 * - completionSearch
	 *
	 * @return string
	 */
	public function getSearchEngineEntryPoint() {
		return $this->searchEngineEntryPoint;
	}

	/**
	 * The SearchProfile context to use when this route is chosen.
	 *
	 * @return string
	 */
	public function getProfileContext() {
		return $this->profileContext;
	}
}
