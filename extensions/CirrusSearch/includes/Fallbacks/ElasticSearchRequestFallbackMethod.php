<?php

namespace CirrusSearch\Fallbacks;

use Elastica\Client;
use Elastica\Search;

/**
 * A fallback method that is able to attach a complete search query to the main msearch
 * request.
 * Results of the query are available from the FallbackRunnerContext
 */
interface ElasticSearchRequestFallbackMethod {

	/**
	 * Build a search to attach to the msearch request
	 * The results of this query is available from
	 * @param Client $client
	 * @return Search|null null if no additional request is to be executed for this method.
	 * @see FallbackRunnerContext::getMethodResponse()
	 */
	public function getSearchRequest( Client $client );
}
