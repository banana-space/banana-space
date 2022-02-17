<?php

namespace CirrusSearch;

use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Query\CompSuggestQueryBuilder;
use CirrusSearch\Query\PrefixSearchQueryBuilder;
use CirrusSearch\Search\CompletionResultsCollector;
use CirrusSearch\Search\FancyTitleResultsType;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\Search\SearchRequestBuilder;
use Elastica\Exception\ExceptionInterface;
use Elastica\Index;
use Elastica\Multi\Search as MultiSearch;
use Elastica\Query;
use Elastica\ResultSet;
use Elastica\Search;
use MediaWiki\MediaWikiServices;
use SearchSuggestionSet;
use Status;
use User;
use Wikimedia\Assert\Assert;

/**
 * Performs search as you type queries using Completion Suggester.
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
 */

/**
 * Completion Suggester Searcher
 *
 * NOTES:
 * The CompletionSuggester is built on top of the ElasticSearch Completion
 * Suggester.
 * (https://www.elastic.co/guide/en/elasticsearch/reference/current/search-suggesters-completion.html).
 *
 * This class is used at query time, see
 * CirrusSearch\BuildDocument\SuggestBuilder for index time logic.
 *
 * Document model: Cirrus documents are indexed with 2 suggestions:
 *
 * 1. The title suggestion (and close redirects).
 * This helps to avoid displaying redirects with typos (e.g. Albert Enstein,
 * Unietd States) where we make the assumption that if the redirect is close
 * enough it's likely a typo and it's preferable to display the canonical title.
 * This decision is made at index-time in SuggestBuilder::extractTitleAndSimilarRedirects.
 *
 * 2. The redirect suggestions
 * Because the same canonical title can be returned twice we support fetch_limit_factor
 * in suggest profiles to fetch more than what the use asked.
 *
 * Additionally if the namespaces request include non NS_MAIN a prefix search query
 * is sent to the main index. Results are appended to the suggest results. Appending
 * is far from ideal but in the current state scores between the suggest index and prefix
 * search are not comparable.
 * TODO: investigate computing the comp suggest score on main indices to properly merge
 * results.
 */
class CompletionSuggester extends ElasticsearchIntermediary {
	/**
	 * @const string multisearch key to identify the comp suggest request
	 */
	const MSEARCH_KEY_SUGGEST = "suggest";

	/**
	 * @const string multisearch key to identify the prefix search request
	 */
	const MSEARCH_KEY_PREFIX = "prefix";

	/**
	 * Search type (used for logs & timeout configs)
	 */
	const SEARCH_TYPE = 'comp_suggest';

	/**
	 * @var integer maximum number of result (final)
	 */
	private $limit;

	/**
	 * @var integer offset (final)
	 */
	private $offset;

	/**
	 * @var string index base name to use (final)
	 */
	private $indexBaseName;

	/**
	 * @var Index (final)
	 */
	private $completionIndex;

	/**
	 * Search environment configuration (final)
	 * @var SearchConfig
	 */
	private $config;

	/**
	 * @var SearchContext (final)
	 */
	private $searchContext;

	/**
	 * @var CompSuggestQueryBuilder (final)
	 */
	private $compSuggestBuilder;

	/**
	 * @var PrefixSearchQueryBuilder (final)
	 */
	private $prefixSearchQueryBuilder;

	/**
	 * @var SearchRequestBuilder the builder to build the search for prefix search queries
	 */
	private $prefixSearchRequestBuilder;

	/**
	 * @param Connection $conn
	 * @param int $limit Limit the results to this many
	 * @param int $offset
	 * @param SearchConfig|null $config Configuration settings
	 * @param int[]|null $namespaces Array of namespace numbers to search or null to search all namespaces.
	 * @param User|null $user user for which this search is being performed.  Attached to slow request logs.
	 * @param string|bool $index Base name for index to search from, defaults to $wgCirrusSearchIndexBaseName
	 * @param string|null $profileName force the profile to use otherwise SearchProfileService defaults will be used
	 */
	public function __construct( Connection $conn, $limit, $offset = 0, SearchConfig $config = null, array $namespaces = null,
		User $user = null, $index = false, $profileName = null ) {
		if ( $config === null ) {
			// @todo connection has an embedded config ... reuse that? somehow should
			// at least ensure they are the same.
			$config = MediaWikiServices::getInstance()
				->getConfigFactory()
				->makeConfig( 'CirrusSearch' );
		}

		parent::__construct( $conn, $user, $config->get( 'CirrusSearchSlowSearch' ) );
		$this->config = $config;
		$this->limit = $limit;
		$this->offset = $offset;
		$this->indexBaseName = $index ?: $config->get( SearchConfig::INDEX_BASE_NAME );
		$this->completionIndex = $this->connection->getIndex( $this->indexBaseName,
			Connection::TITLE_SUGGEST_TYPE );
		$this->searchContext = new SearchContext( $this->config, $namespaces );

		$profileDefinition = $this->config->getProfileService()
			->loadProfile( SearchProfileService::COMPLETION, SearchProfileService::CONTEXT_DEFAULT, $profileName );
		$this->compSuggestBuilder = new CompSuggestQueryBuilder(
			$this->searchContext,
			$profileDefinition,
			$limit,
			$offset
		);
		$this->prefixSearchQueryBuilder = new PrefixSearchQueryBuilder();
	}

	/**
	 * Produce a set of completion suggestions for text using _suggest
	 * See https://www.elastic.co/guide/en/elasticsearch/reference/1.6/search-suggesters-completion.html
	 *
	 * WARNING: experimental API
	 *
	 * @param string $text Search term
	 * @param string[]|null $variants Search term variants
	 *  Usually issued via Language::autoConvertToAllVariants( $text ) for the content language.
	 * @return Status
	 */
	public function suggest( $text, $variants = null ) {
		$suggestSearch = $this->getSuggestSearchRequest( $text, $variants );
		$msearch = new MultiSearch( $this->connection->getClient() );
		if ( $suggestSearch !== null ) {
			$msearch->addSearch( $suggestSearch, self::MSEARCH_KEY_SUGGEST );
		}

		$prefixSearch = $this->getPrefixSearchRequest( $text, $variants );
		if ( $prefixSearch !== null ) {
			$msearch->addSearch( $prefixSearch, self::MSEARCH_KEY_PREFIX );
		}

		if ( empty( $msearch->getSearches() ) ) {
			return Status::newGood( SearchSuggestionSet::emptySuggestionSet() );
		}

		$this->connection->setTimeout( $this->getClientTimeout( self::SEARCH_TYPE ) );
		$result = Util::doPoolCounterWork(
			'CirrusSearch-Completion',
			$this->user,
			function () use( $msearch, $text ) {
				$log = $this->newLog( "{queryType} search for '{query}'", self::SEARCH_TYPE, [
					'query' => $text,
					'offset' => $this->offset,
				] );
				$this->start( $log );
				try {
					$results = $msearch->search();
					if ( $results->hasError() ||
						// Catches HTTP errors (ex: 5xx) not reported
						// by hasError()
						!$results->getResponse()->isOk()
					) {
						return $this->multiFailure( $results );
					}
					return $this->success( $this->processMSearchResponse( $results->getResultSets(), $log ) );
				} catch ( ExceptionInterface $e ) {
					return $this->failure( $e );
				}
			}
		);
		return $result;
	}

	/**
	 * @param ResultSet[] $results
	 * @param CompletionRequestLog $log
	 * @return SearchSuggestionSet
	 */
	private function processMSearchResponse( array $results, CompletionRequestLog $log ) {
		$collector = new CompletionResultsCollector( $this->limit, $this->offset );
		$totalHits = $this->collectCompSuggestResults( $collector, $results, $log );
		$totalHits += $this->collectPrefixSearchResults( $collector, $results, $log );
		$log->setTotalHits( $totalHits );
		return $collector->logAndGetSet( $log );
	}

	/**
	 * @param CompletionResultsCollector $collector
	 * @param ResultSet[] $results
	 * @param CompletionRequestLog $log
	 * @return int
	 */
	private function collectCompSuggestResults( CompletionResultsCollector $collector, array $results, CompletionRequestLog $log ) {
		if ( !isset( $results[self::MSEARCH_KEY_SUGGEST] ) ) {
			return 0;
		}
		$log->addIndex( $this->completionIndex->getName() );
		$suggestResults = $results[self::MSEARCH_KEY_SUGGEST];
		$log->setSuggestTookMs( intval( $suggestResults->getResponse()->getQueryTime() * 1000 ) );
		return $this->compSuggestBuilder->postProcess(
			$collector,
			$suggestResults,
			$this->completionIndex->getName()
		);
	}

	/**
	 * @param CompletionResultsCollector $collector
	 * @param ResultSet[] $results
	 * @param CompletionRequestLog $log
	 * @return int
	 */
	private function collectPrefixSearchResults( CompletionResultsCollector $collector, array $results, CompletionRequestLog $log ) {
		if ( !isset( $results[self::MSEARCH_KEY_PREFIX] ) ) {
			return 0;
		}
		$indexName = $this->prefixSearchRequestBuilder->getPageType()->getIndex()->getName();
		$prefixResults = $results[self::MSEARCH_KEY_PREFIX];
		$totalHits = $prefixResults->getTotalHits();
		$log->addIndex( $indexName );
		$log->setPrefixTookMs( intval( $prefixResults->getResponse()->getQueryTime() * 1000 ) );
		// We only append as we can't really compare scores without more complex code/evaluation
		if ( $collector->isFull() ) {
			return $totalHits;
		}
		/** @var FancyTitleResultsType $rType */
		$rType = $this->prefixSearchRequestBuilder->getSearchContext()->getResultsType();
		// the code below highly depends on the array format built by
		// FancyTitleResultsType::transformOneElasticResult assert that this type
		// is properly set so that we fail during unit tests if someone changes it
		// inadvertently.
		Assert::precondition( $rType instanceof FancyTitleResultsType, '$rType must be a FancyTitleResultsType' );
		// scores can go negative, it's not a problem we only use scores for sorting
		// they'll be forgotten in client response
		$score = $collector->getMinScore() !== null ? $collector->getMinScore() - 1 : count( $prefixResults->getResults() );

		$namespaces = $this->prefixSearchRequestBuilder->getSearchContext()->getNamespaces();
		foreach ( $prefixResults->getResults() as $res ) {
			$pageId = $this->config->makePageId( $res->getId() );
			$title = FancyTitleResultsType::chooseBestTitleOrRedirect( $rType->transformOneElasticResult( $res, $namespaces ) );
			if ( $title === false ) {
				continue;
			}
			$suggestion = new \SearchSuggestion( $score--, $title->getPrefixedText(), $title, $pageId );
			if ( !$collector->collect( $suggestion, 'prefix', $indexName ) && $collector->isFull() ) {
				break;
			}
		}
		return $totalHits;
	}

	/**
	 * @param string $text Search term
	 * @param string[]|null $variants Search term variants
	 *  Usually issued via Language::autoConvertToAllVariants( $text ) for the content language.
	 * @return Search|null
	 */
	private function getSuggestSearchRequest( $text, $variants ) {
		if ( !$this->compSuggestBuilder->areResultsPossible() ) {
			return null;
		}

		$suggest = $this->compSuggestBuilder->build( $text, $variants );
		$query = new Query( new Query\MatchNone() );
		$query->setSize( 0 );
		$query->setSuggest( $suggest );
		$query->setSource( [ 'target_title' ] );
		$search = new Search( $this->connection->getClient() );
		$search->addIndex( $this->completionIndex );
		$search->setQuery( $query );
		return $search;
	}

	/**
	 * @param string $term Search term
	 * @param string[]|null $variants Search term variants
	 *  Usually issued via Language::autoConvertToAllVariants( $text ) for the content language.
	 * @return Search|null
	 */
	private function getPrefixSearchRequest( $term, $variants ) {
		$namespaces = $this->searchContext->getNamespaces();
		if ( $namespaces === null ) {
			return null;
		}

		foreach ( $namespaces as $k => $v ) {
			// non-strict comparison, it can be strings
			if ( $v == NS_MAIN ) {
				unset( $namespaces[$k] );
			}
		}

		if ( $namespaces === [] ) {
			return null;
		}
		$limit = CompSuggestQueryBuilder::computeHardLimit( $this->limit, $this->offset, $this->config );
		if ( $this->offset > $limit ) {
			return null;
		}
		$prefixSearchContext = new SearchContext( $this->config, $namespaces );
		$prefixSearchContext->setResultsType( new FancyTitleResultsType( 'prefix' ) );
		$this->prefixSearchQueryBuilder->build( $prefixSearchContext, $term, $variants );
		$this->prefixSearchRequestBuilder = new SearchRequestBuilder( $prefixSearchContext, $this->connection, $this->indexBaseName );
		$this->prefixSearchRequestBuilder->setTimeout( $this->getTimeout( self::SEARCH_TYPE ) );
		return $this->prefixSearchRequestBuilder->setLimit( $limit )
			// collect all results up to $limit, $this->offset is the offset the client wants
			// not the offset in prefix search results.
			->setOffset( 0 )
			->build();
	}

	/**
	 * @param string $description
	 * @param string $queryType
	 * @param array $extra
	 * @return CompletionRequestLog
	 */
	protected function newLog( $description, $queryType, array $extra = [] ) {
		return new CompletionRequestLog(
			$description,
			$queryType,
			$extra,
			$this->searchContext->getNamespaces()
		);
	}

	/**
	 * @return Index
	 */
	public function getCompletionIndex() {
		return $this->completionIndex;
	}
}
