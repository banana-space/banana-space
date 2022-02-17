<?php

namespace CirrusSearch\Dispatch;

use CirrusSearch\Search\SearchQuery;

/**
 * The Search query dispatch service.
 * Based on a SearchQuery and the SearchQueryRoute that have been
 * declared find the best possible route for this query.
 *
 * All routes are evaluated the best one is returned.
 *  - If multiple routes gives equal score the first one wins
 *  - If multiple routes give the max score of 1 then the system fails
 *  - If no routes is found the system fails
 *
 * @see SearchQueryRoute
 */
interface SearchQueryDispatchService {
	/**
	 * Score used by cirrus defaults.
	 * Anything below is unlikely to be selected as cirrus defaults
	 * are made to catchup all query types.
	 */
	const CIRRUS_DEFAULTS_SCORE = 0.0001;

	/**
	 * Determine the best route for the $query.
	 *
	 * @param SearchQuery $query
	 * @return SearchQueryRoute
	 */
	public function bestRoute( SearchQuery $query ): SearchQueryRoute;
}
