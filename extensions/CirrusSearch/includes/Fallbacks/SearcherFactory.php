<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Searcher;

interface SearcherFactory {

	/**
	 * @param SearchQuery $query
	 * @return Searcher
	 */
	public function makeSearcher( SearchQuery $query );
}
