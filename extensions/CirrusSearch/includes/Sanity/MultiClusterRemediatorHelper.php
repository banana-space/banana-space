<?php

namespace CirrusSearch\Sanity;

use InvalidArgumentException;

/**
 * Helper to maintain the association with Remediators and theirs corresponding
 * BufferedRemediator.
 * When calling sendBatch this class evaluates if all action scheduled to the
 * BufferedRemediator are the same and send them to the all cluster remediator.
 * The aim is to minimize the number of generated LinksUpdate jobs in a setup
 * where there are many clusters. If the same elements are to be verified on
 * all the clusters then it is equivalent and more efficient to send these
 * elements to all clusters using the [ "cluster" => null ] job param as some
 * steps (link counting) will be shared accross all the clusters as opposed
 * to sending a LinksUpdate job per cluster.
 */
class MultiClusterRemediatorHelper {
	/** @var Remediator[] */
	private $perClusterRemediators;

	/** @var AllClustersQueueingRemediator */
	private $allClusterRemediator;

	/** @var BufferedRemediator[] */
	private $perClusterBufferedRemediator;

	/**
	 * @param QueueingRemediator[] $perClusterRemediators
	 * @param BufferedRemediator[] $perClusterBufferedRemediator
	 * @param AllClustersQueueingRemediator $allClusterRemediator
	 * @see \CirrusSearch\Job\CirrusGenericJob::decideClusters()
	 */
	public function __construct(
		array $perClusterRemediators,
		array $perClusterBufferedRemediator,
		AllClustersQueueingRemediator $allClusterRemediator
	) {
		if ( array_keys( $perClusterRemediators ) != array_keys( $perClusterBufferedRemediator ) ) {
			throw new InvalidArgumentException( '$perClusterRemediators and $perClusterBufferedRemediator must have the same keys' );
		}
		$this->perClusterRemediators = $perClusterRemediators;
		$this->perClusterBufferedRemediator = $perClusterBufferedRemediator;
		$this->allClusterRemediator = $allClusterRemediator;
	}

	/**
	 * Evaluate if all BufferedRemediator contain the same elements and replay
	 * the recorded actions to the all cluster remediator or to their corresponding
	 * remediators otherwize.
	 */
	public function sendBatch() {
		$allSame = true;
		$firstRemediator = null;
		foreach ( $this->perClusterBufferedRemediator as $remediator ) {
			if ( $firstRemediator === null ) {
				$firstRemediator = $remediator;
			} elseif ( !$remediator->hasSameActions( $firstRemediator ) ) {
				$allSame = false;
				break;
			}
		}
		if ( $allSame && $this->allClusterRemediator->canSendOptimizedJob( array_keys( $this->perClusterRemediators ) ) ) {
			$firstRemediator->replayOn( $this->allClusterRemediator );
		} else {
			foreach ( $this->perClusterBufferedRemediator as $cluster => $remediator ) {
				$remediator->replayOn( $this->perClusterRemediators[$cluster] );
			}
		}
		foreach ( $this->perClusterBufferedRemediator as $remediator ) {
			$remediator->resetActions();
		}
	}
}
