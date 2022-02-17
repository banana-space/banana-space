<?php

namespace CirrusSearch\Query;

use CirrusSearch\Search\SearchContext;

/**
 * Parse a cirrus fulltext search query and build an elasticsearch query.
 */
interface FullTextQueryBuilder {
	/**
	 * Build a query for supplied term.
	 *
	 * The method will setup the query and accompanying environment within
	 * the supplied context.
	 *
	 * TODO: right now, this code does two separate things, parsing $term
	 * and building query from parsed structure. We may want to split this
	 * into separate actions, to allow other query builders to benefit from parsing
	 * stage but override building stage.
	 *
	 * @param SearchContext $searchContext
	 * @param string $term term to search
	 */
	public function build( SearchContext $searchContext, $term );

	/**
	 * Attempt to build a degraded query from the query already built into $context. Must be
	 * called *after* self::build().
	 *
	 * @param SearchContext $searchContext
	 * @return bool True if a degraded query was built
	 */
	public function buildDegraded( SearchContext $searchContext );
}
