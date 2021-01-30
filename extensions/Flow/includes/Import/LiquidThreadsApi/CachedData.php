<?php

namespace Flow\Import\LiquidThreadsApi;

/**
 * Abstract class to store ID-indexed cached data.
 */
abstract class CachedData {
	protected $data = [];

	public function reset() {
		$this->data = [];
	}

	/**
	 * Get the value for a given ID
	 *
	 * @param int $id The ID to get
	 * @return mixed The data returned by retrieve()
	 */
	public function get( $id ) {
		$result = $this->getMulti( [ $id ] );
		return reset( $result );
	}

	public function getMaxId() {
		if ( $this->data ) {
			return max( array_keys( $this->data ) );
		} else {
			return 0;
		}
	}

	/**
	 * Get the value for a number of IDs
	 *
	 * @param int[] $ids List of IDs to retrieve
	 * @return array Associative array, indexed by ID.
	 */
	public function getMulti( array $ids ) {
		$this->ensureLoaded( $ids );

		$output = [];
		foreach ( $ids as $id ) {
			$output[$id] = $this->data[$id] ?? null;
		}

		return $output;
	}

	/**
	 * Gets the number of items stored in this object.
	 *
	 * @return int
	 */
	public function getSize() {
		return count( $this->data );
	}

	/**
	 * Uncached retrieval of data from the backend.
	 *
	 * @param int[] $ids The IDs to retrieve data for
	 * @return array Associative array of data retrieved, indexed by ID.
	 */
	abstract protected function retrieve( array $ids );

	/**
	 * Adds data to the object
	 *
	 * @param array $data Associative array, indexed by ID.
	 */
	protected function addData( array $data ) {
		$this->data += $data;
	}

	/**
	 * Load missing IDs from a list
	 *
	 * @param int[] $ids The IDs to retrieve
	 */
	protected function ensureLoaded( array $ids ) {
		$missing = array_diff( $ids, array_keys( $this->data ) );

		if ( count( $missing ) > 0 ) {
			$data = $this->retrieve( $missing );
			$this->addData( $data );
		}
	}
}
