<?php

namespace CirrusSearch\Query;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\Rescore\BoostFunctionBuilder;

interface BoostFunctionFeature {

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return BoostFunctionBuilder|null
	 */
	public function getBoostFunctionBuilder( KeywordFeatureNode $node, QueryBuildingContext $context );
}
