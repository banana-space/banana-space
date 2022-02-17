<?php

namespace CirrusSearch\Search\Rescore;

use Elastica\Query\FunctionScore;

/**
 * Append functions to a FunctionScore
 * @package CirrusSearch\Search\Rescore
 */
interface BoostFunctionBuilder {

	/**
	 * Append functions to the function score $container
	 *
	 * @param FunctionScore $container
	 */
	public function append( FunctionScore $container );
}
