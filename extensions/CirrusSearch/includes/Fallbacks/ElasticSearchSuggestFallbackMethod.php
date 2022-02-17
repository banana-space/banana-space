<?php

namespace CirrusSearch\Fallbacks;

/**
 * Interface for fallback methods that uses Elasticsearch suggest attached to the
 * main query.
 */
interface ElasticSearchSuggestFallbackMethod {

	/**
	 * List of suggest queries indexed by name
	 * @return array|null
	 */
	public function getSuggestQueries();
}
