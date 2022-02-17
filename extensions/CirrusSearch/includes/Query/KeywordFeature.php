<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use CirrusSearch\WarningCollector;

/**
 * Definition of a search keyword.
 *
 * This interface is being actively refactored, the initial behavior is to do all the
 * work in the function apply( SearchContext $context, $term ).
 *
 * The aim is to clearly separate the parsing logic from the query building logic.
 *
 *  - AST generation and parsing: must be idempotent and depend as little as possible on
 *    configuration variables. Output of the parsing will be KeywordFeatureNode.
 *  - CrossSearchStrategy evaluation
 *  - Expansion: for keyword needing to fetch external resources.
 *  - Query building
 *
 * The parsing behavior can be defined using the following methods:
 *  - getKeywordPrefixes()
 *  - allowEmptyValue()
 *  - hasValue()
 *  - greedy()
 *  - queryHeader()
 *  - getValueDelimiters()
 *  - parseValue()
 *
 * The keyword can define its CrossSearchStrategy to decide whether or not a query
 * using this keyword can be applied to external wikis indices.
 *
 * For keywords that need to fetch data from external resource the method
 * expand( KeywordFeatureNode $node, SearchConfig $config, WarningCollector $warningCollector )
 * can be used. Its return value will be made in a context available during query building.
 *
 * A keyword must not directly implement this interface but extends SimpleKeywordFeature.
 *
 * NOTE: since this interface is being refactored it's highly recommended to use and implement
 * the dedicated method in the old all-in-one apply strategy (This "apply" strategy will be removed).
 *
 * @see SimpleKeywordFeature
 * @see CrossSearchStrategy
 * @see KeywordFeatureNode
 */
interface KeywordFeature {
	/**
	 * List of keyword strings this implementation consumes
	 * @return string[]
	 */
	public function getKeywordPrefixes();

	/**
	 * Whether this keyword allows empty value.
	 * @return bool true to allow the keyword to appear in an empty form
	 */
	public function allowEmptyValue();

	/**
	 * Whether this keyword can have a value
	 * @return bool
	 */
	public function hasValue();

	/**
	 * Whether this keyword is greedy consuming the rest of the string.
	 * NOTE: do not use, greedy keywords will eventually be removed in the future
	 * @return bool
	 */
	public function greedy();

	/**
	 * Whether this keyword can appear only at the beginning of the query
	 * (excluding spaces)
	 * @return bool
	 */
	public function queryHeader();

	/**
	 * Determine the name of the feature being set in SearchContext::addSyntaxUsed
	 * Defaults to $key
	 *
	 * @param string $key
	 * @param string $valueDelimiter the delimiter used to wrap the value
	 * @return string
	 *  '"' when parsing keyword:"test"
	 *  '' when parsing keyword:test
	 */
	public function getFeatureName( $key, $valueDelimiter );

	/**
	 * List of value delimiters supported (must be an array of single byte char)
	 * @return string[][] list of delimiters options
	 */
	public function getValueDelimiters();

	/**
	 * Parse the value of the keyword.
	 * NOTE: this function called prior to creating the node in the AST.
	 * It is not allowed to call external resources here (db, elastic, others).
	 * The data known by this method should only be the value contained in the user query string
	 * and maybe few config vars for sanity check purposes.
	 *
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped and escaped
	 *  quotes un-escaped.
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param string $valueDelimiter the delimiter char used to wrap the keyword value ('"' in intitle:"test")
	 * @param string $suffix the optional suffix used after the value ('i' in insource:/regex/i)
	 * @param WarningCollector $warningCollector
	 * @return array|null|false an array kept containing the information parsed,
	 * 	null when nothing is to be kept
	 * 	false when the value is refused (only allowed for keywords that allows empty value)
	 * @see self::allowEmptyValue
	 */
	public function parseValue( $key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector );

	/**
	 * Get support strategy for cross searching
	 * @param KeywordFeatureNode $node
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy( KeywordFeatureNode $node );

	/**
	 * Expand the keyword potentially accessing external resources.
	 * Keywords that need to access the DB or any other external resources
	 * should implement this method.
	 * NOTE: this method will be called on every external wikis the search
	 * request will be made to.
	 * @param KeywordFeatureNode $node
	 * @param SearchConfig $config
	 * @param WarningCollector $warningCollector
	 * @return array a state containing the data the keyword fetched from an external resource
	 * The format of this array is only known by the keyword implementation and is stored in
	 * the query building context.
	 */
	public function expand( KeywordFeatureNode $node, SearchConfig $config, WarningCollector $warningCollector );

	/**
	 * Checks $term for usage of the feature, and applies necessary filters,
	 * rescores, etc. to the provided $context. The returned $term will be
	 * passed on to other keyword features, and eventually to an elasticsearch
	 * QueryString query.
	 *
	 * @param SearchContext $context
	 * @param string $term The input search query
	 * @return string The remaining search query after processing
	 */
	public function apply( SearchContext $context, $term );
}
