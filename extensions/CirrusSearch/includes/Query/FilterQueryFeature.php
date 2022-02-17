<?php

namespace CirrusSearch\Query;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use Elastica\Query\AbstractQuery;

/**
 * A KeywordFeature that generates an elasticsearch query used
 * as a filter.
 * When a keyword implementation wants to filter a subset of docs matching a particular
 * field/term it needs to implement this interface.
 * This interface should only be implemented by KeywordFeature implementations.
 * @see AbstractQuery
 * @see KeywordFeature
 */
interface FilterQueryFeature {

	/**
	 * Build a filter query using the information available in KeywordFeatureNode or in
	 * QueryBuildingContext::getKeywordExpandedData()
	 * This method will be called at the very end of the query generation process when building
	 * the query of the search request.
	 * The implementor may return null in case the parsed data is inappropriate
	 * it may help the query generation code to optimize the search process by not
	 * sending a search request to the backend (e.g. when this keyword is part of a
	 * conjunction at the root)
	 * @param KeywordFeatureNode $node the node corresponding to this keyword that was parsed
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null the filter to apply or null if the information parsed
	 * in $node does not allow the query to be built.
	 * @see QueryBuildingContext::getKeywordExpandedData()
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context );
}
