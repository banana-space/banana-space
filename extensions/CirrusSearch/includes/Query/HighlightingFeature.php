<?php

namespace CirrusSearch\Query;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\Fetch\HighlightedField;

/**
 * Keywords willing to interact with the highlighting configuration
 * should implement this interface.
 */
interface HighlightingFeature {
	/**
	 * Build the list of highlighted fields to add to fetch phase configuration
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return HighlightedField[]
	 */
	public function buildHighlightFields( KeywordFeatureNode $node, QueryBuildingContext $context );
}
