<?php

namespace CirrusSearch\Sanity;

use CirrusSearch\Assignment\ClusterAssignment;
use JobQueueGroup;
use Title;
use WikiPage;

/**
 * Remediator that forwards all actions to all writable clusters
 * using the 'cluster' => null optimization
 * @see \CirrusSearch\Job\CirrusGenericJob::decideClusters()
 */
class AllClustersQueueingRemediator implements Remediator {

	/**
	 * @var Remediator
	 */
	private $inner;

	/**
	 * @var string[] list of all writable clusters this remediator will write to
	 */
	private $clusters;

	/**
	 * @param ClusterAssignment $clusterAssignment
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct( ClusterAssignment $clusterAssignment, JobQueueGroup $jobQueueGroup ) {
		$this->clusters = $clusterAssignment->getWritableClusters();
		$this->inner = new QueueingRemediator( null, $jobQueueGroup );
	}

	/**
	 * Checker whether or not some remediations that are scheduled for the clusters
	 * defined in $clusters can be optimized by sending a single job per remediation
	 * instead duplicated remediations per cluster.
	 *
	 * @param array $clusters List of clusters affected by a remediation
	 * @return bool true if the list of clusters affected is the same than all writable clusters
	 * allowing the remediation to be sent to this Remediator
	 */
	public function canSendOptimizedJob( array $clusters ) {
		return array_diff( $this->clusters, $clusters ) === array_diff( $clusters, $this->clusters );
	}

	/**
	 * @inheritDoc
	 */
	public function redirectInIndex( WikiPage $page ) {
		$this->inner->redirectInIndex( $page );
	}

	/**
	 * @inheritDoc
	 */
	public function pageNotInIndex( WikiPage $page ) {
		$this->inner->pageNotInIndex( $page );
	}

	/**
	 * @inheritDoc
	 */
	public function ghostPageInIndex( $docId, Title $title ) {
		$this->inner->ghostPageInIndex( $docId, $title );
	}

	/**
	 * @inheritDoc
	 */
	public function pageInWrongIndex( $docId, WikiPage $page, $indexType ) {
		$this->inner->pageInWrongIndex( $docId, $page, $indexType );
	}

	/**
	 * @inheritDoc
	 */
	public function oldVersionInIndex( $docId, WikiPage $page, $indexType ) {
		$this->inner->oldVersionInIndex( $docId, $page, $indexType );
	}

	/**
	 * @inheritDoc
	 */
	public function oldDocument( WikiPage $page ) {
		$this->inner->oldDocument( $page );
	}
}
