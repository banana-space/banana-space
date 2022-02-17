<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\InterwikiResolver;
use CirrusSearch\Search\SearchQuery;

/**
 * A fallback method is a way to interact (correct/fix/suggest a better query) with the search
 * results.
 *
 * Multiple methods can be chained together the order in which they are applied is determined
 * by the successApproximation method.
 *
 * The actual work is then done in the rewrite method where the method can actually change/augment
 * the current resultset.
 *
 * @package CirrusSearch\Fallbacks
 */
interface FallbackMethod {

	/**
	 * @param SearchQuery $query
	 * @param array $params
	 * @param InterwikiResolver $interwikiResolver
	 * @return FallbackMethod|null the method instance or null if unavailable
	 */
	public static function build( SearchQuery $query, array $params, InterwikiResolver $interwikiResolver );

	/**
	 * Approximation of the success of this fallback method
	 * this evaluation must be fast and not access remote resources.
	 *
	 * The score is interpreted as :
	 * - 1.0: the engine can blindly execute this one and discard any others
	 * 	 (saving respective calls to successApproximation of other methods)
	 * - 0.5: e.g. when no approximation is possible
	 * - 0.0: should not be tried (safe to skip costly work)
	 *
	 * The order of application (call to the rewrite method) is the order of these scores.
	 * If the score of multiple methods is equals the initialization order is kept.
	 *
	 * @param FallbackRunnerContext $context
	 * @return float
	 */
	public function successApproximation( FallbackRunnerContext $context );

	/**
	 * Rewrite the results.
	 *
	 * A costly call is allowed here. Result sets must not be changed directly,
	 * rather a FallbackStatus that applies the desired change must be returned.
	 *
	 * @param FallbackRunnerContext $context
	 * @return FallbackStatus
	 */
	public function rewrite( FallbackRunnerContext $context ): FallbackStatus;
}
