<?php

namespace CirrusSearch;

/**
 * Represents logging information for a single network operation made between
 * php and elasticsearch. Information returned from here goes through the
 * RequestLogger class and gets logged to the cirrussearch-request channel
 * for later processing in analytics platforms.
 */
interface RequestLog {
	/**
	 * Called when the network request is started
	 */
	public function start();

	/**
	 * Called when the network request has finished
	 */
	public function finish();

	/**
	 * @return string The type of query that was performed
	 */
	public function getQueryType();

	/**
	 * @return string Get the raw psr-3 compliant request description
	 */
	public function getDescription();

	/**
	 * @return string Get the request description, formatted as per psr-3 guidelines
	 *  with self::getLogVariables()
	 */
	public function formatDescription();

	/**
	 * @return int|null The number of ms php spend waiting for the request,
	 *  or null if the request has not finished yet.
	 */
	public function getTookMs();

	/**
	 * @return int The number of ms elasticsearch reported spending on the request,
	 *  or -1 if no request was made (such as cached responses).
	 */
	public function getElasticTookMs();

	/**
	 * @return bool Was this query answered without talking to elasticsearch?
	 */
	public function isCachedResponse();

	/**
	 * @return array Various information about the request(s). The exact set of
	 *  returned keys can vary, but should generally conform to what is expected
	 *  in RequestLogger::buildRequestSetLog(). This must return a single map
	 *  of k/v pairs regardless of the number of requests represented here.
	 *  This is utilized primarily for error reporting purposes.
	 */
	public function getLogVariables();

	/**
	 * @return array[] array of arrays containing various information about the
	 * request(s). The exact returned keys can vary, but should generally
	 * conform to what is expected in RequestLogger::buildRequestSetLog(). This
	 * must return one map per request represented by this log. This is
	 * primarily used for structured logging of request data to be analyzed in
	 * analytics platforms.
	 */
	public function getRequests();
}
