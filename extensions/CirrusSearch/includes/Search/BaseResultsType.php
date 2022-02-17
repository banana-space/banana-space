<?php

namespace CirrusSearch\Search;

/**
 * Base class for result type implementations.
 */
abstract class BaseResultsType implements ResultsType {

	/**
	 * @return false|string|array corresponding to Elasticsearch source filtering syntax
	 */
	public function getSourceFiltering() {
		return [ 'namespace', 'title', 'namespace_text', 'wiki' ];
	}
}
