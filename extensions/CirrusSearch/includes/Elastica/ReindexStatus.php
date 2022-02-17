<?php

namespace CirrusSearch\Elastica;

class ReindexStatus {
	/** @var array */
	protected $status;

	/**
	 * @param array $status Status part of response from elasticsearch _tasks api
	 */
	public function __construct( array $status ) {
		$this->status = $status;
	}

	/**
	 * @return bool
	 */
	public function isComplete() {
		return false;
	}

	/**
	 * @return int The total number of documents this request will process.
	 *  0 means we don't yet know or, possibly, there are actually 0 documents
	 *  to process.
	 */
	public function getTotal() {
		return $this->status['total'];
	}

	/**
	 * @return int Count of documents updated.
	 */
	public function getUpdated() {
		return $this->status['updated'];
	}

	/**
	 * @return int Count of documents created.
	 */
	public function getCreated() {
		return $this->status['created'];
	}

	/**
	 * @return int Count of successful delete operations.
	 */
	public function getDeleted() {
		return $this->status['deleted'];
	}

	/**
	 * @return int Number of scan responses this request has processed.
	 */
	public function getBatches() {
		return $this->status['batches'];
	}

	/**
	 * @return int Number of version conflicts this request has hit.
	 */
	public function getVersionConflicts() {
		return $this->status['version_conflicts'];
	}

	/**
	 * @return int Number of noops (skipped bulk items) as part of this request.
	 */
	public function getNoops() {
		return $this->status['noops'];
	}

	/**
	 * @return int Number of retires that had to be attempted due to bulk
	 *  actions being rejected.
	 */
	public function getBulkRetries() {
		return $this->status['retries']['bulk'];
	}

	/**
	 * @return int Number of retries that had to be attempted due to search
	 *  actions being rejected.
	 */
	public function getSearchRetries() {
		return $this->status['retries']['search'];
	}

	/**
	 * @return int The total time this request has throttled itself not
	 *  including the current throttle time if it is currently sleeping.
	 */
	public function getThrottledMillis() {
		return $this->status['throttled_millis'];
	}

	/**
	 * @return int The number of requests per second to which to throttle the
	 * request. -1 means unlimited.
	 */
	public function getRequestsPerSecond() {
		return $this->status['requests_per_second'];
	}

	/**
	 * @return int Remaining delay of any current throttle sleep or 0
	 *  if not sleeping.
	 */
	public function getThrottledUntil() {
		return $this->status['throttled_until_millis'];
	}

	/**
	 * @return array Statuses of the sub requests into which this sub-request was
	 *  sliced. Empty if this request wasn't sliced into sub-requests.
	 */
	public function getSlices() {
		return $this->status['slices'] ?? [];
	}
}
