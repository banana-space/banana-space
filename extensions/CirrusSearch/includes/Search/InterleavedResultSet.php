<?php

namespace CirrusSearch\Search;

use BaseSearchResultSet;
use Elastica\Response;
use HtmlArmor;
use ISearchResultSet;
use SearchResultSetTrait;
use Title;

class InterleavedResultSet extends BaseSearchResultSet implements CirrusSearchResultSet {
	use SearchResultSetTrait;

	/** @var string[] Doc ID's belonging to team A */
	private $teamA;
	/** @var string[] Doc ID's belonging to team B */
	private $teamB;
	/** @var int Offset to calculate next unused result in team A */
	private $offset;
	/** @var CirrusSearchResultSet */
	private $delegate;
	/** @var CirrusSearchResult[] */
	private $results;

	/**
	 * @param CirrusSearchResultSet $nested Original result set for team A (control)
	 * @param CirrusSearchResult[] $interleaved Interleaved results
	 * @param string[] $teamA Document id's belonging to team A
	 * @param string[] $teamB Document id's belonging to team B
	 * @param int $offset Offset to calculate next unused result in team A
	 */
	public function __construct(
		CirrusSearchResultSet $nested,
		array $interleaved,
		array $teamA,
		array $teamB,
		$offset
	) {
		$this->results = $interleaved;
		$this->teamA = $teamA;
		$this->teamB = $teamB;
		$this->offset = $offset;
		$this->delegate = $nested;
	}

	public function getMetrics() {
		return [
			'wgCirrusSearchTeamDraft' => [
				'a' => $this->teamA,
				'b' => $this->teamB,
			],
		];
	}

	/**
	 * @return int|null
	 */
	public function getOffset() {
		return $this->offset;
	}

	/**
	 * @return CirrusSearchResult[]
	 */
	public function extractResults() {
		return $this->results;
	}

	/**
	 * @return Response|null
	 */
	public function getElasticResponse() {
		return $this->delegate->getElasticResponse();
	}

	/**
	 * @return \Elastica\ResultSet|null
	 */
	public function getElasticaResultSet() {
		return $this->delegate->getElasticaResultSet();
	}

	/**
	 * @param CirrusSearchResultSet $res
	 * @param int $type one of searchresultset::* constants
	 * @param string $interwiki
	 */
	public function addInterwikiResults( CirrusSearchResultSet $res, $type, $interwiki ) {
		$this->delegate->addInterwikiResults( $res, $type, $interwiki );
	}

	/**
	 * @param string $newQuery
	 * @param HtmlArmor|string|null $newQuerySnippet
	 */
	public function setRewrittenQuery( string $newQuery, $newQuerySnippet = null ) {
		$this->delegate->setRewrittenQuery( $newQuery, $newQuerySnippet );
	}

	/**
	 * Count elements of an object
	 * @link https://php.net/manual/en/countable.count.php
	 * @return int The custom count as integer.
	 * </p>
	 * <p>
	 * The return value is cast to an integer.
	 * @since 5.1.0
	 */
	public function count() {
		return count( $this->results );
	}

	/**
	 * @return int
	 */
	public function numRows() {
		return $this->count();
	}

	/**
	 * Some search modes return a total hit count for the query
	 * in the entire article database. This may include pages
	 * in namespaces that would not be matched on the given
	 * settings.
	 *
	 * Return null if no total hits number is supported.
	 *
	 * @return int|null
	 */
	public function getTotalHits() {
		return $this->delegate->getTotalHits();
	}

	/**
	 * Some search modes will run an alternative query that it thinks gives
	 * a better result than the provided search. Returns true if this has
	 * occurred.
	 *
	 * @return bool
	 */
	public function hasRewrittenQuery() {
		return $this->delegate->hasRewrittenQuery();
	}

	/**
	 * @return string|null The search the query was internally rewritten to,
	 *  or null when the result of the original query was returned.
	 */
	public function getQueryAfterRewrite() {
		return $this->delegate->getQueryAfterRewrite();
	}

	/**
	 * @return string|null Same as self::getQueryAfterRewrite(), but in HTML
	 *  and with changes highlighted. Null when the query was not rewritten.
	 */
	public function getQueryAfterRewriteSnippet() {
		return $this->delegate->getQueryAfterRewriteSnippet();
	}

	/**
	 * Some search modes return a suggested alternate term if there are
	 * no exact hits. Returns true if there is one on this set.
	 *
	 * @return bool
	 */
	public function hasSuggestion() {
		return $this->delegate->hasSuggestion();
	}

	/**
	 * @return string|null Suggested query, null if none
	 */
	public function getSuggestionQuery() {
		return $this->delegate->getSuggestionQuery();
	}

	/**
	 * @return string HTML highlighted suggested query, '' if none
	 */
	public function getSuggestionSnippet() {
		return $this->delegate->getSuggestionSnippet();
	}

	/**
	 * Return a result set of hits on other (multiple) wikis associated with this one
	 *
	 * @param int $type
	 * @return ISearchResultSet[]
	 */
	public function getInterwikiResults( $type = self::SECONDARY_RESULTS ) {
		return $this->delegate->getInterwikiResults( $type );
	}

	/**
	 * Check if there are results on other wikis
	 *
	 * @param int $type
	 * @return bool
	 */
	public function hasInterwikiResults( $type = self::SECONDARY_RESULTS ) {
		return $this->delegate->hasInterwikiResults( $type );
	}

	/**
	 * Did the search contain search syntax?  If so, Special:Search won't offer
	 * the user a link to a create a page named by the search string because the
	 * name would contain the search syntax.
	 * @return bool
	 */
	public function searchContainedSyntax() {
		return $this->delegate->searchContainedSyntax();
	}

	/**
	 * @return bool True when there are more pages of search results available.
	 */
	public function hasMoreResults() {
		return $this->delegate->hasMoreResults();
	}

	/**
	 * @param int $limit Shrink result set to $limit and flag
	 *  if more results are available.
	 */
	public function shrink( $limit ) {
		if ( $this->count() > $limit ) {
			$this->results = array_slice( $this->results, 0, $limit );
		}
		$this->delegate->shrink( $limit );
	}

	/**
	 * Extract all the titles in the result set.
	 * @return Title[]
	 */
	public function extractTitles() {
		return $this->delegate->extractTitles();
	}

	/**
	 * @param string $suggestionQuery
	 * @param HtmlArmor|string|null $suggestionSnippet
	 */
	public function setSuggestionQuery( string $suggestionQuery, $suggestionSnippet = null ) {
		$this->delegate->setSuggestionQuery( $suggestionQuery, $suggestionSnippet );
	}
}
