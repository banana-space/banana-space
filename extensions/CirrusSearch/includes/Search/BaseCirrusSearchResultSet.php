<?php

namespace CirrusSearch\Search;

use BaseSearchResultSet;
use Exception;
use HtmlArmor;
use LinkBatch;
use SearchResult;
use SearchResultSetTrait;
use Title;
use Wikimedia\Assert\Assert;

/**
 * Base class to represent a CirrusSearchResultSet
 * Extensions willing to feed Cirrus with a CirrusSearchResultSet must extend this class.
 */
abstract class BaseCirrusSearchResultSet extends BaseSearchResultSet implements CirrusSearchResultSet {
	use SearchResultSetTrait;

	/** @var bool */
	private $hasMoreResults = false;

	/**
	 * @var CirrusSearchResult[]|null
	 */
	private $results;

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
	 * @var TitleHelper
	 */
	private $titleHelper;

	/**
	 * @param \Elastica\Result $result Result from search engine
	 * @return CirrusSearchResult Elasticsearch result transformed into mediawiki
	 *  search result object.
	 */
	abstract protected function transformOneResult( \Elastica\Result $result );

	/**
	 * @return bool True when there are more pages of search results available.
	 */
	final public function hasMoreResults() {
		return $this->hasMoreResults;
	}

	/**
	 * @param string $suggestionQuery
	 * @param HtmlArmor|string|null $suggestionSnippet
	 */
	final public function setSuggestionQuery( string $suggestionQuery, $suggestionSnippet = null ) {
		$this->suggestionQuery = $suggestionQuery;
		$this->suggestionSnippet = $suggestionSnippet ?? $suggestionQuery;
	}

	/**
	 * Loads the result set into the mediawiki LinkCache via a
	 * batch query. By pre-caching this we ensure methods such as
	 * Result::isMissingRevision() don't trigger a query for each and
	 * every search result.
	 *
	 * @param \Elastica\ResultSet $resultSet Result set from which the titles come
	 */
	final private function preCacheContainedTitles( \Elastica\ResultSet $resultSet ) {
		// We can only pull in information about the local wiki
		$lb = new LinkBatch;
		foreach ( $resultSet->getResults() as $result ) {
			if ( !$this->getTitleHelper()->isExternal( $result )
				&& isset( $result->namespace )
				&& isset( $result->title )
			) {
				$lb->add( $result->namespace, $result->title );
			}
		}
		if ( !$lb->isEmpty() ) {
			$lb->setCaller( __METHOD__ );
			$lb->execute();
		}
	}

	/**
	 * @param bool $searchContainedSyntax
	 * @return CirrusSearchResultSet an empty result set
	 */
	final public static function emptyResultSet( $searchContainedSyntax = false ) {
		return new class( $searchContainedSyntax ) extends BaseCirrusSearchResultSet {
			/** @var bool */
			private $searchContainedSyntax;

			/**
			 * @param bool $searchContainedSyntax
			 */
			public function __construct( $searchContainedSyntax ) {
				$this->searchContainedSyntax = $searchContainedSyntax;
			}

			/**
			 * @inheritDoc
			 */
			protected function transformOneResult( \Elastica\Result $result ) {
				throw new Exception( "An empty ResultSet has nothing to transform" );
			}

			/**
			 * @inheritDoc
			 */
			public function getElasticaResultSet() {
				return null;
			}

			/**
			 * @inheritDoc
			 */
			public function searchContainedSyntax() {
				return $this->searchContainedSyntax;
			}
		};
	}

	/**
	 * @param int $limit Shrink result set to $limit and flag
	 *  if more results are available.
	 */
	final public function shrink( $limit ) {
		if ( $this->count() > $limit ) {
			Assert::precondition( $this->results !== null, "results not initialized" );
			$this->results = array_slice( $this->results, 0, $limit );
			$this->hasMoreResults = true;
		}
	}

	/**
	 * @return CirrusSearchResult[]|SearchResult[]
	 */
	final public function extractResults() {
		if ( $this->results === null ) {
			$this->results = [];
			$elasticaResults = $this->getElasticaResultSet();
			if ( $elasticaResults !== null ) {
				$this->preCacheContainedTitles( $elasticaResults );
				foreach ( $elasticaResults->getResults() as $result ) {
					$transformed = $this->transformOneResult( $result );
					if ( $transformed !== null ) {
						$this->augmentResult( $transformed );
						$this->results[] = $transformed;
					}
				}
			}
		}
		return $this->results;
	}

	/**
	 * Extract all the titles in the result set.
	 * @return Title[]
	 */
	final public function extractTitles() {
		return array_map(
			function ( SearchResult $result ) {
				return $result->getTitle();
			},
			$this->extractResults() );
	}

	/**
	 * @param CirrusSearchResultSet $res
	 * @param int $type one of searchresultset::* constants
	 * @param string $interwiki
	 */
	final public function addInterwikiResults( CirrusSearchResultSet $res, $type, $interwiki ) {
		$this->interwikiResults[$type][$interwiki] = $res;
	}

	/**
	 * @param int $type
	 * @return \ISearchResultSet[]
	 */
	final public function getInterwikiResults( $type = self::SECONDARY_RESULTS ) {
		return $this->interwikiResults[$type] ?? [];
	}

	/**
	 * @param int $type
	 * @return bool
	 */
	final public function hasInterwikiResults( $type = self::SECONDARY_RESULTS ) {
		return isset( $this->interwikiResults[$type] );
	}

	/**
	 * @param string $newQuery
	 * @param HtmlArmor|string|null $newQuerySnippet
	 */
	final public function setRewrittenQuery( string $newQuery, $newQuerySnippet = null ) {
		$this->rewrittenQuery = $newQuery;
		$this->rewrittenQuerySnippet = $newQuerySnippet ?? $newQuery;
	}

	/**
	 * @return bool
	 */
	final public function hasRewrittenQuery() {
		return $this->rewrittenQuery !== null;
	}

	/**
	 * @return string|null
	 */
	final public function getQueryAfterRewrite() {
		return $this->rewrittenQuery;
	}

	/**
	 * @return HtmlArmor|string|null
	 */
	final public function getQueryAfterRewriteSnippet() {
		return $this->rewrittenQuerySnippet;
	}

	/**
	 * @return bool
	 */
	final public function hasSuggestion() {
		return $this->suggestionQuery !== null;
	}

	/**
	 * @return string|null
	 */
	final public function getSuggestionQuery() {
		return $this->suggestionQuery;
	}

	/**
	 * @return string|null
	 */
	final public function getSuggestionSnippet() {
		return $this->suggestionSnippet;
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
	final public function count() {
		return count( $this->extractResults() );
	}

	/**
	 * @return int
	 */
	final public function numRows() {
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
	final public function getTotalHits() {
		$elasticaResultSet = $this->getElasticaResultSet();
		if ( $elasticaResultSet !== null ) {
			return $elasticaResultSet->getTotalHits();
		}
		return 0;
	}

	/**
	 * @return \Elastica\Response|null
	 */
	final public function getElasticResponse() {
		$elasticaResultSet = $this->getElasticaResultSet();
		return $elasticaResultSet != null ? $elasticaResultSet->getResponse() : null;
	}

	/**
	 * Useful to inject your own TitleHelper during tests
	 * @return TitleHelper
	 */
	protected function getTitleHelper(): TitleHelper {
		if ( $this->titleHelper === null ) {
			$this->titleHelper = new TitleHelper();
		}
		return $this->titleHelper;
	}
}
