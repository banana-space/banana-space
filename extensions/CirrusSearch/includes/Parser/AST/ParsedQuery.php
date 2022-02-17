<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\Visitor\KeywordNodeVisitor;
use CirrusSearch\Parser\ParsedQueryClassifiersRepository;
use Wikimedia\Assert\Assert;

/**
 * Parsed query
 */
class ParsedQuery {

	/**
	 * markup to indicate that the query was cleaned up
	 * detecting a double quote used as a gershayim
	 * see T66350
	 */
	const CLEANUP_GERSHAYIM_QUIRKS = 'gershayim_quirks';

	/**
	 * markup to indicate that the had some question marks
	 * stripped
	 * @see \CirrusSearch\Util::stripQuestionMarks
	 */
	const CLEANUP_QMARK_STRIPPING = 'stripped_qmark';

	/**
	 * markup to indicate that we removed a '~' at the beginning of the query
	 */
	const TILDE_HEADER = 'tilde_header';

	/**
	 * @var ParsedNode
	 */
	private $root;

	/**
	 * @var string
	 */
	private $query;

	/**
	 * @var string
	 */
	private $rawQuery;

	/**
	 * @var bool[] indexed by cleanup type
	 */
	private $queryCleanups;

	/**
	 * @var ParseWarning[]
	 */
	private $parseWarnings;

	/**
	 * @var NamespaceHeaderNode|null namespace specified at the beginning of the query
	 */
	private $namespaceHeader;

	/**
	 * @var array|string (array of int or 'all') list of required namespaces
	 * for the query to be able to return results.
	 * This list of namespace must always be added no matter what is requested
	 * before.
	 * Main use-case is the prefix keyword that must supersede any other settings.
	 */
	private $requiredNamespaces;

	/**
	 * @var CrossSearchStrategy|null (lazy loaded)
	 */
	private $crossSearchStrategy;

	/**
	 * @var ParsedQueryClassifiersRepository
	 */
	private $classifierRepository;

	/**
	 * @var bool[] indexed by query class name
	 */
	private $queryClassCache = [];

	/**
	 * @var string[] list of used features in the query
	 * @see \CirrusSearch\Query\KeywordFeature::getFeatureName()
	 */
	private $featuresUsed;

	/**
	 * @param ParsedNode $root
	 * @param string $query cleaned up query string
	 * @param string $rawQuery original query as received by the search engine
	 * @param bool[] $queryCleanups indexed by cleanup type (non-empty when $query !== $rawQuery)
	 * @param ?NamespaceHeaderNode $namespaceHeader namespace found as a "header" of the query
	 *        is a int when a namespace id is provided, string with 'all' or null if none specified
	 * @param array|string $requiredNamespaces
	 * @param ParseWarning[] $parseWarnings list of warnings detected during parsing
	 * @param ParsedQueryClassifiersRepository $repository
	 */
	public function __construct(
		ParsedNode $root,
		$query,
		$rawQuery,
		$queryCleanups,
		?NamespaceHeaderNode $namespaceHeader,
		$requiredNamespaces,
		array $parseWarnings,
		ParsedQueryClassifiersRepository $repository
	) {
		$this->root = $root;
		$this->query = $query;
		$this->rawQuery = $rawQuery;
		$this->queryCleanups = $queryCleanups;
		$this->parseWarnings = $parseWarnings;
		$this->namespaceHeader = $namespaceHeader;
		Assert::parameter( is_array( $requiredNamespaces ) || $requiredNamespaces === 'all',
			'$requiredNamespaces', 'must be an array or "all"' );
		$this->requiredNamespaces = $requiredNamespaces;
		$this->classifierRepository = $repository;
	}

	/**
	 * @return ParsedNode
	 */
	public function getRoot() {
		return $this->root;
	}

	/**
	 * The query being parsed
	 * Some cleanups may have been made to the raw query
	 * NOTE: the query may include the namespace header
	 * @return string
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * The query string without the namespace header
	 * @return string
	 */
	public function getQueryWithoutNsHeader() {
		// TODO: remove once the AST is fully used by all building components.
		if ( $this->namespaceHeader !== null ) {
			return substr( $this->query, $this->namespaceHeader->getEndOffset() );
		}
		return $this->query;
	}

	/**
	 * The raw query as received by the search engine
	 * @return string
	 */
	public function getRawQuery() {
		return $this->rawQuery;
	}

	/**
	 * Check if the query was cleanup with this type
	 * @see ParsedQuery::CLEANUP_QMARK_STRIPPING
	 * @see ParsedQuery::CLEANUP_GERSHAYIM_QUIRKS
	 * @param string $cleanup
	 * @return bool
	 */
	public function hasCleanup( $cleanup ) {
		return isset( $this->queryCleanups[$cleanup] );
	}

	/**
	 * List of warnings detected at parse time
	 * @return ParseWarning[]
	 */
	public function getParseWarnings() {
		return $this->parseWarnings;
	}

	/**
	 * Get the node of the namespace header identified in the prefix of the query
	 * if specified.
	 * It can be null in all other cases
	 * @return NamespaceHeaderNode|null
	 */
	public function getNamespaceHeader() {
		return $this->namespaceHeader;
	}

	/**
	 * @return array|string array of additional namespaces or 'all' if all namespaces required
	 */
	public function getRequiredNamespaces() {
		return $this->requiredNamespaces;
	}

	/**
	 * Determine the actual namespaces required for this query to run
	 * assuming that $namespaces is the list of namespaces initially requested
	 * usually set <code>\SearchEngine::setNamespaces()</code>.
	 *
	 * @param int[]|null $namespaces initial namespaces
	 * @param int[]|null $additionalRequiredNamespaces additional namespaces required (by ContextualFilters)
	 * @return int[] the list of namespaces that have to be queried,
	 * empty array means all namespaces
	 * @see \SearchEngine::setNamespaces()
	 * @see self::getRequiredNamespaces()
	 * @see self::getNamespaceHeader()
	 * @see \CirrusSearch\Query\Builder\ContextualFilter::requiredNamespaces()
	 */
	public function getActualNamespaces( array $namespaces = null, array $additionalRequiredNamespaces = null ) {
		if ( $this->requiredNamespaces === 'all' ) {
			// e.g. prefix:all:foo (all namespaces must be queried no matter what is requested before
			return [];
		}

		if ( $additionalRequiredNamespaces === [] ) {
			return [];
		}

		if ( $this->namespaceHeader !== null && $this->namespaceHeader->getNamespace() === 'all' ) {
			// e.g. all:foo
			return [];
		}

		if ( $this->namespaceHeader === null && !$namespaces ) {
			// Everything was selected using SearchEngine::setNamespaces() but nothing more specific
			// was requested using a prefixed ns
			return [];
		}

		// now everything else will be an explicit list of namespaces
		Assert::postcondition( $this->namespaceHeader === null || is_int( $this->namespaceHeader->getNamespace() ),
			'$this->namespaceHeader must be null or an integer' );

		$ns = $this->namespaceHeader === null ? $namespaces : [ $this->namespaceHeader->getNamespace() ];
		Assert::postcondition( is_array( $ns ) && $ns !== [],
			'at this point we must have a list of specific namespaces' );

		return array_values( array_unique(
			array_merge( $ns, $this->requiredNamespaces, $additionalRequiredNamespaces ?? [] ),
			SORT_REGULAR
		) );
	}

	/**
	 * Get the cross search strategy supported by this query.
	 *
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy() {
		if ( $this->crossSearchStrategy === null ) {
			$visitor = new class() extends KeywordNodeVisitor {
				public $strategy;

				public function __construct( array $excludeOccurs = [], array $keywordClasses = [] ) {
					parent::__construct( $excludeOccurs, $keywordClasses );
					$this->strategy = CrossSearchStrategy::allWikisStrategy();
				}

				/**
				 * @param KeywordFeatureNode $node
				 */
				public function doVisitKeyword( KeywordFeatureNode $node ) {
					$this->strategy = $this->strategy
						->intersect( $node->getKeyword()->getCrossSearchStrategy( $node ) );
				}
			};
			$this->root->accept( $visitor );
			$this->crossSearchStrategy = $visitor->strategy;
		}
		return $this->crossSearchStrategy;
	}

	/**
	 * @param string $class
	 * @return bool
	 * @throws \CirrusSearch\Parser\ParsedQueryClassifierException if the class is unknown
	 */
	public function isQueryOfClass( $class ) {
		return $this->queryClassCache[$class] ?? $this->loadQueryClass( $class );
	}

	/**
	 * @param string $class
	 * @return bool
	 * @throws \CirrusSearch\Parser\ParsedQueryClassifierException
	 */
	private function loadQueryClass( $class ) {
		$classifier = $this->classifierRepository->getClassifier( $class );
		$newClasses = $classifier->classify( $this );
		foreach ( $classifier->classes() as $k ) {
			$this->queryClassCache[$k] = in_array( $k, $newClasses, true );
		}
		return $this->queryClassCache[$class];
	}

	/**
	 * Preload all known query classes and classify this
	 * query.
	 * @throws \CirrusSearch\Parser\ParsedQueryClassifierException
	 */
	public function preloadQueryClasses() {
		foreach ( $this->classifierRepository->getKnownClassifiers() as $class ) {
			$this->isQueryOfClass( $class );
		}
	}

	/**
	 * Get the list of keyword features used by this query.
	 * @see \CirrusSearch\Query\KeywordFeature::getFeatureName()
	 * @return string[]
	 */
	public function getFeaturesUsed() {
		if ( $this->featuresUsed === null ) {
			$visitor = new class() extends KeywordNodeVisitor {
				public $features = [];

				/**
				 * @param KeywordFeatureNode $node
				 */
				public function doVisitKeyword( KeywordFeatureNode $node ) {
					$name = $node->getKeyword()
						->getFeatureName( $node->getKey(), $node->getDelimiter() );
					$this->features[$name] = true;
				}
			};
			$this->root->accept( $visitor );
			$this->featuresUsed = array_keys( $visitor->features );
			if ( $this->namespaceHeader ) {
				$this->featuresUsed[] = 'namespace_header';
			}
		}
		return $this->featuresUsed;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		$ar = [
			'query' => $this->query,
			'rawQuery' => $this->rawQuery
		];

		if ( $this->namespaceHeader !== null ) {
			$ar += $this->namespaceHeader->toArray();
		}
		if ( $this->requiredNamespaces !== [] ) {
			$ar['requiredNamespaces'] = $this->requiredNamespaces;
		}
		if ( $this->queryCleanups !== [] ) {
			$ar['queryCleanups'] = $this->queryCleanups;
		}
		$this->preloadQueryClasses();
		$classes = array_keys( array_filter( $this->queryClassCache ) );
		if ( $classes !== [] ) {
			$ar['queryClassCache'] = $classes;
		}
		if ( $this->parseWarnings !== [] ) {
			$ar['warnings'] = array_map( function ( ParseWarning $w ) {
				return $w->toArray();
			}, $this->parseWarnings );
		}
		if ( $this->getFeaturesUsed() !== [] ) {
			$ar['featuresUsed'] = $this->getFeaturesUsed();
		}
		$ar['root'] = $this->getRoot()->toArray();

		return $ar;
	}
}
