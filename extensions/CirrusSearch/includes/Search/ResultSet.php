<?php

namespace CirrusSearch\Search;

use BaseSearchResultSet;
use HtmlArmor;
use ISearchResultSet;
use LinkBatch;
use SearchResult;
use SearchResultSetTrait;
use Title;
use Wikimedia\Assert\Assert;

/**
 * A set of results from Elasticsearch.
 * Extending this class from another extension is not supported, use BaseCirrusSearchResultSet
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
 * @deprecated use a subclass of BaseCirrusSearchResultSet
 */
class ResultSet extends BaseSearchResultSet implements CirrusSearchResultSet {
	use SearchResultSetTrait;

	/**
	 * @var \Elastica\ResultSet
	 */
	private $result;

	/**
	 * @var string|null
	 */
	private $suggestionQuery;

	/**
	 * @var HtmlArmor|string|null
	 */
	private $suggestionSnippet;

	/**
	 * @var array
	 */
	private $interwikiResults = [];

	/**
	 * @var string|null
	 */
	private $rewrittenQuery;

	/**
	 * @var HtmlArmor|string|null
	 */
	private $rewrittenQuerySnippet;
	/**
	 * @var SearchResult[]
	 */
	protected $results;

	/**
	 * @var bool
	 */
	private $hasMoreResults = false;

	/**
	 * @var bool
	 */
	private $searchContainedSyntax;

	/**
	 * @var FullTextCirrusSearchResultBuilder
	 */
	private $resultBuilder;

	/**
	 * @var TitleHelper
	 */
	private $titleHelper;

	/**
	 * @param bool $searchContainedSyntax
	 * @param \Elastica\ResultSet|null $elasticResultSet
	 * @param TitleHelper|null $titleHelper
	 * @deprecated use a subclass of BaseCirrusSearchResultSet
	 */
	public function __construct(
		$searchContainedSyntax = false,
		\Elastica\ResultSet $elasticResultSet = null,
		TitleHelper $titleHelper = null
	) {
		$this->searchContainedSyntax = $searchContainedSyntax;
		$this->result = $elasticResultSet;
		$this->titleHelper = $titleHelper ?: new TitleHelper();
		$this->resultBuilder = new FullTextCirrusSearchResultBuilder( $this->titleHelper, [] );
	}

	/**
	 * @param string $suggestionQuery
	 * @param HtmlArmor|string|null $suggestionSnippet
	 */
	public function setSuggestionQuery( string $suggestionQuery, $suggestionSnippet = null ) {
		$this->suggestionQuery = $suggestionQuery;
		$this->suggestionSnippet = $suggestionSnippet ?? $suggestionQuery;
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
		$elasticaResultSet = $this->getElasticaResultSet();
		if ( $elasticaResultSet !== null ) {
			return $elasticaResultSet->getTotalHits();
		}
		return 0;
	}

	/**
	 * Loads the result set into the mediawiki LinkCache via a
	 * batch query. By pre-caching this we ensure methods such as
	 * Result::isMissingRevision() don't trigger a query for each and
	 * every search result.
	 *
	 * @param \Elastica\ResultSet $resultSet Result set from which the titles come
	 */
	protected function preCacheContainedTitles( \Elastica\ResultSet $resultSet ) {
		// We can only pull in information about the local wiki
		$lb = new LinkBatch;
		foreach ( $resultSet->getResults() as $result ) {
			if ( !$this->titleHelper->isExternal( $result ) ) {
				$lb->add( $result->namespace, $result->title );
			}
		}
		if ( !$lb->isEmpty() ) {
			$lb->setCaller( __METHOD__ );
			$lb->execute();
		}
	}

	/**
	 * @return bool
	 */
	public function hasSuggestion() {
		return $this->suggestionQuery !== null;
	}

	/**
	 * @return string|null
	 */
	public function getSuggestionQuery() {
		return $this->suggestionQuery;
	}

	/**
	 * @return HtmlArmor|string|null Null is only returned if suggestion query is also null
	 */
	public function getSuggestionSnippet() {
		return $this->suggestionSnippet;
	}

	public function extractResults() {
		if ( $this->results === null ) {
			$this->results = [];
			if ( $this->result !== null ) {
				$this->preCacheContainedTitles( $this->result );
				foreach ( $this->result->getResults() as $result ) {
					$transformed = $this->transformOneResult( $result );
					$this->augmentResult( $transformed );
					$this->results[] = $transformed;
				}
			}
		}
		return $this->results;
	}

	/**
	 * @param \Elastica\Result $result Result from search engine
	 * @return CirrusSearchResult Elasticsearch result transformed into mediawiki
	 *  search result object.
	 */
	protected function transformOneResult( \Elastica\Result $result ) {
		return $this->resultBuilder->build( $result );
	}

	/**
	 * @param CirrusSearchResultSet $res
	 * @param int $type One of SearchResultSet::* constants
	 * @param string $interwiki
	 */
	public function addInterwikiResults( CirrusSearchResultSet $res, $type, $interwiki ) {
		$this->interwikiResults[$type][$interwiki] = $res;
	}

	/**
	 * @param int $type
	 * @return ISearchResultSet[]
	 */
	public function getInterwikiResults( $type = self::SECONDARY_RESULTS ) {
		return $this->interwikiResults[$type] ?? [];
	}

	/**
	 * @param int $type
	 * @return bool
	 */
	public function hasInterwikiResults( $type = self::SECONDARY_RESULTS ) {
		return isset( $this->interwikiResults[$type] );
	}

	/**
	 * @param string $newQuery
	 * @param HtmlArmor|string|null $newQuerySnippet
	 */
	public function setRewrittenQuery( string $newQuery, $newQuerySnippet = null ) {
		$this->rewrittenQuery = $newQuery;
		$this->rewrittenQuerySnippet = $newQuerySnippet ?? $newQuery;
	}

	/**
	 * @return bool
	 */
	public function hasRewrittenQuery() {
		return $this->rewrittenQuery !== null;
	}

	/**
	 * @return string|null
	 */
	public function getQueryAfterRewrite() {
		return $this->rewrittenQuery;
	}

	/**
	 * @return HtmlArmor|string|null
	 */
	public function getQueryAfterRewriteSnippet() {
		return $this->rewrittenQuerySnippet;
	}

	/**
	 * @return \Elastica\Response|null
	 */
	public function getElasticResponse() {
		return $this->result != null ? $this->result->getResponse() : null;
	}

	/**
	 * @return \Elastica\ResultSet|null
	 */
	public function getElasticaResultSet() {
		return $this->result;
	}

	/**
	 * Count elements of an object
	 * @link https://php.net/manual/en/countable.count.php
	 * @return int The custom count as an integer.
	 * </p>
	 * <p>
	 * The return value is cast to an integer.
	 * @since 5.1.0
	 */
	public function count() {
		return count( $this->extractResults() );
	}

	/**
	 * @return int
	 */
	public function numRows() {
		return $this->count();
	}

	/**
	 * Did the search contain search syntax?  If so, Special:Search won't offer
	 * the user a link to a create a page named by the search string because the
	 * name would contain the search syntax.
	 * @return bool
	 */
	public function searchContainedSyntax() {
		return $this->searchContainedSyntax;
	}

	/**
	 * @return bool True when there are more pages of search results available.
	 */
	public function hasMoreResults() {
		return $this->hasMoreResults;
	}

	/**
	 * @param int $limit Shrink result set to $limit and flag
	 *  if more results are available.
	 */
	public function shrink( $limit ) {
		if ( $this->count() > $limit ) {
			Assert::precondition( $this->results !== null, "results not initialized" );
			$this->results = array_slice( $this->results, 0, $limit );
			$this->hasMoreResults = true;
		}
	}

	/**
	 * Extract all the titles in the result set.
	 * @return Title[]
	 */
	public function extractTitles() {
		return array_map(
			function ( SearchResult $result ) {
				return $result->getTitle();
			},
			$this->extractResults() );
	}
}
