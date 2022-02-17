<?php

namespace CirrusSearch\Elastica;

use Elastica\Client;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use MediaWiki\Logger\LoggerFactory;

class ReindexTask {
	/** @var Client */
	private $client;
	/** @var string */
	private $taskId;
	/** @var ReindexResponse|null */
	private $response;
	/** @var \Psr\Log\LoggerInterface */
	private $log;

	/**
	 * @param Client $client
	 * @param string $taskId
	 */
	public function __construct( Client $client, $taskId ) {
		$this->client = $client;
		$this->taskId = $taskId;
		$this->log = LoggerFactory::getInstance( 'CirrusSearch' );
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->taskId;
	}

	/**
	 * @param bool $check When true queries the remote
	 *  to see if the task is complete. Otherwise reports
	 *  last requested status.
	 * @return bool True if the reindex task is complete
	 */
	public function isComplete( $check = false ) {
		return $this->response !== null;
	}

	/**
	 * Cancel the in-progress reindex task.
	 * @return bool True if cancel was succesful
	 */
	public function cancel() {
		if ( $this->response ) {
			throw new \Exception( 'Cannot cancel completed task' );
		}

		$response = $this->client->request( "_tasks/{$this->taskId}/_cancel", Request::POST );

		return $response->isOK();
	}

	/**
	 * Delete the task
	 * @return bool True if delete was successfull, false otherwise.
	 *  Throws Elastica NotFoundException for unknown task (already
	 *  deleted?) or HttpException for communication failures.
	 */
	public function delete() {
		if ( !$this->response ) {
			throw new \Exception( 'Cannot delete in-progress task' );
		}
		$response =
			$this->client->getIndex( '.tasks' )->getType( 'task' )->deleteById( $this->taskId );

		return $response->isOK();
	}

	/**
	 * Get the final result of the reindexing task.
	 *
	 * @return ReindexResponse|null The result of the reindex, or null
	 *  if the reindex is still running. self::getStatus must be used
	 *  to update the task completion status.
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * @return ReindexStatus|null The status of the reindex, or null
	 *  on failure. Transport may also throw exceptions for network
	 *  failures.
	 */
	public function getStatus() {
		if ( $this->response ) {
			// task complete
			return $this->response;
		}

		$response = $this->client->request( "_tasks/{$this->taskId}", Request::GET );
		if ( !$response->isOK() ) {
			throw new ResponseException( $this->client->getLastRequest(), $response );
		}
		$data = $response->getData();
		$status = $data['task']['status'];

		if ( isset( $data['response'] ) ) {
			// task complete
			$this->response = new ReindexResponse( $data['response'] );

			return $this->response;
		}

		/**
		 * the task.status.slices array contains null for each incomplete child
		 * task. This fetches the children and merges their status in.
		 */
		if ( isset( $data['task']['status']['slices'] ) ) {
			$childResponse = $this->client->request( "_tasks", Request::GET, [], [
				'parent_task_id' => $this->taskId,
				'detailed' => 'true',
			] );
			if ( $childResponse->isOK() ) {
				$status = $this->mergeStatusWithChildren( $status, $childResponse->getData() );
			}
		}

		return new ReindexStatus( $status );
	}

	private function mergeStatusWithChildren( array $status, array $childResponse ) {
		foreach ( $childResponse['nodes'] as $nodeData ) {
			foreach ( $nodeData['tasks'] as $taskId => $childData ) {
				$sliceId = $childData['status']['slice_id'];
				$status['slices'][$sliceId] = $childData['status'];
			}
		}

		// Below mimics org.elasticsearch.action.bulk.byscroll.BulkByScrollTask.Status::Status
		// except that class doesn't have data about in-progress task's.
		$status['total'] = 0;
		$status['updated'] = 0;
		$status['created'] = 0;
		$status['deleted'] = 0;
		$status['batches'] = 0;
		$status['version_conflicts'] = 0;
		$status['noops'] = 0;
		$status['bulkRetries'] = 0;
		$status['searchRetries'] = 0;
		$status['throttled_millis'] = 0;
		$status['requests_per_second'] = 0;
		$status['throttled_until_millis'] = PHP_INT_MAX;
		$sliceFields = [
			'total',
			'updated',
			'created',
			'deleted',
			'batches',
			'version_conflicts',
			'noops',
			'retries',
			'throttled_millis',
			'requests_per_second',
			'throttled_until_millis',
		];
		foreach ( $status['slices'] as $slice ) {
			if ( $slice === null ) {
				// slice has failed catastrophically
				continue;
			}
			$missing_status_fields = array_diff_key( array_flip( $sliceFields ), $slice );
			if ( $missing_status_fields !== [] ) {
				// slice has missing key fields
				$slice_to_json = json_encode( $slice );
				$this->log->warning( 'Missing key field(s) for reindex task status', [
					'cirrus_reindex_task_slice' => $slice_to_json,
					'exact_missing_fields' => $missing_status_fields,
				] );
				continue;
			}
			$status['total'] += $slice['total'];
			$status['updated'] += $slice['updated'];
			$status['created'] += $slice['created'];
			$status['deleted'] += $slice['deleted'];
			$status['batches'] += $slice['batches'];
			$status['version_conflicts'] += $slice['version_conflicts'];
			$status['noops'] += $slice['noops'];
			$status['retries']['bulk'] += $slice['retries']['bulk'];
			$status['retries']['search'] += $slice['retries']['search'];
			$status['throttled_millis'] += $slice['throttled_millis'];
			$status['requests_per_second'] += $slice['requests_per_second'] === - 1 ? INF
				: $slice['requests_per_second'];
			$status['throttled_until_millis'] += min( $status['throttled_until_millis'],
				$slice['throttled_until_millis'] );
		}

		if ( $status['requests_per_second'] === INF ) {
			$status['requests_per_second'] = - 1;
		}

		return $status;
	}
}
