<?php

namespace CirrusSearch\Dispatch;

use CirrusSearch\Search\SearchQuery;

/**
 * For a given search engine entry point a SearchQueryRoute evaluates
 * a particular SearchQuery and assign it a score.
 * The SearchQueryDispatchService evaluates these scores and chose the best one
 * in order to assign the profile context using the one provided by the route
 * itself.
 * SearchQueryRoutes are evaluated just after the SearchQuery is constructed
 * and before ES query building components are chosen.
 * @see \CirrusSearch\Profile\SearchProfileService
 */
interface SearchQueryRoute {

	/**
	 * Compute a score for this particular $query.
	 * Special values:
	 * - 0.0: this route must be avoided
	 * - 1.0: this route must supersede any others, the system
	 *      fails if multiple routes return a score equals to 1
	 * @param SearchQuery $query
	 * @return float a score between 0 and 1
	 */
	public function score( SearchQuery $query );

	/**
	 * The entry point used in the search engine:
	 * - searchText
	 * - nearMatch
	 * - completionSearch
	 *
	 * @return string
	 */
	public function getSearchEngineEntryPoint();

	/**
	 * The SearchProfile context to use when this route is chosen.
	 *
	 * @return string
	 */
	public function getProfileContext();
}
