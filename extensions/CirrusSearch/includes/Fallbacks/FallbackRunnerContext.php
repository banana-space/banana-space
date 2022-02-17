<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\Parser\NamespacePrefixParser;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Searcher;
use Elastica\ResultSet as ElasicaResultSet;

/**
 * Context storing the states of the FallbackRunner.
 * This object is populated/maintained by the FallbackRunner itself and read
 * by the FallbackMethod.
 */
interface FallbackRunnerContext {

	/**
	 * The initial resultset as returned by the main search query.
	 * @return CirrusSearchResultSet
	 */
	public function getInitialResultSet(): CirrusSearchResultSet;

	/**
	 * The resultset as rewritten by the previous fallback method.
	 * It may be equal to getInitialResultSet() if this is accessed by the
	 * first fallback method or if it was not rewritten yet.
	 * Technically this method returns the value of the previous FallbackMethod::rewrite()
	 * @return CirrusSearchResultSet
	 * @see FallbackMethod::rewrite()
	 */
	public function getPreviousResultSet(): CirrusSearchResultSet;

	/**
	 * Retrieve the response of the query attached to the main
	 * search request using ElasticSearchRequestFallbackMethod::getSearchRequest().
	 * NOTE: This method must not be called if no requests was attached.
	 *
	 * @return ElasicaResultSet
	 * @see ElasticSearchRequestFallbackMethod::getSearchRequest()
	 */
	public function getMethodResponse(): ElasicaResultSet;

	/**
	 * Whether or not a costly call is still allowed.
	 * @return bool
	 */
	public function costlyCallAllowed();

	/**
	 * Prepare a Searcher able to search for $rewrittenQuery.
	 * Calling this method.
	 * NOTE: a costly call must still be allowed before creating
	 * a new Searcher.
	 * @param \CirrusSearch\Search\SearchQuery $rewrittenQuery
	 * @return Searcher
	 * @see FallbackRunnerContext::costlyCallAllowed()
	 */
	public function makeSearcher( SearchQuery $rewrittenQuery );

	/**
	 * @return NamespacePrefixParser
	 */
	public function getNamespacePrefixParser(): NamespacePrefixParser;

	/**
	 * Whether or not this fallback method has an ElasticSearch response
	 * available.
	 * @return bool
	 */
	public function hasMethodResponse();
}
