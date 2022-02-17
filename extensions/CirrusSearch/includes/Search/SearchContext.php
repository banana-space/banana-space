<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\ExternalIndex;
use CirrusSearch\Fallbacks\FallbackRunner;
use CirrusSearch\OtherIndexesUpdater;
use CirrusSearch\Parser\BasicQueryClassifier;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Query\Builder\FilterBuilder;
use CirrusSearch\Search\Fetch\FetchPhaseConfigBuilder;
use CirrusSearch\Search\Rescore\BoostFunctionBuilder;
use CirrusSearch\Search\Rescore\RescoreBuilder;
use CirrusSearch\SearchConfig;
use CirrusSearch\WarningCollector;
use Elastica\Aggregation\AbstractAggregation;
use Elastica\Query\AbstractQuery;
use Wikimedia\Assert\Assert;

/**
 * The search context, maintains the state of the current search query.
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
 * The SearchContext stores the various states maintained
 * during the query building process.
 */
class SearchContext implements WarningCollector, FilterBuilder {
	/**
	 * @var SearchConfig
	 */
	private $config;

	/**
	 * @var int[]|null list of namespaces
	 */
	private $namespaces;

	/**
	 * @var string
	 */
	private $profileContext = SearchProfileService::CONTEXT_DEFAULT;

	/**
	 * @var string[]
	 */
	private $profileContextParams = [];

	/**
	 * @var string rescore profile to use
	 */
	private $rescoreProfile;

	/**
	 * @var BoostFunctionBuilder[] Extra scoring builders to use.
	 */
	private $extraScoreBuilders = [];

	/**
	 * @var bool Could this query possibly return results?
	 */
	private $resultsPossible = true;

	/**
	 * @var int[] List of features in the user suplied query string. Features are
	 *  held in the array key, value is how "complex" the feature is.
	 */
	private $syntaxUsed = [];

	/**
	 * @var AbstractQuery[] List of filters that query results must match
	 */
	private $filters = [];

	/**
	 * @var AbstractQuery[] List of filters that query results must not match
	 */
	private $notFilters = [];

	/**
	 * @var AbstractQuery|null Query that should be used for highlighting if different
	 *  from the query used for selecting.
	 */
	private $highlightQuery;

	/**
	 * @var AbstractQuery[] queries that don't use Elastic's "query string" query,
	 *  for more advanced highlighting (e.g. match_phrase_prefix for regular
	 *  quoted strings).
	 */
	private $nonTextHighlightQueries = [];

	/**
	 * @var AbstractQuery|null phrase rescore query
	 */
	private $phraseRescoreQuery;

	/**
	 * @var AbstractQuery|null main query. null defaults to MatchAll
	 */
	private $mainQuery;

	/**
	 * @var \Elastica\Query\MatchQuery[] Queries that don't use Elastic's "query string" query, for
	 *  more advanced searching (e.g. match_phrase_prefix for regular quoted strings).
	 */
	private $nonTextQueries = [];

	/**
	 * @var bool Should this search limit results to the local wiki?
	 */
	private $limitSearchToLocalWiki = false;

	/**
	 * @var int The number of seconds to cache results for
	 */
	private $cacheTtl = 0;

	/**
	 * @var string The original search
	 */
	private $originalSearchTerm;

	/**
	 * @var string The users search term with keywords removed
	 */
	private $cleanedSearchTerm;

	/**
	 * @var Escaper
	 */
	private $escaper;

	/**
	 * @var FetchPhaseConfigBuilder
	 */
	private $fetchPhaseBuilder;

	/**
	 * @var int[] weights of different syntaxes
	 */
	private static $syntaxWeights = [
		// regex is really tough
		'full_text' => 10,
		'regex' => PHP_INT_MAX,
		'more_like' => 100,
		'near_match' => 10,
		'prefix' => 2,
		// Deep category searches
		'deepcategory' => 20,
	];

	/**
	 * @var array[] Warnings to be passed into StatusValue::warning()
	 */
	private $warnings = [];

	/**
	 * @var string name of the fulltext query builder profile
	 */
	private $fulltextQueryBuilderProfile;

	/**
	 * @var bool Have custom options that effect the search results been set
	 *  outside the defaults from config?
	 */
	private $isDirty = false;

	/**
	 * @var ResultsType|FullTextResultsType Type of the result for the context.
	 */
	private $resultsType;

	/**
	 * @var AbstractAggregation[] Aggregations to perform
	 */
	private $aggs = [];

	/**
	 * @var CirrusDebugOptions
	 */
	private $debugOptions;

	/**
	 * @var FallbackRunner|null
	 */
	private $fallbackRunner;

	/**
	 * @param SearchConfig $config
	 * @param int[]|null $namespaces
	 * @param CirrusDebugOptions|null $options
	 * @param FallbackRunner|null $fallbackRunner
	 * @param FetchPhaseConfigBuilder|null $fetchPhaseConfigBuilder
	 */
	public function __construct(
		SearchConfig $config,
		array $namespaces = null,
		CirrusDebugOptions $options = null,
		FallbackRunner $fallbackRunner = null,
		FetchPhaseConfigBuilder $fetchPhaseConfigBuilder = null
	) {
		$this->config = $config;
		$this->namespaces = $namespaces;
		$this->debugOptions = $options ?? CirrusDebugOptions::defaultOptions();
		$this->fallbackRunner = $fallbackRunner ?? FallbackRunner::noopRunner();
		$this->fetchPhaseBuilder = $fetchPhaseConfigBuilder ?? new FetchPhaseConfigBuilder( $config );
		$this->loadConfig();
	}

	/**
	 * Return a copy of this context with a new configuration.
	 *
	 * @param SearchConfig $config The new configuration
	 * @return SearchContext
	 */
	public function withConfig( SearchConfig $config ) {
		$other = clone $this;
		$other->config = $config;
		$other->fetchPhaseBuilder = $this->fetchPhaseBuilder->withConfig( $config );
		if ( $other->resultsType instanceof FullTextResultsType ) {
			$other->resultsType = $this->resultsType->withFetchPhaseBuilder( $other->fetchPhaseBuilder );
		}
		$other->loadConfig();

		return $other;
	}

	private function loadConfig() {
		$this->escaper = new Escaper( $this->config->get( 'LanguageCode' ), $this->config->get( 'CirrusSearchAllowLeadingWildcard' ) );
	}

	public function __clone() {
		if ( $this->mainQuery ) {
			$this->mainQuery = clone $this->mainQuery;
		}
	}

	/**
	 * Have custom options that effect the search results been set outside the
	 * defaults from config?
	 *
	 * @return bool
	 */
	public function isDirty() {
		return $this->isDirty;
	}

	/**
	 * @return SearchConfig the Cirrus config object
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * mediawiki namespace id's being requested.
	 * NOTE: this value may change during the Searcher process.
	 *
	 * @return int[]|null
	 */
	public function getNamespaces() {
		return $this->namespaces;
	}

	/**
	 * set the mediawiki namespace id's
	 *
	 * @param int[]|null $namespaces array of integer
	 */
	public function setNamespaces( $namespaces ) {
		$this->isDirty = true;
		$this->namespaces = $namespaces;
	}

	/**
	 * @return string
	 */
	public function getProfileContext() {
		return $this->profileContext;
	}

	/**
	 * @return string[]
	 */
	public function getProfileContextParams(): array {
		return $this->profileContextParams;
	}

	/**
	 * @param string $profileContext
	 * @param string[] $contextParams
	 */
	public function setProfileContext( $profileContext, array $contextParams = [] ) {
		$this->isDirty = $this->isDirty ||
			$this->profileContext !== $profileContext ||
			$this->profileContextParams !== $contextParams;
		$this->profileContext = $profileContext;
		$this->profileContextParams = $contextParams;
	}

	/**
	 * @return string the rescore profile to use
	 */
	public function getRescoreProfile() {
		if ( $this->rescoreProfile === null ) {
			$this->rescoreProfile = $this->config->getProfileService()
				->getProfileName( SearchProfileService::RESCORE, $this->profileContext, $this->profileContextParams );
		}
		return $this->rescoreProfile;
	}

	/**
	 * @param string $rescoreProfile the rescore profile to use
	 */
	public function setRescoreProfile( $rescoreProfile ) {
		$this->isDirty = true;
		$this->rescoreProfile = $rescoreProfile;
	}

	/**
	 * @return bool Could this query possibly return results?
	 */
	public function areResultsPossible() {
		return $this->resultsPossible;
	}

	/**
	 * @param bool $possible Could this query possible return results? Defaults to true
	 *  if not called.
	 */
	public function setResultsPossible( $possible ) {
		$this->isDirty = true;
		$this->resultsPossible = $possible;
	}

	/**
	 * @param string|null $type type of syntax to check, null for any type
	 * @return bool True when the query uses $type kind of syntax
	 */
	public function isSyntaxUsed( $type = null ) {
		if ( $type === null ) {
			return $this->syntaxUsed !== [];
		}
		return isset( $this->syntaxUsed[$type] );
	}

	/**
	 * @return bool true if a special keyword or syntax was used in the query
	 */
	public function isSpecialKeywordUsed() {
		// full_text is not considered a special keyword
		// TODO: investigate using BasicQueryClassifier::SIMPLE_BAG_OF_WORDS instead
		return !empty( array_diff_key( $this->syntaxUsed, [
			'full_text' => true,
			'full_text_simple_match' => true,
			'full_text_querystring' => true,
			BasicQueryClassifier::SIMPLE_BAG_OF_WORDS => true,
			BasicQueryClassifier::SIMPLE_PHRASE => true,
			BasicQueryClassifier::BAG_OF_WORDS_WITH_PHRASE => true,
		] ) );
	}

	/**
	 * @return string[] List of syntax used in the query
	 */
	public function getSyntaxUsed() {
		return array_keys( $this->syntaxUsed );
	}

	/**
	 * @return string Text description of syntax used by query.
	 */
	public function getSyntaxDescription() {
		return implode( ',', $this->getSyntaxUsed() );
	}

	/**
	 * @param string $feature Name of a syntax feature used in the query string
	 * @param int|null $weight How "complex" is this feature.
	 */
	public function addSyntaxUsed( $feature, $weight = null ) {
		$this->isDirty = true;
		if ( $weight === null ) {
			if ( isset( self::$syntaxWeights[$feature] ) ) {
				$weight = self::$syntaxWeights[$feature];
			} else {
				$weight = 1;
			}
		}
		$this->syntaxUsed[$feature] = $weight;
	}

	/**
	 * @return string The type of search being performed, ex: full_text, near_match, prefix, etc.
	 * Using getSyntaxUsed() is better in most cases.
	 */
	public function getSearchType() {
		if ( empty( $this->syntaxUsed ) ) {
			return 'full_text';
		}
		arsort( $this->syntaxUsed );
		// Return the first heaviest syntax
		return key( $this->syntaxUsed );
	}

	/**
	 * @param AbstractQuery $filter Query results must match this filter
	 */
	public function addFilter( AbstractQuery $filter ) {
		$this->isDirty = true;
		$this->filters[] = $filter;
	}

	/**
	 * @param AbstractQuery $filter Query results must not match this filter
	 */
	public function addNotFilter( AbstractQuery $filter ) {
		$this->isDirty = true;
		$this->notFilters[] = $filter;
	}

	/**
	 * @param AbstractQuery|null $query Query that should be used for highlighting if different
	 *  from the query used for selecting.
	 */
	public function setHighlightQuery( AbstractQuery $query = null ) {
		$this->isDirty = true;
		$this->highlightQuery = $query;
	}

	/**
	 * @param AbstractQuery $query queries that don't use Elastic's "query
	 * string" query, for more advanced highlighting (e.g. match_phrase_prefix
	 * for regular quoted strings).
	 */
	public function addNonTextHighlightQuery( AbstractQuery $query ) {
		$this->isDirty = true;
		$this->nonTextHighlightQueries[] = $query;
	}

	/**
	 * @return FetchPhaseConfigBuilder
	 */
	public function getFetchPhaseBuilder(): FetchPhaseConfigBuilder {
		return $this->fetchPhaseBuilder;
	}

	/**
	 * @param ResultsType $resultsType
	 * @param AbstractQuery $mainQuery Will be combined with highlighting query
	 *  to provide highlightable terms.
	 * @return array|null Fetch portion of query to be sent to elasticsearch
	 */
	public function getHighlight( ResultsType $resultsType, AbstractQuery $mainQuery ) {
		$highlight = $resultsType->getHighlightingConfiguration( [] );
		if ( !$highlight ) {
			return null;
		}

		$query = $this->getHighlightQuery( $mainQuery );
		if ( $query ) {
			$highlight['highlight_query'] = $query->toArray();
		}

		return $highlight;
	}

	/**
	 * @param AbstractQuery $mainQuery
	 * @return AbstractQuery|null Query that should be used for highlighting if different
	 *  from the query used for selecting.
	 */
	private function getHighlightQuery( AbstractQuery $mainQuery ) {
		if ( empty( $this->nonTextHighlightQueries ) ) {
			// If no explicit highlight query is provided elastic
			// will fallback to $mainQuery without specifying it.
			return $this->highlightQuery;
		}

		$bool = new \Elastica\Query\BoolQuery();
		// If no explicit highlight query is provided we
		// need to include the main query along with
		// the non-text queries to highlight those fields.
		$bool->addShould( $this->highlightQuery ?: $mainQuery );
		foreach ( $this->nonTextHighlightQueries as $nonTextHighlightQuery ) {
			$bool->addShould( $nonTextHighlightQuery );
		}

		return $bool;
	}

	/**
	 * rescore_query has to be in array form before we send it to Elasticsearch but it is way
	 * easier to work with if we leave it in query form until now
	 *
	 * @return array[] Rescore configurations as used by elasticsearch.
	 */
	public function getRescore() {
		$rescores = ( new RescoreBuilder( $this ) )->build();
		$result = [];
		foreach ( $rescores as $rescore ) {
			$rescore['query']['rescore_query'] = $rescore['query']['rescore_query']->toArray();
			$result[] = $rescore;
		}

		return $result;
	}

	/**
	 * @return AbstractQuery The primary query to be sent to elasticsearch. Includes
	 *  the main quedry, non text queries, and any additional filters.
	 */
	public function getQuery() {
		if ( empty( $this->nonTextQueries ) ) {
			$mainQuery = $this->mainQuery ?: new \Elastica\Query\MatchAll();
		} else {
			$mainQuery = new \Elastica\Query\BoolQuery();
			if ( $this->mainQuery ) {
				$mainQuery->addMust( $this->mainQuery );
			}
			foreach ( $this->nonTextQueries as $nonTextQuery ) {
				$mainQuery->addMust( $nonTextQuery );
			}
		}
		$filters = $this->filters;
		if ( $this->getNamespaces() ) {
			$filters[] = new \Elastica\Query\Terms( 'namespace', $this->getNamespaces() );
		}

		// Wrap $mainQuery in a filtered query if there are any filters
		$unifiedFilter = Filters::unify( $filters, $this->notFilters );
		if ( $unifiedFilter !== null ) {
			if ( !( $mainQuery instanceof \Elastica\Query\BoolQuery ) ) {
				$bool = new \Elastica\Query\BoolQuery();
				$bool->addMust( $mainQuery );
				$mainQuery = $bool;
			}
			$mainQuery->addFilter( $unifiedFilter );
		}

		return $mainQuery;
	}

	/**
	 * @param AbstractQuery $query The primary query to be passed to
	 *  elasticsearch.
	 */
	public function setMainQuery( AbstractQuery $query ) {
		$this->isDirty = true;
		$this->mainQuery = $query;
	}

	/**
	 * @param \Elastica\Query\AbstractQuery $match Queries that don't use Elastic's
	 * "query string" query, for more advanced searching (e.g.
	 *  match_phrase_prefix for regular quoted strings).
	 */
	public function addNonTextQuery( \Elastica\Query\AbstractQuery $match ) {
		$this->isDirty = true;
		$this->nonTextQueries[] = $match;
	}

	/**
	 * @return bool Should this search limit results to the local wiki? If
	 *  not called the default is false.
	 */
	public function getLimitSearchToLocalWiki() {
		return $this->limitSearchToLocalWiki;
	}

	/**
	 * @param bool $localWikiOnly Should this search limit results to the local wiki? If
	 *  not called the default is false.
	 */
	public function setLimitSearchToLocalWiki( $localWikiOnly ) {
		if ( $localWikiOnly !== $this->limitSearchToLocalWiki ) {
			$this->isDirty = true;
			$this->limitSearchToLocalWiki = $localWikiOnly;
		}
	}

	/**
	 * @return int The number of seconds to cache results for
	 */
	public function getCacheTtl() {
		return $this->cacheTtl;
	}

	/**
	 * @param int $ttl The number of seconds to cache results for
	 */
	public function setCacheTtl( $ttl ) {
		$this->isDirty = true;
		$this->cacheTtl = $ttl;
	}

	/**
	 * @return string the original search term
	 */
	public function getOriginalSearchTerm() {
		return $this->originalSearchTerm;
	}

	/**
	 * Set the original search term
	 * @param string $term
	 */
	public function setOriginalSearchTerm( $term ) {
		// Intentionally does not set dirty to true. This is used only
		// for logging, as of july 2017.
		$this->originalSearchTerm = $term;
	}

	/**
	 * @return string The search term with keywords removed
	 */
	public function getCleanedSearchTerm() {
		return $this->cleanedSearchTerm;
	}

	/**
	 * @param string $term The search term with keywords removed
	 */
	public function setCleanedSearchTerm( $term ) {
		$this->isDirty = true;
		$this->cleanedSearchTerm = $term;
	}

	/**
	 * @return Escaper
	 */
	public function escaper() {
		return $this->escaper;
	}

	/**
	 * @return BoostFunctionBuilder[]
	 */
	public function getExtraScoreBuilders() {
		return $this->extraScoreBuilders;
	}

	/**
	 * Add custom scoring function to the context.
	 * The rescore builder will pick it up.
	 * @param BoostFunctionBuilder $rescore
	 */
	public function addCustomRescoreComponent( BoostFunctionBuilder $rescore ) {
		$this->isDirty = true;
		$this->extraScoreBuilders[] = $rescore;
	}

	/**
	 * @param string $message i18n message key
	 * @param mixed ...$params
	 */
	public function addWarning( $message, ...$params ) {
		$this->isDirty = true;
		$this->warnings[] = array_filter( func_get_args(), function ( $v ) {
			return $v !== null;
		} );
	}

	/**
	 * @return array[] Array of arrays. Each sub array is a set of values
	 *  suitable for creating an i18n message.
	 */
	public function getWarnings() {
		return $this->warnings;
	}

	/**
	 * @return string the name of the fulltext query builder profile
	 */
	public function getFulltextQueryBuilderProfile() {
		if ( $this->fulltextQueryBuilderProfile === null ) {
			$this->fulltextQueryBuilderProfile = $this->config->getProfileService()
				->getProfileName( SearchProfileService::FT_QUERY_BUILDER, $this->profileContext );
		}
		return $this->fulltextQueryBuilderProfile;
	}

	/**
	 * @param string $profile set the name of the fulltext query builder profile
	 */
	public function setFulltextQueryBuilderProfile( $profile ) {
		$this->isDirty = true;
		$this->fulltextQueryBuilderProfile = $profile;
	}

	/**
	 * @param ResultsType $resultsType results type to return
	 */
	public function setResultsType( $resultsType ) {
		$this->resultsType = $resultsType;
	}

	/**
	 * @return ResultsType $resultsType results type to return
	 */
	public function getResultsType() {
		Assert::precondition( $this->resultsType !== null, "resultsType unset" );
		return $this->resultsType;
	}

	/**
	 * Get the list of extra indices to query.
	 * Generally needed to query externilized file index.
	 * Must be called only once the list of namespaces has been set.
	 *
	 * @return ExternalIndex[]
	 * @see OtherIndexesUpdater::getExtraIndexesForNamespaces()
	 */
	public function getExtraIndices() {
		if ( $this->getLimitSearchToLocalWiki() || !$this->getNamespaces() ) {
			return [];
		}
		return OtherIndexesUpdater::getExtraIndexesForNamespaces(
			$this->config,
			$this->getNamespaces()
		);
	}

	/**
	 * Get the phrase rescore query if available
	 * @return AbstractQuery|null
	 */
	public function getPhraseRescoreQuery() {
		return $this->phraseRescoreQuery;
	}

	/**
	 * Set the phrase rescore query
	 * @param AbstractQuery|null $phraseRescoreQuery
	 */
	public function setPhraseRescoreQuery( $phraseRescoreQuery ) {
		$this->phraseRescoreQuery = $phraseRescoreQuery;
		$this->isDirty = true;
	}

	/**
	 * Add aggregation to perform on search.
	 * @param AbstractAggregation $agg
	 */
	public function addAggregation( AbstractAggregation $agg ) {
		$this->aggs[] = $agg;
		$this->isDirty = true;
	}

	/**
	 * Get the list of aggregations.
	 * @return AbstractAggregation[]
	 */
	public function getAggregations() {
		return $this->aggs;
	}

	/**
	 * @return CirrusDebugOptions
	 */
	public function getDebugOptions() {
		return $this->debugOptions;
	}

	/**
	 * NOTE: public for testing purposes.
	 * @return AbstractQuery[]
	 */
	public function getFilters(): array {
		return $this->filters;
	}

	/**
	 * @param AbstractQuery $query
	 */
	public function must( AbstractQuery $query ) {
		$this->addFilter( $query );
	}

	/**
	 * @param AbstractQuery $query
	 */
	public function mustNot( AbstractQuery $query ) {
		$this->addNotFilter( $query );
	}

	/**
	 * Builds a SearchContext based on a SearchQuery.
	 *
	 * Helper function used for building blocks that still work on top
	 * of the SearchContext+queryString instead of SearchQuery.
	 *
	 * States initialized:
	 *    - limitSearchToLocalWiki
	 *  - suggestion
	 *  - custom rescoreProfile/fulltextQueryBuilderProfile
	 *  - contextual filters: (eg. SearchEngine::$prefix)
	 *  - SuggestPrefix (DYM prefix: ~ and/or namespace header)
	 *
	 * @param SearchQuery $query
	 * @param FallbackRunner|null $fallbackRunner
	 * @return SearchContext
	 */
	public static function fromSearchQuery( SearchQuery $query, FallbackRunner $fallbackRunner = null ): SearchContext {
		$searchContext = new SearchContext(
			$query->getSearchConfig(),
			$query->getNamespaces(),
			$query->getDebugOptions(),
			$fallbackRunner,
			new FetchPhaseConfigBuilder( $query->getSearchConfig(), $query->getSearchEngineEntryPoint() )
		);
		$searchContext->limitSearchToLocalWiki = !$query->getCrossSearchStrategy()->isExtraIndicesSearchSupported();

		$searchContext->rescoreProfile = $query->getForcedProfile( SearchProfileService::RESCORE );

		$profileContext = $query->getSearchConfig()
			->getProfileService()
			->getDispatchService()
			->bestRoute( $query )
			->getProfileContext();
		$searchContext->setProfileContext( $profileContext );
		$parsedQuery = $query->getParsedQuery();
		$basicQueryClasses = [
			BasicQueryClassifier::SIMPLE_BAG_OF_WORDS,
			BasicQueryClassifier::SIMPLE_PHRASE,
			BasicQueryClassifier::BAG_OF_WORDS_WITH_PHRASE,
			BasicQueryClassifier::COMPLEX_QUERY,
		];

		foreach ( $basicQueryClasses as $klass ) {
			if ( $parsedQuery->isQueryOfClass( $klass ) ) {
				$searchContext->syntaxUsed[$klass] = 1;
			}
		}
		// TODO: Clarify what happens when user forces a profile, should we disable the dispatch service?
		$searchContext->fulltextQueryBuilderProfile = $query->getForcedProfile( SearchProfileService::FT_QUERY_BUILDER );
		$searchContext->profileContextParams = $query->getProfileContextParameters();

		foreach ( $query->getContextualFilters() as $filter ) {
			$filter->populate( $searchContext );
		}
		$pQuery = $query->getParsedQuery();
		$searchContext->originalSearchTerm = $pQuery->getRawQuery();
		return $searchContext;
	}

	/**
	 * @return FallbackRunner
	 */
	public function getFallbackRunner(): FallbackRunner {
		return $this->fallbackRunner;
	}
}
