<?php

namespace CirrusSearch;

use DeferredUpdates;
use ISearchResultSet;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserIdentity;
use UIDGenerator;
use User;

/**
 * Handles logging information about requests made to various destinations,
 * such as monolog, EventBus and statsd.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class RequestLogger {
	/**
	 * @const int max number of results to store in cirrussearch-request logs (per request)
	 */
	const LOG_MAX_RESULTS = 50;

	// If a title hit was given to the user, but the hit was not
	// obtained via ElasticSearch, we won't really know where it came from.
	// We still want to log the fact that the request set generated a hit
	// for this title.  When this happens, the hit index field value will be this.
	const UNKNOWN_HIT_INDEX = '_UNKNOWN_';

	/**
	 * @var RequestLog[] Set of requests made
	 */
	private $logs = [];

	/**
	 * @var string[] Result page ids that were returned to user
	 */
	private $resultTitleStrings = [];

	/**
	 * @var string[][] Extra payload for the logs, indexed first by the log index
	 *  in self::$logs, and second by the payload item name.
	 */
	private $extraPayload = [];

	/**
	 * @return bool True when logs have been generated during the current
	 *  php execution.
	 */
	public function hasQueryLogs() {
		return (bool)$this->logs;
	}

	/**
	 * @param float $percentage A value between 0 and 1 indiciating the
	 *  percentage of calls that should return true.
	 * @return bool True on $percentage calls to this method
	 */
	private function sample( $percentage ) {
		if ( $percentage <= 0 ) {
			return false;
		}
		if ( $percentage >= 1 ) {
			return true;
		}
		$rand = random_int( 0, PHP_INT_MAX ) / PHP_INT_MAX;
		return $rand <= $percentage;
	}

	/**
	 * Summarizes all the requests made in this process and reports
	 * them along with the test they belong to.
	 */
	private function reportLogs() {
		if ( $this->logs ) {

			// Build the mediawiki/search/requestset event and log it to the (json+EventBus)
			// cirrussearch-request channel.
			LoggerFactory::getInstance( 'cirrussearch-request' )->debug(
				'', $this->buildCirrusSearchRequestEvent()
			);

			// reset logs
			$this->logs = [];
		}
	}

	/**
	 * @param RequestLog $log The log about a network request to be added
	 * @param User|null $user The user performing the request, or null
	 *  for actions that don't have a user (such as index updates).
	 * @param int|null $slowMillis The threshold in ms after which the request
	 *  will be considered slow.
	 * @return array A map of information about the performed request, suitible
	 *  for use as a psr-3 log context.
	 */
	public function addRequest( RequestLog $log, UserIdentity $user = null, $slowMillis = null ) {
		global $wgCirrusSearchLogElasticRequests;

		// @todo Is this necessary here? Check on what uses the response value
		$finalContext = $log->getLogVariables() + [
			'source' => Util::getExecutionContext(),
			'executor' => Util::getExecutionId(),
			'identity' => Util::generateIdentToken(),
		];
		if ( $wgCirrusSearchLogElasticRequests ) {
			$this->logs[] = $log;
			if ( count( $this->logs ) === 1 ) {
				DeferredUpdates::addCallableUpdate( function () {
					$this->reportLogs();
				} );
			}

			$logMessage = $this->buildLogMessage( $log, $finalContext );
			LoggerFactory::getInstance( 'CirrusSearchRequests' )->debug( $logMessage, $finalContext );
			if ( $slowMillis && $log->getTookMs() >= $slowMillis ) {
				if ( $user !== null ) {
					$finalContext['user'] = $user->getName();
					$logMessage .= ' for {user}';
				}
				LoggerFactory::getInstance( 'CirrusSearchSlowRequests' )->info( $logMessage, $finalContext );
			}
		}

		return $finalContext;
	}

	/**
	 * @param string $key
	 * @param string $value
	 */
	public function appendLastLogPayload( $key, $value ) {
		$idx = count( $this->logs ) - 1;
		if ( isset( $this->logs[$idx] ) ) {
			$this->extraPayload[$idx][$key] = $value;
		}
	}

	/**
	 * Report the types of queries that were issued
	 * within the current request.
	 *
	 * @return string[]
	 */
	public function getQueryTypesUsed() {
		$types = [];
		foreach ( $this->logs as $log ) {
			$types[] = $log->getQueryType();
		}
		return array_unique( $types );
	}

	/**
	 * This is set externally because we don't have complete control, from the
	 * SearchEngine interface, of what is actually sent to the user. Instead hooks
	 * receive the final results that will be sent to the user and set them here.
	 *
	 * Accepts two result sets because some places (Special:Search) perform multiple
	 * searches. This can be called multiple times, but only that last call wins. For
	 * API's that is correct, for Special:Search a hook catches the final results and
	 * sets them here.
	 *
	 * @param ISearchResultSet[] $matches
	 */
	public function setResultPages( array $matches ) {
		$titleStrings = [];
		foreach ( $matches as $resultSet ) {
			if ( $resultSet !== null ) {
				$titleStrings = array_merge( $titleStrings, $this->extractTitleStrings( $resultSet ) );
			}
		}
		$this->resultTitleStrings = $titleStrings;
	}

	/**
	 * Builds and ships a log context of an object that conforms to the
	 * JSONSchema  mediawiki/search/requestset event schema.
	 *
	 * @return array mediawiki/search/requestset event object
	 */
	private function buildCirrusSearchRequestEvent() {
		global $wgRequest, $wgServerName;
		$webrequest = $wgRequest;

		// for the moment RequestLog::getRequests() is still created in the
		// old format to serve the old log formats, so here we transform the
		// context into the new mediawiki/cirrussearch/request event format.
		// At some point the context should just be created in the correct format.
		$elasticSearchRequests = [];

		// If false, then at least one of the Elasticsearch request responses
		// returned was not from cache
		$allRequestsCached = true;

		// While building the elasticSearchRequests object, collect
		// a list of all hits from all Elasticsearch requests.
		$allHits = [];

		// Iterate over each individual Elasticsearch request
		foreach ( $this->logs as $idx => $elasticSearchRequestLog ) {
			foreach ( $elasticSearchRequestLog->getRequests() as $requestContext ) {
				// Build an entry in the elasticSearchRequests array from each logged
				// Elasticsearch request.
				$requestEntry = [];

				Util::setIfDefined( $requestContext, 'query', $requestEntry, 'query', 'strval' );
				Util::setIfDefined( $requestContext, 'queryType', $requestEntry, 'query_type', 'strval' );
				Util::setIfDefined(
					$requestContext, 'index', $requestEntry, 'indices',
					// Use list for search indices, not csv string.
					function ( $v ) {
						return explode( ',', $v );
					}
				);
				Util::setIfDefined(
					$requestContext, 'namespaces', $requestEntry, 'namespaces',
					// Make sure namespace values are all integers.
					function ( $v ) {
						if ( empty( $v ) ) {
							return $v;
						} else {
							return array_values( array_map( 'intval', $v ) );
						}
					}
				);
				Util::setIfDefined( $requestContext, 'tookMs', $requestEntry, 'request_time_ms', 'intval' );
				Util::setIfDefined( $requestContext, 'elasticTookMs', $requestEntry, 'search_time_ms', 'intval' );
				Util::setIfDefined( $requestContext, 'limit', $requestEntry, 'limit', 'intval' );
				Util::setIfDefined( $requestContext, 'hitsTotal', $requestEntry, 'hits_total', 'intval' );
				Util::setIfDefined( $requestContext, 'hitsReturned', $requestEntry, 'hits_returned', 'intval' );
				Util::setIfDefined( $requestContext, 'hitsOffset', $requestEntry, 'hits_offset', 'intval' );
				Util::setIfDefined( $requestContext, 'suggestion', $requestEntry, 'suggestion', 'strval' );

				// suggestion_requested is true if suggestionRequested or if any suggestion is present.
				$requestEntry['suggestion_requested'] =
					(bool)( $requestContext['suggestionRequested'] ?? isset( $requestContext['suggestion'] ) );

				Util::setIfDefined( $requestContext, 'maxScore', $requestEntry, 'max_score', 'floatval' );

				if ( isset( $this->extraPayload[$idx] ) ) {
					// fields in extraPayload keys must be explicitly
					// set here if we want to include them in the event.
					Util::setIfDefined(
						$this->extraPayload[$idx], 'langdetect', $requestEntry, 'langdetect', 'strval'
					);
				}

				Util::setIfDefined( $requestContext, 'syntax', $requestEntry, 'syntax' );

				// If response was servied from cache.
				$requestEntry['cached'] = (bool)$elasticSearchRequestLog->isCachedResponse();
				// $wereAllResponsesCached will be used later for
				// deteriming if all responses for the request set were cached.
				if ( !$requestEntry['cached'] ) {
					$allRequestsCached = false;
				}

				Util::setIfDefined(
					$requestContext, 'hits', $requestEntry, 'hits',
					function ( $v ) {
						return $this->encodeHits( $v );
					}
				);
				if ( isset( $requestEntry['hits'] ) ) {
					$allHits = array_merge( $allHits, $requestEntry['hits'] );
				}

				$elasticSearchRequests[] = $requestEntry;
			}
		}

		// Reindex allHits by page titles. It's maybe not perfect, but it's
		// hopefully a "close enough" representation of where our final result
		// set came from. maybe :(
		$allHitsByTitle = [];
		foreach ( $allHits as $hit ) {
			'@phan-var array $hit';
			$allHitsByTitle[$hit['page_title']] = $hit;
		}
		$resultHits = [];
		// $this->resultTitleStrings give us title hits that were actually provided to the user.
		// Build a subset top level list of hits from each sub request's list of hits.
		$resultTitleStrings = array_slice( $this->resultTitleStrings, 0, self::LOG_MAX_RESULTS );
		foreach ( $resultTitleStrings as $titleString ) {
			// $allHitsByTitle[$titleString] will have the hit. If there isn't a hit
			// for this title, then this title 'hit' did not come from a ElasticSearch request,
			// so log a 'fake' hit for it.  In this case, the index will be self::UNKNOWN_HIT_INDEX.
			$hit = $allHitsByTitle[$titleString] ?? [
				'page_title' => (string)$titleString,
				'index' => self::UNKNOWN_HIT_INDEX,
			];
			$resultHits[] = $hit;
		}

		$requestEvent = [
			// This schema can be found in the mediawiki/event-schemas repository.
			// The $schema URI here should be updated if we increment schema versions.
			'$schema' => '/mediawiki/cirrussearch/request/0.0.1',
			'meta' => [
				'request_id' => $webrequest->getRequestId(),
				'id' => UIDGenerator::newUUIDv4(),
				'dt' => wfTimestamp( TS_ISO_8601 ),
				'domain' => $wgServerName,
				'stream' => 'mediawiki.cirrussearch-request',
			],
			'http' => [
				'method' => $webrequest->getMethod(),
				'client_ip' => $webrequest->getIP(),
				'has_cookies' => $webrequest->getHeader( 'Cookie' ) ? true : false,
			],
			'database' => wfWikiID(),
			'mediawiki_host' => gethostname(),
			'search_id' => Util::getRequestSetToken(),
			'source' => Util::getExecutionContext(),
			'identity' => Util::generateIdentToken(),
			'request_time_ms' => $this->getPhpRequestTookMs(),
			'all_elasticsearch_requests_cached' => $allRequestsCached,
		];

		$webRequestValues = $webrequest->getValues();
		if ( !empty( $webRequestValues ) ) {
			$requestEvent['params'] = [];
			// Make sure all params are string keys and values
			foreach ( $webRequestValues as $k => $v ) {
				if ( !is_scalar( $v ) ) {
					// This is potentially a multi-dimensional array. JSON is
					// perhaps not the best format, but this gives a good
					// guarantee about always returning a string, and
					// faithfully represents the variety of shapes request
					// parameters can be parsed into.
					$v = \FormatJson::encode( $v );
				}
				$k = $webrequest->normalizeUnicode( $k );
				$requestEvent['params'][(string)$k] = (string)$v;
			}
		}

		// Don't set these fields if there is no data.
		if ( !empty( $resultHits ) ) {
			$requestEvent['hits'] = $resultHits;
		}
		if ( !empty( $elasticSearchRequests ) ) {
			$requestEvent['elasticsearch_requests'] = $elasticSearchRequests;
		}
		$activeTestNames = UserTesting::getInstance()->getActiveTestNamesWithBucket();
		if ( !empty( $activeTestNames ) ) {
			$requestEvent['backend_user_tests'] = $activeTestNames;
		}

		// If in webrequests, log these request headers in http.headers.
		$httpRequestHeadersToLog = [ 'accept-language', 'referer', 'user-agent' ];
		foreach ( $httpRequestHeadersToLog as $header ) {
			if ( $webrequest->getHeader( $header ) ) {
				$requestEvent['http']['request_headers'][$header] =
					(string)$webrequest->normalizeUnicode( $webrequest->getHeader( $header ) );
			}
		}

		return $requestEvent;
	}

	/**
	 * @param ISearchResultSet $matches
	 * @return string[]
	 */
	private function extractTitleStrings( ISearchResultSet $matches ) {
		$strings = [];
		foreach ( $matches as $result ) {
			$strings[] = (string)$result->getTitle();
		}

		return $strings;
	}

	/**
	 * @param RequestLog $log The request to build a log message about
	 * @param array $context Request specific log variables from RequestLog::getLogVariables()
	 * @return string a PSR-3 compliant message describing $context
	 */
	private function buildLogMessage( RequestLog $log, array $context ) {
		$message = $log->getDescription();
		$message .= " against {index} took {tookMs} millis";
		if ( isset( $context['elasticTookMs'] ) ) {
			$message .= " and {elasticTookMs} Elasticsearch millis";
			if ( isset( $context['elasticTook2PassMs'] ) && $context['elasticTook2PassMs'] >= 0 ) {
				$message .= " (with 2nd pass: {elasticTook2PassMs} ms)";
			}
		}
		if ( isset( $context['hitsTotal'] ) ) {
			$message .= ". Found {hitsTotal} total results";
			$message .= " and returned {hitsReturned} of them starting at {hitsOffset}";
		}
		if ( isset( $context['namespaces'] ) ) {
			$namespaces = implode( ', ', $context['namespaces'] );
			$message .= " within these namespaces: $namespaces";
		}
		if ( isset( $context['suggestion'] ) && strlen( $context['suggestion'] ) > 0 ) {
			$message .= " and suggested '{suggestion}'";
		}
		$message .= ". Requested via {source} for {identity} by executor {executor}";

		return $message;
	}

	/**
	 * Enforce all type constraints on the hits field and limit
	 * the number of results to the maximum specified.
	 *
	 * @param array[] $hits
	 * @return array[]
	 */
	private function encodeHits( array $hits ) {
		$formattedHits = [];
		foreach ( array_slice( $hits, 0, self::LOG_MAX_RESULTS )  as $hit ) {
			$formattedHit = [];
			Util::setIfDefined( $hit, 'title', $formattedHit, 'page_title', 'strval' );
			Util::setIfDefined( $hit, 'pageId', $formattedHit, 'page_id', 'intval' );
			Util::setIfDefined( $hit, 'index', $formattedHit, 'index', 'strval' );
			Util::setIfDefined( $hit, 'score', $formattedHit, 'score', 'floatval' );
			Util::setIfDefined( $hit, 'profileName', $formattedHit, 'profile_name', 'strval' );
			$formattedHits[] = $formattedHit;
		}
		return $formattedHits;
	}

	/**
	 * Note that this is only accurate for hhvm and php-fpm
	 * since they close the request to the user before running
	 * deferred updates.
	 *
	 * @return int The number of ms the php request took
	 */
	private function getPhpRequestTookMs() {
		$timing = \RequestContext::getMain()->getTiming();
		$startMark = $timing->getEntryByName( 'requestStart' );
		$endMark  = $timing->getEntryByName( 'requestShutdown' );
		if ( $startMark && $endMark ) {
			// should always work, but Timing can return null so
			// fallbacks are provided.
			$tookS = $endMark['startTime'] - $startMark['startTime'];
		} elseif ( isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ) {
			// php >= 5.4
			$tookS = microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'];
		} else {
			// php 5.3
			$tookS = microtime( true ) - $_SERVER['REQUEST_TIME'];
		}

		return intval( 1000 * $tookS );
	}
}
