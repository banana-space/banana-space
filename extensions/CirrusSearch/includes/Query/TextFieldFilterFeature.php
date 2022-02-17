<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\SearchContext;
use Elastica\Query;
use Elastica\Query\AbstractQuery;

/**
 * Feature for filtering on specific text fields. Supports plain AND matching
 * by default, and phrase matching when quoted.
 */
class TextFieldFilterFeature extends SimpleKeywordFeature implements FilterQueryFeature {

	/** @var string Full text keyword to register */
	private $keyword;

	/** @var string Elasticsearch field to filter against */
	private $field;

	/**
	 * @param string $keyword Full text keyword to register
	 * @param string $field Elasticsearch field to filter against
	 */
	public function __construct( $keyword, $field ) {
		$this->keyword = $keyword;
		$this->field = $field;
	}

	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ $this->keyword ];
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
	 * @param string $quotedValue The original value in the search string, including quotes
	 *     if used
	 * @param bool $negated Is the search negated? Not used to generate the returned
	 *     AbstractQuery, that will be negated as necessary. Used for any other building/context
	 *     necessary.
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$query = $this->doGetFilterQuery( $value, $quotedValue );

		return [ $query, false ];
	}

	/**
	 * @param string $value
	 * @param string $quotedValue
	 * @return Query\MatchQuery|Query\MatchPhrase
	 */
	protected function doGetFilterQuery( $value, $quotedValue ) {
		if ( $value !== $quotedValue ) {
			// If used with quotes we create a more precise phrase query
			$query = new Query\MatchPhrase( $this->field, $value );
		} else {
			$query = new Query\MatchQuery( $this->field, [ 'query' => $value ] );
			$query->setFieldOperator( $this->field, 'AND' );
		}

		return $query;
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		return $this->doGetFilterQuery( $node->getValue(), $node->getQuotedValue() );
	}
}
