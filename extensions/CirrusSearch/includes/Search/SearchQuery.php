<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\ParsedQuery;
use CirrusSearch\SearchConfig;

/**
 * A search query, it contains all the necessary information to build and send a query to the backend.
 * NOTE: Immutable value class.
 */
class SearchQuery {
	/**
	 * Identifier for the fulltext SearchEngine entry point
	 * @see \SearchEngine::searchText()
	 */
	const SEARCH_TEXT = 'searchText';

	/**
	 * Identifier for the near match SearchEngine entry point
	 * @see \SearchEngine::getNearMatcher()
	 */
	const NEAR_MATCH = 'nearMatch';

	/**
	 * Identifier for the completionSearch SearchEngine entry point
	 * @see \SearchEngine::completionSearch() and related endpoints
	 */
	const COMPLETION_SEARCH = 'completionSearch';

	/**
	 * @var ParsedQuery
	 */
	private $parsedQuery;

	/**
	 * @var int[]
	 */
	private $initialNamespaces;

	/**
	 * @var CrossSearchStrategy
	 */
	private $initialCrossSearchStrategy;

	/**
	 * @var CrossSearchStrategy|null (lazy loaded)
	 */
	private $crossSearchStrategy;

	/**
	 * @var \CirrusSearch\Query\Builder\ContextualFilter[]
	 */
	private $contextualFilters;

	/**
	 * Entry point from the SearchEngine
	 * TODO: clarify its usage and see whether or not another
	 * entry point var is needed to carry some provenance information
	 * from the UI.
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
	private $forcedProfiles;

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
	private $withDYMSuggestion;

	/**
	 * @var bool
	 */
	private $allowRewrite;

	/**
	 * @var string[] parameters for SearchProfileService
	 * @see \CirrusSearch\Profile\ContextualProfileOverride
	 */
	private $profileContextParameters;

	/**
	 * @param ParsedQuery $parsedQuery
	 * @param int[] $initialNamespaces
	 * @param CrossSearchStrategy $initialCrosswikiStrategy
	 * @param \CirrusSearch\Query\Builder\ContextualFilter[] $contextualFilters
	 * @param string $searchEngineEntryPoint
	 * @param string $sort
	 * @param string[] $forcedProfiles
	 * @param int $offset
	 * @param int $limit
	 * @param CirrusDebugOptions $debugOptions
	 * @param SearchConfig $searchConfig
	 * @param bool $withDYMSuggestion
	 * @param bool $allowRewrite
	 * @param string[] $profileContextParameters
	 * @see SearchQueryBuilder
	 */
	public function __construct(
		ParsedQuery $parsedQuery,
		array $initialNamespaces,
		CrossSearchStrategy $initialCrosswikiStrategy,
		array $contextualFilters,
		$searchEngineEntryPoint,
		$sort,
		array $forcedProfiles,
		$offset,
		$limit,
		CirrusDebugOptions $debugOptions,
		SearchConfig $searchConfig,
		$withDYMSuggestion,
		$allowRewrite,
		array $profileContextParameters
	) {
		$this->parsedQuery = $parsedQuery;
		$this->initialNamespaces = $initialNamespaces;
		$this->initialCrossSearchStrategy = $initialCrosswikiStrategy;
		$this->contextualFilters = $contextualFilters;
		$this->searchEngineEntryPoint = $searchEngineEntryPoint;
		$this->sort = $sort;
		$this->forcedProfiles = $forcedProfiles;
		$this->offset = $offset;
		$this->limit = $limit;
		$this->debugOptions = $debugOptions;
		$this->searchConfig = $searchConfig;
		$this->withDYMSuggestion = $withDYMSuggestion;
		$this->allowRewrite = $allowRewrite;
		$this->profileContextParameters = $profileContextParameters;
	}

	/**
	 * @return CirrusDebugOptions
	 */
	public function getDebugOptions(): CirrusDebugOptions {
		return $this->debugOptions;
	}

	/**
	 * @return ParsedQuery
	 */
	public function getParsedQuery(): ParsedQuery {
		return $this->parsedQuery;
	}

	/**
	 * @return int[]
	 */
	public function getInitialNamespaces() {
		return $this->initialNamespaces;
	}

	/**
	 * @return CrossSearchStrategy
	 */
	public function getInitialCrossSearchStrategy(): CrossSearchStrategy {
		return $this->initialCrossSearchStrategy;
	}

	/**
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy(): CrossSearchStrategy {
		if ( $this->crossSearchStrategy === null ) {
			if ( $this->contextualFilters !== [] ) {
				$this->crossSearchStrategy = CrossSearchStrategy::hostWikiOnlyStrategy();
			} else {
				$this->crossSearchStrategy = $this->parsedQuery
					->getCrossSearchStrategy()
					->intersect( $this->initialCrossSearchStrategy );
			}
		}
		return $this->crossSearchStrategy;
	}

	/**
	 * @return \CirrusSearch\Query\Builder\ContextualFilter[]
	 */
	public function getContextualFilters(): array {
		return $this->contextualFilters;
	}

	/**
	 * From which SearchEngine method this query entered CirrusSearch
	 * @return string
	 */
	public function getSearchEngineEntryPoint() {
		return $this->searchEngineEntryPoint;
	}

	/**
	 * @return string
	 */
	public function getSort() {
		return $this->sort;
	}

	/**
	 * @return string[]
	 */
	public function getForcedProfiles(): array {
		return $this->forcedProfiles;
	}

	/**
	 * @return int
	 */
	public function getOffset() {
		return $this->offset;
	}

	/**
	 * @return int
	 */
	public function getLimit() {
		return $this->limit;
	}

	/**
	 * List of namespaces required to run this query.
	 *
	 * @return int[] list of namespaces, empty array means that all namespaces
	 * are required.
	 */
	public function getNamespaces(): array {
		$additionalRequired = null;
		if ( $this->initialNamespaces != [] && $this->contextualFilters != [] ) {
			foreach ( $this->contextualFilters as $filter ) {
				$additional = $filter->requiredNamespaces();
				if ( $additional === null ) {
					continue;
				}
				if ( $additional === [] ) {
					$additionalRequired = [];
					break;
				}
				if ( $additionalRequired === null ) {
					$additionalRequired = $additional;
				} else {
					$additionalRequired = array_merge( $additionalRequired, $additional );
				}
			}
			if ( $additionalRequired !== null ) {
				$additionalRequired = array_unique( $additionalRequired );
			}
		}
		return $this->parsedQuery->getActualNamespaces( $this->initialNamespaces, $additionalRequired );
	}

	/**
	 * @return SearchConfig
	 */
	public function getSearchConfig(): SearchConfig {
		return $this->searchConfig;
	}

	/**
	 * @param string $profileType
	 * @see \CirrusSearch\Profile\SearchProfileService
	 * @return string|null name of the profile or null if nothing forced for this type
	 */
	public function getForcedProfile( $profileType ) {
		return $this->forcedProfiles[$profileType] ?? null;
	}

	/**
	 * @return bool
	 */
	public function hasForcedProfile() {
		return $this->forcedProfiles !== [];
	}

	/**
	 * @return bool
	 */
	public function isWithDYMSuggestion() {
		return $this->withDYMSuggestion;
	}

	/**
	 * @return bool
	 */
	public function isAllowRewrite() {
		return $this->allowRewrite;
	}

	/**
	 * @return string[]
	 * @see \CirrusSearch\Profile\ContextualProfileOverride
	 */
	public function getProfileContextParameters() {
		return $this->profileContextParameters;
	}
}
