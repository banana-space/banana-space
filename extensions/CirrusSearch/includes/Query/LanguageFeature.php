<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\Filters;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use Elastica\Query\AbstractQuery;

/**
 * Filters the result set based on pages labeled with the provided language.
 * More than one language can be specified with commas and they will be
 * generated as an OR query.
 *
 * Examples:
 *   inlanguage:en
 *   inlanguage:fr,en
 */
class LanguageFeature extends SimpleKeywordFeature implements FilterQueryFeature {
	/**
	 * Limit search to 20 languages. Arbitrarily chosen, but should be more
	 * than enough and some sort of limit has to be enforced.
	 */
	const QUERY_LIMIT = 20;

	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ 'inlanguage' ];
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy( KeywordFeatureNode $node ) {
		return CrossSearchStrategy::allWikisStrategy();
	}

	/**
	 * @param SearchContext $context
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param bool $negated Is the search negated? Not used to generate the returned AbstractQuery,
	 *  that will be negated as necessary. Used for any other building/context necessary.
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$parsedValue = $this->parseValue( $key, $value, $quotedValue, '', '', $context );
		return [ $this->doGetFilterQuery( $parsedValue ), false ];
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
		$langs = explode( ',', $value );
		if ( count( $langs ) > self::QUERY_LIMIT ) {
			$warningCollector->addWarning(
				'cirrussearch-feature-too-many-conditions',
				$key,
				self::QUERY_LIMIT
			);
			$langs = array_slice( $langs, 0, self::QUERY_LIMIT );
		}
		return [ 'langs' => $langs ];
	}

	/**
	 * @param array[] $parsedValue
	 * @return \Elastica\Query\AbstractQuery|\Elastica\Query\MatchQuery|null
	 */
	private function doGetFilterQuery( $parsedValue ) {
		$queries = [];
		foreach ( $parsedValue['langs'] as $lang ) {
			if ( strlen( trim( $lang ) ) > 0 ) {
				$query = new \Elastica\Query\MatchQuery();
				$query->setFieldQuery( 'language', $lang );
				$queries[] = $query;
			}
		}
		$query = Filters::booleanOr( $queries, false );

		return $query;
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		return $this->doGetFilterQuery( $node->getParsedValue() );
	}
}
