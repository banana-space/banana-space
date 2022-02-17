<?php

namespace CirrusSearch\Search;

use CirrusSearch\Connection;
use Elastica\Query;
use Elastica\Type;
use MediaWiki\Logger\LoggerFactory;

/**
 * Build the search request body
 */
class SearchRequestBuilder {
	/** @var SearchContext */
	private $searchContext;

	/** @var Connection */
	private $connection;

	/** @var string */
	private $indexBaseName;

	/** @var  int */
	private $offset = 0;

	/** @var  int */
	private $limit = 20;

	/** @var string search timeout, string with time and unit, e.g. 20s for 20 seconds */
	private $timeout;

	/** @var Type|null Force the type when set, use {@link Connection::pickIndexTypeForNamespaces}
	 * otherwise
	 */
	private $pageType;

	/** @var string set the sort option, controls the use of rescore functions or elastic sort */
	private $sort = 'relevance';

	public function __construct( SearchContext $searchContext, Connection $connection, $indexBaseName ) {
		$this->searchContext = $searchContext;
		$this->connection = $connection;
		$this->indexBaseName = $indexBaseName;
	}

	/**
	 * Build the search request
	 * @return \Elastica\Search
	 */
	public function build() {
		$resultsType = $this->searchContext->getResultsType();

		$query = new Query();
		$query->setSource( $resultsType->getSourceFiltering() );
		$query->setStoredFields( $resultsType->getStoredFields() );

		$extraIndexes = $this->searchContext->getExtraIndices();

		if ( !empty( $extraIndexes ) ) {
			$this->searchContext->addNotFilter( new \Elastica\Query\Term(
				[ 'local_sites_with_dupe' => $this->indexBaseName ]
			) );
		}

		$mainQuery = $this->searchContext->getQuery();
		$query->setQuery( $mainQuery );

		foreach ( $this->searchContext->getAggregations() as $agg ) {
			$query->addAggregation( $agg );
		}

		$highlight = $this->searchContext->getHighlight( $resultsType, $mainQuery );
		if ( $highlight ) {
			$query->setHighlight( $highlight );
		}

		$suggestQueries = $this->searchContext->getFallbackRunner()->getElasticSuggesters();
		if ( !empty( $suggestQueries ) ) {
			$query->setParam( 'suggest', [
				// TODO: remove special case on 1-elt array, added to not change the test fixtures
				// We should switch to explicit naming
				'suggest' => count( $suggestQueries ) === 1 ? reset( $suggestQueries ) : $suggestQueries
			] );
			$query->addParam( 'stats', 'suggest' );
		}

		foreach ( $this->searchContext->getSyntaxUsed() as $syntax ) {
			$query->addParam( 'stats', $syntax );
		}

		// See also CirrusSearch::getValidSorts()
		switch ( $this->sort ) {
			case 'just_match':
				// Use just matching scores, without any rescoring, and default sort.
				break;
			case 'relevance':
				// Add some rescores to improve relevance
				$rescores = $this->searchContext->getRescore();
				if ( $rescores !== [] ) {
					$query->setParam( 'rescore', $rescores );
				}
				break;  // The default
			case 'create_timestamp_asc':
				$query->setSort( [ 'create_timestamp' => 'asc' ] );
				break;
			case 'create_timestamp_desc':
				$query->setSort( [ 'create_timestamp' => 'desc' ] );
				break;
			case 'last_edit_asc':
				$query->setSort( [ 'timestamp' => 'asc' ] );
				break;
			case 'last_edit_desc':
				$query->setSort( [ 'timestamp' => 'desc' ] );
				break;
			case 'incoming_links_asc':
				$query->setSort( [ 'incoming_links' => [
					'order' => 'asc',
					'missing' => '_first',
				] ] );
				break;
			case 'incoming_links_desc':
				$query->setSort( [ 'incoming_links' => [
					'order' => 'desc',
					'missing' => '_last',
				] ] );
				break;
			case 'none':
				// Return documents in index order
				$query->setSort( [ '_doc' ] );
				break;
			case 'random':
				if ( $this->offset !== 0 ) {
					$this->searchContext->addWarning( 'cirrussearch-offset-not-allowed-with-random-sort' );
					$this->offset = 0;
				}
				// Instead of setting a sort field wrap the whole query in a
				// bool filter and add a must clause for the random score. This
				// could alternatively be a rescore over a limited document
				// set, but in basic testing the filter was more performant
				// than an 8k rescore window even with 50M total hits.
				$query->setQuery( ( new Query\BoolQuery() )
					->addFilter( $mainQuery )
					->addMust( ( new Query\FunctionScore() )
						->setQuery( new Query\MatchAll() )
						/** @phan-suppress-next-line PhanTypeMismatchArgument empty array isn't jsonified to {} properly */
						->addFunction( 'random_score', (object)[] ) ) );
				break;
			default:
				// Same as just_match. No user warning since an invalid sort
				// getting this far as a bug in the calling code which should
				// be validating it's input.
				LoggerFactory::getInstance( 'CirrusSearch' )->warning(
					"Invalid sort type: {sort}",
					[ 'sort' => $this->sort ]
				);
		}

		if ( $this->offset ) {
			$query->setFrom( $this->offset );
		}
		if ( $this->limit ) {
			$query->setSize( $this->limit );
		}

		// Setup the search
		$queryOptions = [];
		if ( $this->timeout ) {
			$queryOptions[\Elastica\Search::OPTION_TIMEOUT] = $this->timeout;
		}
		// @todo when switching to multi-search this has to be provided at the top level
		if ( $this->searchContext->getConfig()->get( 'CirrusSearchMoreAccurateScoringMode' ) ) {
			$queryOptions[\Elastica\Search::OPTION_SEARCH_TYPE] = \Elastica\Search::OPTION_SEARCH_TYPE_DFS_QUERY_THEN_FETCH;
		}

		$pageType = $this->getPageType();

		$search = $pageType->createSearch( $query, $queryOptions );
		$crossClusterName = $this->connection->getConfig()->getClusterAssignment()->getCrossClusterName();
		foreach ( $extraIndexes as $i ) {
			$search->addIndex( $i->getSearchIndex( $crossClusterName ) );
		}

		$this->searchContext->getDebugOptions()->applyDebugOptions( $query );
		return $search;
	}

	/**
	 * @return int
	 */
	public function getOffset() {
		return $this->offset;
	}

	/**
	 * @param int $offset
	 * @return SearchRequestBuilder
	 */
	public function setOffset( $offset ) {
		$this->offset = $offset;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getLimit() {
		return $this->limit;
	}

	/**
	 * @param int $limit
	 * @return SearchRequestBuilder
	 */
	public function setLimit( $limit ) {
		$this->limit = $limit;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTimeout() {
		return $this->timeout;
	}

	/**
	 * @param string $timeout
	 * @return SearchRequestBuilder
	 */
	public function setTimeout( $timeout ) {
		$this->timeout = $timeout;

		return $this;
	}

	/**
	 * @return Type An elastica type suitable for searching against
	 *  the configured wiki over the host wiki's default connection.
	 */
	public function getPageType() {
		if ( $this->pageType ) {
			return $this->pageType;
		} else {
			$indexBaseName = $this->indexBaseName;
			$config = $this->searchContext->getConfig();
			$hostConfig = $config->getHostWikiConfig();
			$indexType = $this->connection->pickIndexTypeForNamespaces(
				$this->searchContext->getNamespaces() );
			if ( $hostConfig->get( 'CirrusSearchCrossClusterSearch' ) ) {
				$local = $hostConfig->getClusterAssignment()->getCrossClusterName();
				$current = $config->getClusterAssignment()->getCrossClusterName();
				if ( $local !== $current ) {
					$indexBaseName = $current . ':' . $indexBaseName;
				}
			}
			return $this->connection->getPageType( $indexBaseName, $indexType );
		}
	}

	/**
	 * Override the index/type used for search. When this is used automatic
	 * handling of cross-cluster search is disabled.
	 *
	 * @param Type|null $pageType
	 * @return SearchRequestBuilder
	 */
	public function setPageType( $pageType ) {
		$this->pageType = $pageType;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSort() {
		return $this->sort;
	}

	/**
	 * @param string $sort
	 * @return SearchRequestBuilder
	 */
	public function setSort( $sort ) {
		$this->sort = $sort;

		return $this;
	}

	/**
	 * @return SearchContext
	 */
	public function getSearchContext() {
		return $this->searchContext;
	}
}
