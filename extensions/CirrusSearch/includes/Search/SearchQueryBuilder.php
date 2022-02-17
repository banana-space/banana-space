<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\AST\ParsedQuery;
use CirrusSearch\Parser\NamespacePrefixParser;
use CirrusSearch\Parser\QueryParserFactory;
use CirrusSearch\Query\Builder\ContextualFilter;
use CirrusSearch\SearchConfig;
use Wikimedia\Assert\Assert;

/**
 * Builder for SearchQuery
 */
final class SearchQueryBuilder {

	/**
	 * @var ParsedQuery
	 */
	private $parsedQuery;

	/**
	 * @var int[]|null
	 */
	private $initialNamespaces;

	/**
	 * @var bool
	 */
	private $crossProjectSearch = false;

	/**
	 * @var bool
	 */
	private $crossLanguageSearch = false;

	/**
	 * @var bool
	 */
	private $extraIndicesSearch = false;

	/**
	 * @var ContextualFilter[]
	 */
	private $contextualFilters = [];

	/**
	 * @var string
	 */
	private $searchEngineEntryPoint;

	/**
	 * @var string
	 */
	private $sort;

	/**
	 * @var string[]
	 */
	private $forcedProfiles = [];

	/**
	 * @var int
	 */
	private $offset;

	/**
	 * @var int
	 */
	private $limit;

	/**
	 * @var CirrusDebugOptions
	 */
	private $debugOptions;

	/**
	 * @var SearchConfig
	 */
	private $searchConfig;

	/**
	 * @var bool
	 */
	private $withDYMSuggestion = false;

	/**
	 * @var bool
	 */
	private $allowRewrite = false;

	/**
	 * @var string[] parameters for the SearchProfileService
	 * @see \CirrusSearch\Profile\ContextualProfileOverride
	 */
	private $profileContextParameters = [];

	/**
	 * Construct a new FT (FullText) SearchQueryBuilder using the config
	 * and query string provided.
	 *
	 * NOTE: this method will parse the query string and set all builder attributes
	 * to Fulltext search defaults.
	 *
	 * @param SearchConfig $config
	 * @param string $queryString
	 * @param NamespacePrefixParser $namespacePrefixParser
	 * @return SearchQueryBuilder
	 * @throws \CirrusSearch\Parser\QueryStringRegex\SearchQueryParseException
	 */
	public static function newFTSearchQueryBuilder(
		SearchConfig $config,
		$queryString,
		NamespacePrefixParser $namespacePrefixParser
	): SearchQueryBuilder {
		$builder = new self();
		$builder->parsedQuery = QueryParserFactory::newFullTextQueryParser( $config, $namespacePrefixParser )->parse( $queryString );
		$builder->initialNamespaces = [ NS_MAIN ];
		$builder->sort = \SearchEngine::DEFAULT_SORT;
		$builder->debugOptions = CirrusDebugOptions::defaultOptions();
		$builder->limit = 10;
		$builder->offset = 0;
		$builder->searchConfig = $config;
		$builder->forcedProfiles = [];
		$builder->searchEngineEntryPoint = SearchQuery::SEARCH_TEXT;
		$builder->crossProjectSearch = true;
		$builder->crossLanguageSearch = true;
		$builder->extraIndicesSearch = true;
		$builder->withDYMSuggestion = true;
		$builder->allowRewrite = false;
		return $builder;
	}

	/**
	 * Recreate a SearchQueryBuilder using an existing query and the target wiki SearchConfig.
	 *
	 * @param SearchConfig $config
	 * @param SearchQuery $query
	 * @return SearchQueryBuilder
	 */
	public static function forCrossProjectSearch( SearchConfig $config, SearchQuery $query ): SearchQueryBuilder {
		Assert::parameter( !$config->isLocalWiki(), '$config', 'must not be the local wiki config' );
		Assert::precondition( $query->getCrossSearchStrategy()->isCrossProjectSearchSupported(),
			'Trying to build a query for a cross-project search but the original query does not ' .
			'support such searches.' );

		$builder = self::copyQueryForCrossSearch( $config, $query );
		$builder->offset = 0;
		$builder->limit = $query->getSearchConfig()->get( 'CirrusSearchNumCrossProjectSearchResults' );
		return $builder;
	}

	/**
	 * @param SearchConfig $config
	 * @param SearchQuery $original
	 * @return SearchQueryBuilder
	 */
	private static function copyQueryForCrossSearch( SearchConfig $config, SearchQuery $original ): SearchQueryBuilder {
		Assert::precondition( $original->getContextualFilters() === [], 'The initial must not have contextual filters' );
		$builder = new self();
		$builder->parsedQuery = $original->getParsedQuery();
		$builder->searchEngineEntryPoint = $original->getSearchEngineEntryPoint();

		// Only allow core namespaces. We can't be sure any others exist
		// TODO: possibly move this later and try to detect if we run the default
		// profile, so that we could try to run the default profile on sister wikis
		$namespaces = $original->getInitialNamespaces();
		if ( $namespaces !== null ) {
			$namespaces = array_filter( $namespaces, function ( $namespace ) {
				return $namespace <= NS_CATEGORY_TALK;
			} );
		}

		$builder->initialNamespaces = $namespaces;
		$builder->sort = $original->getSort();
		$builder->debugOptions = $original->getDebugOptions();
		$builder->searchConfig = $config;
		$builder->profileContextParameters = $original->getProfileContextParameters();

		$forcedProfiles = [];

		// Copy forced profiles only if they exist on the target wiki.
		foreach ( $original->getForcedProfiles() as $type => $name ) {
			if ( $config->getProfileService()->hasProfile( $type, $name ) ) {
				$forcedProfiles[$type] = $name;
			}
		}

		$builder->forcedProfiles = $forcedProfiles;
		// We force to false, during cross project/lang searches
		// and we explicitely disable DYM suggestions
		$builder->crossProjectSearch = false;
		$builder->crossLanguageSearch = false;
		$builder->extraIndicesSearch = false;
		$builder->withDYMSuggestion = false;
		$builder->allowRewrite = false;
		return $builder;
	}

	/**
	 * @param SearchConfig $config
	 * @param SearchQuery $original
	 * @return SearchQueryBuilder
	 */
	public static function forCrossLanguageSearch( SearchConfig $config, SearchQuery $original ) {
		Assert::parameter( !$config->isLocalWiki(), '$config', 'must not be the local wiki config' );
		Assert::precondition( $original->getCrossSearchStrategy()->isCrossLanguageSearchSupported(),
			'Trying to build a query for a cross-language search but the original query does not ' .
			'support such searches.' );

		$builder = self::copyQueryForCrossSearch( $config, $original );
		$builder->offset = $original->getOffset();
		$builder->limit = $original->getLimit();
		return $builder;
	}

	/**
	 * @param SearchQuery $original
	 * @param string $term
	 * @param NamespacePrefixParser $namespacePrefixParser
	 * @return SearchQueryBuilder
	 * @throws \CirrusSearch\Parser\QueryStringRegex\SearchQueryParseException
	 */
	public static function forRewrittenQuery(
		SearchQuery $original,
		$term,
		NamespacePrefixParser $namespacePrefixParser
	): SearchQueryBuilder {
		Assert::precondition( $original->isAllowRewrite(), 'The original query must allow rewrites' );
		// Hack to prevent a second pass on this cleaning algo because its destructive
		$config = new HashSearchConfig( [ 'CirrusSearchStripQuestionMarks' => 'no' ],
			[ HashSearchConfig::FLAG_INHERIT ], $original->getSearchConfig() );

		$builder = self::newFTSearchQueryBuilder( $config, $term, $namespacePrefixParser );
		$builder->contextualFilters = $original->getContextualFilters();
		$builder->forcedProfiles = $original->getForcedProfiles();
		$builder->initialNamespaces = $original->getInitialNamespaces();
		$builder->sort = $original->getSort();
		$builder->debugOptions = $original->getDebugOptions();
		$builder->limit = $original->getLimit();
		$builder->offset = $original->getOffset();
		$builder->crossProjectSearch = false;
		$builder->crossLanguageSearch = false;
		$builder->extraIndicesSearch = $original->getInitialCrossSearchStrategy()->isExtraIndicesSearchSupported();
		$builder->withDYMSuggestion = false;
		$builder->allowRewrite = false;
		return $builder;
	}

	/**
	 * @return SearchQuery
	 */
	public function build(): SearchQuery {
		return new SearchQuery(
			$this->parsedQuery,
			$this->initialNamespaces,
			new CrossSearchStrategy(
				$this->crossProjectSearch && $this->searchConfig->isCrossProjectSearchEnabled(),
				$this->crossLanguageSearch && $this->searchConfig->isCrossLanguageSearchEnabled(),
				$this->extraIndicesSearch
			),
			$this->contextualFilters,
			$this->searchEngineEntryPoint,
			$this->sort,
			$this->forcedProfiles,
			$this->offset,
			$this->limit,
			$this->debugOptions ?? CirrusDebugOptions::defaultOptions(),
			$this->searchConfig,
			$this->withDYMSuggestion,
			$this->allowRewrite,
			$this->profileContextParameters
		);
	}

	/**
	 * @param string $name
	 * @param ContextualFilter $filter
	 * @return SearchQueryBuilder
	 */
	public function addContextualFilter( $name, ContextualFilter $filter ): SearchQueryBuilder {
		Assert::parameter( !array_key_exists( $name, $this->contextualFilters ),
			'$name', "context filter $name already set" );
		$this->contextualFilters[$name] = $filter;
		return $this;
	}

	/**
	 * @param int[] $initialNamespaces
	 * @return SearchQueryBuilder
	 */
	public function setInitialNamespaces( array $initialNamespaces ): SearchQueryBuilder {
		$this->initialNamespaces = $initialNamespaces;

		return $this;
	}

	/**
	 * @param bool $crossProjectSearch
	 * @return SearchQueryBuilder
	 */
	public function setCrossProjectSearch( $crossProjectSearch ): SearchQueryBuilder {
		$this->crossProjectSearch = $crossProjectSearch;

		return $this;
	}

	/**
	 * @param bool $crossLanguageSearch
	 * @return SearchQueryBuilder
	 */
	public function setCrossLanguageSearch( $crossLanguageSearch ): SearchQueryBuilder {
		$this->crossLanguageSearch = $crossLanguageSearch;

		return $this;
	}

	/**
	 * @param string $searchEngineEntryPoint
	 * @return SearchQueryBuilder
	 */
	public function setSearchEngineEntryPoint( $searchEngineEntryPoint ): SearchQueryBuilder {
		$this->searchEngineEntryPoint = $searchEngineEntryPoint;

		return $this;
	}

	/**
	 * @param string $sort
	 * @return SearchQueryBuilder
	 */
	public function setSort( $sort ): SearchQueryBuilder {
		$this->sort = $sort;

		return $this;
	}

	/**
	 * @param int $offset
	 * @return SearchQueryBuilder
	 */
	public function setOffset( $offset ): SearchQueryBuilder {
		$this->offset = $offset;

		return $this;
	}

	/**
	 * @param int $limit
	 * @return SearchQueryBuilder
	 */
	public function setLimit( $limit ): SearchQueryBuilder {
		$this->limit = $limit;

		return $this;
	}

	/**
	 * @param CirrusDebugOptions $debugOptions
	 * @return SearchQueryBuilder
	 */
	public function setDebugOptions( CirrusDebugOptions $debugOptions ): SearchQueryBuilder {
		$this->debugOptions = $debugOptions;

		return $this;
	}

	/**
	 * @param bool $withDYMSuggestion
	 * @return SearchQueryBuilder
	 */
	public function setWithDYMSuggestion( $withDYMSuggestion ): SearchQueryBuilder {
		$this->withDYMSuggestion = $withDYMSuggestion;

		return $this;
	}

	/**
	 * @param bool $extraIndicesSearch
	 * @return SearchQueryBuilder
	 */
	public function setExtraIndicesSearch( $extraIndicesSearch ): SearchQueryBuilder {
		$this->extraIndicesSearch = $extraIndicesSearch;

		return $this;
	}

	/**
	 * @param string $type
	 * @param string $forcedProfile
	 * @return SearchQueryBuilder
	 */
	public function addForcedProfile( $type, $forcedProfile ): SearchQueryBuilder {
		$this->forcedProfiles[$type] = $forcedProfile;
		return $this;
	}

	/**
	 * @param bool $allowRewrite
	 * @return SearchQueryBuilder
	 */
	public function setAllowRewrite( $allowRewrite ): SearchQueryBuilder {
		$this->allowRewrite = $allowRewrite;

		return $this;
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return SearchQueryBuilder
	 * @see \CirrusSearch\Profile\ContextualProfileOverride
	 */
	public function addProfileContextParameter( $key, $value ): SearchQueryBuilder {
		$this->profileContextParameters[$key] = $value;
		return $this;
	}
}
