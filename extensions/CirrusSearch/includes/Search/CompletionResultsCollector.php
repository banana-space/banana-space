<?php

namespace CirrusSearch\Search;

use CirrusSearch\CompletionRequestLog;
use SearchSuggestion;
use SearchSuggestionSet;

/**
 * Collect results from multiple result sets
 */
class CompletionResultsCollector {
	/**
	 * @var SearchSuggestion[] suggestions indexed by pageId (mutable)
	 */
	private $suggestionsByDocId = [];

	/**
	 * @var string[][] profile names indexed by pageId (mutable)
	 */
	private $suggestionMetadataByDocId = [];

	/**
	 * @var float|null maintains the minScore (mutable)
	 */
	private $minScore = null;

	/**
	 * @var int|null maintains the doc that has minScore (mutable)
	 */
	private $minDoc = null;

	/**
	 * @var int how many results we want to keep (final)
	 */
	private $limit;

	/**
	 * @var int the offset (final)
	 */
	private $offset;

	/**
	 * @param int $limit number of results we want to display
	 * @param int $offset
	 */
	public function __construct( $limit, $offset = 0 ) {
		if ( $limit <= 0 ) {
			throw new \RuntimeException( "limit must be strictly positive" );
		}
		$this->limit = $limit;
		$this->offset = $offset;
	}

	/**
	 * @param int $pageId
	 * @param float $score
	 * @return bool
	 * @internal param int $docId
	 */
	private function canCollect( $pageId, $score ) {
		// First element
		if ( $this->minScore === null && $this->limit > 0 ) {
			return true;
		}

		// If we have the doc we do not accept it if it has lower score
		if ( isset( $this->suggestionsByDocId[$pageId] ) &&
				$score <= $this->suggestionsByDocId[$pageId]->getScore() ) {
			return false;
		}

		// We always accept docs that are better
		if ( $score > $this->minScore ) {
			return true;
		}

		// For everything else we accept until we are full
		return !$this->isFull();
	}

	/**
	 * Collect a doc if possible.
	 * The doc will be collected if the capacity is not yet reached or if its score
	 * is better than a suggestion already collected.
	 * @param SearchSuggestion $suggestion
	 * @param string $profileName
	 * @param string $index
	 * @return bool true if the doc was added false otherwise
	 */
	public function collect( SearchSuggestion $suggestion, $profileName, $index ) {
		if ( !$this->canCollect( $suggestion->getSuggestedTitleID(), $suggestion->getScore() ) ) {
			return false;
		}

		if ( isset( $this->suggestionsByDocId[$suggestion->getSuggestedTitleID()] ) ) {
			$oldSugg = $this->suggestionsByDocId[$suggestion->getSuggestedTitleID()];
			if ( $oldSugg->getScore() > $suggestion->getScore() ) {
				return false;
			}
			unset( $this->suggestionsByDocId[$suggestion->getSuggestedTitleID()] );
			unset( $this->suggestionMetadataByDocId[$suggestion->getSuggestedTitleID()] );
			// worst case 1: existing doc with better score
			$this->updateMinDoc();
		}

		if ( $this->isFull() ) {
			unset( $this->suggestionsByDocId[$this->minDoc] );
			unset( $this->suggestionMetadataByDocId[$this->minDoc] );
			// worst case 2: collector full but better score found
			$this->updateMinDoc();
		}
		if ( $this->minScore === null || $this->minScore > $suggestion->getScore() ) {
			$this->minScore = $suggestion->getScore();
			$this->minDoc = $suggestion->getSuggestedTitleID();
		}
		$this->suggestionsByDocId[$suggestion->getSuggestedTitleID()] = $suggestion;
		$this->suggestionMetadataByDocId[$suggestion->getSuggestedTitleID()] = [
			'profile' => $profileName,
			'index' => $index
		];
		return true;
	}

	/**
	 * Test whether the collector is full
	 * @return bool true if it's full
	 */
	public function isFull() {
		return !( $this->size() < ( $this->limit + $this->offset ) );
	}

	/**
	 * Number of suggestions collected
	 * @return int
	 */
	public function size() {
		return count( $this->suggestionsByDocId );
	}

	/**
	 * Find the min doc.
	 * This is called on worst case scenario:
	 * - when the collector is full but a better doc is found
	 * - when an already collected doc is found with a better score
	 *
	 * Realistically this should not happen too frequently since
	 * docs are usually fetched from elastic which returns them
	 * in order. If it appears to cause perf issues we might
	 * want to investigate an approach based on SplMinHeap.
	 */
	private function updateMinDoc() {
		$minScore = null;
		$minDoc = null;
		foreach ( $this->suggestionsByDocId as $sugg ) {
			if ( $minScore === null || $minScore > $sugg->getScore() ) {
				$minScore = $sugg->getScore();
				$minDoc = $sugg->getSuggestedTitleID();
			}
		}
		$this->minDoc = $minDoc;
		$this->minScore = $minScore;
	}

	/**
	 * Return the set of suggestions collected so far and log
	 * its states to CompletionRequestLog.
	 *
	 * @param CompletionRequestLog $log
	 * @return SearchSuggestionSet
	 */
	public function logAndGetSet( CompletionRequestLog $log ) {
		uasort( $this->suggestionsByDocId, function ( SearchSuggestion $a, SearchSuggestion $b ) {
			if ( $b->getScore() > $a->getScore() ) {
				return 1;
			} elseif ( $b->getScore() < $a->getScore() ) {
				return -1;
			}
			return 0;
		} );
		$results = array_slice( $this->suggestionsByDocId, $this->offset,
			$this->limit, true );
		$log->setResult( $results, $this->suggestionMetadataByDocId );
		return new SearchSuggestionSet( $results );
	}

	/**
	 * @return float|null
	 */
	public function getMinScore() {
		return $this->minScore;
	}
}
