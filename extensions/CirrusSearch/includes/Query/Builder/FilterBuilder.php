<?php

namespace CirrusSearch\Query\Builder;

use Elastica\Query\AbstractQuery;

/**
 * Build a filter.
 * NOTE: Consider this as a BooleanQuery where only the methods to add new clauses
 * are exposed.
 */
interface FilterBuilder {

	/**
	 * @param AbstractQuery $query
	 */
	public function must( AbstractQuery $query );

	/**
	 * @param AbstractQuery $query
	 */
	public function mustNot( AbstractQuery $query );
}
