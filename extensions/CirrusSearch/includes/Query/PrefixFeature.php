<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\NamespacePrefixParser;
use CirrusSearch\Query\Builder\ContextualFilter;
use CirrusSearch\Query\Builder\FilterBuilder;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\Term;
use Wikimedia\Assert\Assert;

/**
 * Handles the prefix: keyword for matching titles. Can be used to
 * specify a namespace, a prefix of the title, or both. Note that
 * unlike other keyword features this greedily uses everything after
 * the prefix: keyword, so must be used at the end of the query. Also
 * note that this will override namespace filters previously applied
 * to the SearchContext.
 *
 * Examples:
 *   prefix:Calif
 *   prefix:Talk:
 *   prefix:Talk:Calif
 *   prefix:California Cou
 *   prefix:"California Cou"
 */
class PrefixFeature extends SimpleKeywordFeature implements FilterQueryFeature {
	/** @var string name of the keyword used in the syntax */
	const KEYWORD = 'prefix';

	/**
	 * key value to set in the array returned by KeywordFeature::parsedValue()
	 * to instruct the parser that additional namespaces are needed
	 * for the query to function properly.
	 * NOTE: a value of 'all' means that all namespaces are required
	 * are required.
	 * @see KeywordFeature::parsedValue()
	 */
	const PARSED_NAMESPACES = 'parsed_namespaces';

	/**
	 * @var NamespacePrefixParser
	 */
	private $namespacePrefixParser;

	public function __construct( NamespacePrefixParser $namespacePrefixParser = null ) {
		$this->namespacePrefixParser = $namespacePrefixParser ?: self::defaultNSPrefixParser();
	}

	private static function defaultNSPrefixParser(): NamespacePrefixParser {
		return new class() implements NamespacePrefixParser {
			public function parse( $query ) {
				return \SearchEngine::parseNamespacePrefixes( $query, true, false );
			}
		};
	}

	/**
	 * @return bool
	 */
	public function greedy() {
		return true;
	}

	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ self::KEYWORD ];
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy( KeywordFeatureNode $node ) {
		$parsedValue = $node->getParsedValue();
		$namespace = $parsedValue['namespace'] ?? null;
		if ( $namespace === null || $namespace <= NS_CATEGORY_TALK ) {
			// we allow crosssearches for "standard" namespaces
			return CrossSearchStrategy::allWikisStrategy();
		} else {
			return CrossSearchStrategy::hostWikiOnlyStrategy();
		}
	}

	/**
	 * @param SearchContext $context
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param bool $negated
	 * @return array
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$parsedValue = $this->parseValue( $key, $value, $quotedValue, '', '', $context );
		'@phan-var array $parsedValue';
		$namespace = $parsedValue['namespace'] ?? null;
		self::alterSearchContextNamespace( $context, $namespace );
		$prefixQuery = $this->buildQuery( $parsedValue['value'], $namespace );
		return [ $prefixQuery, false ];
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param string $valueDelimiter
	 * @param string $suffix
	 * @param WarningCollector $warningCollector
	 * @return array|false|null
	 */
	public function parseValue( $key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector ) {
		return $this->internalParseValue( $value );
	}

	/**
	 * Parse the value of the prefix keyword mainly to extract the namespace prefix
	 * @param string $value
	 * @return array|false|null
	 */
	private function internalParseValue( $value ) {
		$trimQuote = '/^"([^"]*)"\s*$/';
		$value = preg_replace( $trimQuote, "$1", $value );
		// NS_MAIN by default
		$namespaces = [ NS_MAIN ];

		// Suck namespaces out of $value. Note that this overrides provided
		// namespace filters.
		$queryAndNamespace = $this->namespacePrefixParser->parse( $value );
		if ( $queryAndNamespace !== false ) {
			// parseNamespacePrefixes returns the whole query if it's made of single namespace prefix
			$value = $value === $queryAndNamespace[0] ? '' : $queryAndNamespace[0];
			$namespaces = $queryAndNamespace[1];

			// Redo best effort quote trimming on the resulting value
			$value = preg_replace( $trimQuote, "$1", $value );
		}
		Assert::postcondition( $namespaces === null || count( $namespaces ) === 1,
			"namespace can only be an array with one value or null" );
		$value = trim( $value );
		// All titles in namespace
		if ( $value === '' ) {
			$value = null;
		}
		if ( $namespaces !== null ) {
			return [
				'namespace' => reset( $namespaces ),
				'value' => $value,
				self::PARSED_NAMESPACES => $namespaces,
			];
		} else {
			return [
				'value' => $value,
				self::PARSED_NAMESPACES => 'all',
			];
		}
	}

	/**
	 * @param string|null $value
	 * @param int|null $namespace
	 * @return AbstractQuery|null null in the case of prefix:all:
	 */
	private function buildQuery( $value = null, $namespace = null ) {
		$nsFilter = null;
		$prefixQuery = null;
		if ( $value !== null ) {
			$prefixQuery = new \Elastica\Query\MatchQuery();
			$prefixQuery->setFieldQuery( 'title.prefix', $value );
		}
		if ( $namespace !== null ) {
			$nsFilter = new Term( [ 'namespace' => $namespace ] );
		}
		if ( $prefixQuery !== null && $nsFilter !== null ) {
			$query = new BoolQuery();
			$query->addMust( $prefixQuery );
			$query->addMust( $nsFilter );
			return $query;
		}

		return $nsFilter ?? $prefixQuery;
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		return $this->buildQuery( $node->getParsedValue()['value'],
			$node->getParsedValue()['namespace'] ?? null );
	}

	/**
	 * Adds a prefix filter to the search context
	 * @param SearchContext $context
	 * @param string $prefix
	 * @param NamespacePrefixParser|null $namespacePrefixParser
	 */
	public static function prepareSearchContext( SearchContext $context, $prefix, NamespacePrefixParser $namespacePrefixParser = null ) {
		$filter = self::asContextualFilter( $prefix, $namespacePrefixParser );
		$filter->populate( $context );
		$namespaces = $filter->requiredNamespaces();
		Assert::postcondition( $namespaces !== null && count( $namespaces ) <= 1,
			'PrefixFeature must extract one or all namespaces' );
		self::alterSearchContextNamespace( $context,
			count( $namespaces ) === 1 ? reset( $namespaces ) : null );
	}

	/**
	 * Alter the set of namespaces in the SearchContext
	 * This is a special (and historic) behavior of the prefix keyword
	 * it has the ability to extend the list requested namespaces to the ones
	 * it wants to query.
	 *
	 * @param SearchContext $context
	 * @param int|null $namespace
	 */
	private static function alterSearchContextNamespace( SearchContext $context, $namespace ) {
		if ( $namespace === null && $context->getNamespaces() ) {
			$context->setNamespaces( null );
		} elseif ( $context->getNamespaces() &&
				   !in_array( $namespace, $context->getNamespaces() ) ) {
			$namespaces = $context->getNamespaces();
			$namespaces[] = $namespace;
			$context->setNamespaces( $namespaces );
		}
	}

	/**
	 * @param string $prefix
	 * @param NamespacePrefixParser|null $namespacePrefixParser
	 * @return ContextualFilter
	 */
	public static function asContextualFilter( $prefix, NamespacePrefixParser $namespacePrefixParser = null ) {
		$feature = new self( $namespacePrefixParser );
		$parsedValue = $feature->internalParseValue( $prefix );
		$namespace = $parsedValue['namespace'] ?? null;
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		$query = $feature->buildQuery( $parsedValue['value'], $namespace );
		return new class( $query, $namespace !== null ? [ $namespace ] : [] ) implements ContextualFilter {
			/**
			 * @var AbstractQuery
			 */
			private $query;

			/**
			 * @var int[]
			 */
			private $namespaces;

			public function __construct( $query, array $namespaces ) {
				$this->query = $query;
				$this->namespaces = $namespaces;
			}

			public function populate( FilterBuilder $filteringContext ) {
				$filteringContext->must( $this->query );
			}

			/**
			 * @return int[]|null
			 */
			public function requiredNamespaces() {
				return $this->namespaces;
			}
		};
	}
}
