<?php

namespace CirrusSearch\Elastica;

use Exception;

class ReindexResponse extends ReindexStatus {
	/**
	 * @return bool True when the reindex completed successfully
	 */
	public function isSuccessful() {
		return !$this->isFailed() && !$this->isCanceled();
	}

	/**
	 * @return string The reason the reindex was unsuccessful
	 * @throws Exception If the reindex completed successfully
	 */
	public function getUnsuccessfulReason() {
		if ( $this->isCanceled() ) {
			return "Canceled: " . $this->getCanceledReason();
		} elseif ( $this->isFailed() ) {
			return "Failed: " . json_encode( $this->getFailures() );
		} else {
			throw new Exception( "Request was successful" );
		}
	}

	/**
	 * @return bool
	 */
	public function isComplete() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function isFailed() {
		return !empty( $this->status['failures'] );
	}

	/**
	 * @return string[]
	 */
	public function getFailures() {
		return $this->status['failures'];
	}

	/**
	 * @return bool True If the reindex was canceled (as opposed to failing)
	 */
	public function isCanceled() {
		return isset( $this->status['canceled'] ) && (bool)$this->status['canceled'];
	}

	/**
	 * @return string The reason the reindex was canceled
	 */
	public function getCanceledReason() {
		return $this->status['canceled'];
	}
}
