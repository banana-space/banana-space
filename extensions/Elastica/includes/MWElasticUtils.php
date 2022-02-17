<?php

use Elastica\Client;
use MediaWiki\Logger\LoggerFactory;

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

/**
 * Utility class
 */
class MWElasticUtils {

	private const ONE_SEC_IN_MICROSEC = 1000000;

	/**
	 * A function that retries callback $func if it throws an exception.
	 * The $beforeRetry is called before a retry and receives the underlying
	 * ExceptionInterface object and the number of failed attempts.
	 * It's generally used to log and sleep between retries. Default behaviour
	 * is to sleep with a random backoff.
	 * @see Util::backoffDelay
	 *
	 * @param int $attempts the number of times we retry
	 * @param callable $func
	 * @param callable|null $beforeRetry function called before each retry
	 * @return mixed
	 */
	public static function withRetry( $attempts, $func, $beforeRetry = null ) {
		$errors = 0;
		while ( true ) {
			if ( $errors < $attempts ) {
				try {
					return $func();
				} catch ( Exception $e ) {
					$errors += 1;
					if ( $beforeRetry ) {
						$beforeRetry( $e, $errors );
					} else {
						$seconds = static::backoffDelay( $errors );
						usleep( $seconds * self::ONE_SEC_IN_MICROSEC );
					}
				}
			} else {
				return $func();
			}
		}
	}

	/**
	 * Backoff with lowest possible upper bound as 16 seconds.
	 * With the default maximum number of errors (5) this maxes out at 256 seconds.
	 *
	 * @param int $errorCount
	 * @return int
	 */
	public static function backoffDelay( $errorCount ) {
		return rand( 1, (int)pow( 2, 3 + $errorCount ) );
	}

	/**
	 * Get index health
	 *
	 * @param Client $client
	 * @param string $indexName
	 * @return array the index health status
	 */
	public static function getIndexHealth( Client $client, $indexName ) {
		$endpoint = new \Elasticsearch\Endpoints\Cluster\Health;
		$endpoint->setIndex( $indexName );
		$response = $client->requestEndpoint( $endpoint );
		if ( $response->hasError() ) {
			throw new \Exception( "Error while fetching index health status: " . $response->getError() );
		}
		return $response->getData();
	}

	/**
	 * Wait for the index to go green
	 *
	 * @param Client $client
	 * @param string $indexName Name of index to wait for
	 * @param int $timeout In seconds
	 * @return \Generator|string[]|bool Returns a generator. Generator yields
	 *  string status messages. Generator return value is true if the index is
	 *  green false otherwise.
	 */
	public static function waitForGreen( Client $client, $indexName, $timeout ) {
		$startTime = time();
		while ( ( $startTime + $timeout ) > time() ) {
			try {
				$response = self::getIndexHealth( $client, $indexName );
				$status = $response['status'] ?? 'unknown';
				if ( $status === 'green' ) {
					yield "\tGreen!";
					return true;
				}
				yield "\tIndex is $status retrying...";
				sleep( 5 );
			} catch ( \Exception $e ) {
				yield "Error while waiting for green ({$e->getMessage()}), retrying...";
			}
		}
		return false;
	}

	/**
	 * Delete docs by query and wait for it to complete via tasks api.
	 *
	 * @param \Elastica\Index $index the source index
	 * @param \Elastica\Query $query the query
	 * @param bool $allowConflicts When true documents updated since starting
	 *  the query will not be deleted, and will not fail the delete-by-query. When
	 *  false (default) the updated document will not be deleted and the delete-by-query
	 *  will abort. Deletes are not transactional, some subset of matching documents
	 *  will have been deleted.
	 * @param int $reportEveryNumSec Log task status on this interval of seconds
	 * @return \Elastica\Task Generator returns the Task instance on completion.
	 * @throws Exception when task reports failures
	 */
	public static function deleteByQuery(
		\Elastica\Index $index,
		\Elastica\Query $query,
		$allowConflicts = false,
		$reportEveryNumSec = 300
	) {
		$gen = self::deleteByQueryWithStatus( $index, $query, $allowConflicts, $reportEveryNumSec );
		// @phan-suppress-next-line PhanTypeNoAccessiblePropertiesForeach always a generator object
		foreach ( $gen as $status ) {
			// We don't need these status updates. But we need to iterate
			// the generator until it is done.
		}
		return $gen->getReturn();
	}

	/**
	 * @param float $minDelay Starting value of generator
	 * @param float $maxDelay Maximum value to return
	 * @param float $increaseByRatio Increase by this ratio on each iteration, up to $maxDelay
	 * @return Generator|float[] Returns a generator. Generator yields floats between
	 *  $minDelay and $maxDelay
	 * @suppress PhanInfiniteLoop
	 */
	private static function increasingDelay( $minDelay, $maxDelay, $increaseByRatio = 1.5 ) {
		$delay = $minDelay;
		while ( true ) {
			yield $delay;
			$delay = min( $delay * $increaseByRatio, $maxDelay );
		}
	}

	/**
	 * Delete docs by query and wait for it to complete via tasks api. This
	 * method returns a generator which must be iterated on at least once
	 * or the deletion will not occur.
	 *
	 * Client code that doesn't care about the result or when the deleteByQuery
	 * completes are safe to call next( $gen ) a single time to start the deletion,
	 * and then throw away the generator. Note that logging about how long the task
	 * has been running will not be logged if the generator is not iterated.
	 *
	 * @param \Elastica\Index $index the source index
	 * @param \Elastica\Query $query the query
	 * @param bool $allowConflicts When true documents updated since starting
	 *  the query will not be deleted, and will not fail the delete-by-query. When
	 *  false (default) the updated document will not be deleted and the delete-by-query
	 *  will abort. Deletes are not transactional, some subset of matching documents
	 *  will have been deleted.
	 * @param int $reportEveryNumSec Log task status on this interval of seconds
	 * @return \Generator|array[]|\Elastica\Task Returns a generator. Generator yields
	 *  arrays containing task status responses. Generator returns the Task instance
	 *  on completion via Generator::getReturn.
	 * @throws Exception when task reports failures
	 */
	public static function deleteByQueryWithStatus(
		\Elastica\Index $index,
		\Elastica\Query $query,
		$allowConflicts = false,
		$reportEveryNumSec = 300
	) {
		$params = [
			'wait_for_completion' => 'false',
			'scroll' => '15m',
		];
		if ( $allowConflicts ) {
			$params['conflicts'] = 'proceed';
		}
		$response = $index->deleteByQuery( $query, $params )->getData();
		if ( !isset( $response['task'] ) ) {
			throw new \Exception( 'No task returned: ' . var_export( $response, true ) );
		}
		$log = LoggerFactory::getInstance( 'Elastica' );
		$clusterName = self::fetchClusterName( $index->getClient() );
		$logContext = [
			'index' => $index->getName(),
			'cluster' => $clusterName,
			'taskId' => $response['task'],
		];
		$logPrefix = 'deleteByQuery against [{index}] on cluster [{cluster}] with task id [{taskId}]';
		$log->info( "$logPrefix starting", $logContext + [
			'elastic_query' => FormatJson::encode( $query->toArray() )
		] );

		// Log tasks running longer than 10 minutes to help track down job runner
		// timeouts that occur after 20 minutes. T219234
		$start = MWTimestamp::time();
		$reportAfter = $start + $reportEveryNumSec;
		$task = new \Elastica\Task(
			$index->getClient(),
			$response['task'] );
		$delay = self::increasingDelay( 0.05, 5 );
		while ( !$task->isCompleted() ) {
			$now = MWTimestamp::time();
			if ( $now >= $reportAfter ) {
				$reportAfter = $now + $reportEveryNumSec;
				$log->warning( "$logPrefix still running after [{runtime}] seconds", $logContext + [
					'runtime' => $now - $start,
					// json encode to ensure we don't add a bunch of properties in
					// logstash, we only really need the content and this will still be
					// searchable.
					'status' => FormatJson::encode( $task->getData() ),
				] );
			}
			yield $task->getData();
			$delay->next();
			usleep( $delay->current() * self::ONE_SEC_IN_MICROSEC );
			$task->refresh();
		}

		$now = MWTimestamp::time();
		$taskCompleteResponse = $task->getData()['response'];
		if ( $taskCompleteResponse['failures'] ) {
			$log->error( "$logPrefix failed", $logContext + [
				'runtime' => $now - $start,
				'status' => FormatJson::encode( $task->getData() ),
			] );
			throw new \Exception( 'Failed deleteByQuery: '
				. implode( ', ', $taskCompleteResponse['failures'] ) );
		}

		$log->info( "$logPrefix completed", $logContext + [
			'runtime' => $now - $start,
			'status' => FormatJson::encode( $task->getData() ),
		] );

		return $task;
	}

	/**
	 * Fetch the name of the cluster client is communicating with.
	 *
	 * @param Client $client Elasticsearch client to fetch name for
	 * @return string Name of cluster $client is communicating with
	 */
	public static function fetchClusterName( Client $client ) {
		$response = $client->requestEndpoint( new \Elasticsearch\Endpoints\Info );
		if ( $response->getStatus() !== 200 ) {
			throw new \Exception(
				"Failed requesting cluster name, got status code [{$response->getStatus()}]" );
		}
		return $response->getData()['cluster_name'];
	}

	private const METASTORE_INDEX_NAME = 'mw_cirrus_metastore';
	private const ALL_INDEXES_FROZEN_NAME = 'freeze-everything';

	/**
	 * @param Client $client
	 * @return bool True when no writes should be sent via $client
	 */
	public static function isFrozen( Client $client ) {
		// TODO: This should be a hook that cirrus implements to tell
		// others to not try to write to a cluster.
		$ids = ( new \Elastica\Query\Ids() )
			->addId( self::ALL_INDEXES_FROZEN_NAME );
		$resp = $client
			->getIndex( self::METASTORE_INDEX_NAME )
			->search( \Elastica\Query::create( $ids ) );

		return $resp->count() > 0;
	}
}
