<?php

namespace Flow\Import\LiquidThreadsApi;

use ArrayIterator;
use Iterator;

/**
 * Cached LiquidThreads thread data.
 * @property array[] $data
 */
class CachedThreadData extends CachedApiData {
	protected $topics = [];

	/**
	 * @param array[] $data
	 */
	protected function addData( array $data ) {
		parent::addData( $data );

		foreach ( $data as $thread ) {
			if ( self::isTopic( $thread ) ) {
				$this->topics[$thread['id']] = true;
			}
		}
		ksort( $this->topics );
	}

	/**
	 * Get the IDs of loaded threads that are top-level topics.
	 *
	 * @return array List of thread IDs in ascending order.
	 */
	public function getTopics() {
		return array_keys( $this->topics );
	}

	/**
	 * Create an iterator for the contained topic ids in ascending order
	 *
	 * @return Iterator<int>
	 */
	public function getTopicIdIterator() {
		return new ArrayIterator( $this->getTopics() );
	}

	/**
	 * Retrieve data for threads from the given page starting with the provided
	 * offset.
	 *
	 * @param string $pageName
	 * @param int $startId
	 * @return array Associative result array
	 */
	public function getFromPage( $pageName, $startId = 0 ) {
		$data = $this->backend->retrieveThreadData(
			[
				'thpage' => $pageName,
				'thstartid' => $startId
			]
		);
		$this->addData( $data );

		return $data;
	}

	protected function retrieve( array $ids ) {
		return $this->backend->retrieveThreadData(
			[
				'thid' => implode( '|', $ids ),
			]
		);
	}

	/**
	 * @param array $thread
	 * @return bool
	 */
	public static function isTopic( array $thread ) {
		return $thread['parent'] === null;
	}
}
