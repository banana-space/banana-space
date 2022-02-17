<?php

namespace CirrusSearch\Job;

use CirrusSearch\ClusterSettings;
use CirrusSearch\Connection;
use CirrusSearch\ExternalIndex;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\SearchConfig;
use JobQueueGroup;
use MediaWiki\Logger\LoggerFactory;

/**
 * Traits for CirrusSearch Jobs.
 */
trait JobTraits {
	/**
	 * @return array
	 */
	abstract public function getParams();

	/**
	 * @return SearchConfig
	 */
	abstract public function getSearchConfig(): SearchConfig;

	/**
	 * @return string
	 */
	abstract public function getType();

	/**
	 * Actually perform the labor of the job.
	 * The Job will be retried if true is returned from allowRetries() when
	 * this method fails (thrown exception or returning false from this
	 * method).
	 * @return bool true for success, false for failures
	 */
	abstract protected function doJob();

	/**
	 * @param int $retryCount The number of times the job has errored out.
	 * @return int Number of seconds to delay. With the default minimum exponent
	 *  of 6 the possible return values are  64, 128, 256, 512 and 1024 giving a
	 *  maximum delay of 17 minutes.
	 */
	public function backoffDelay( $retryCount ) {
		$exponent = $this->getSearchConfig()->get( 'CirrusSearchWriteBackoffExponent' );
		$minIncrease = 0;
		if ( $retryCount > 1 ) {
			// Delay at least 2 minutes for everything that fails more than once
			$minIncrease = 1;
		}
		return ceil( pow( 2, $exponent + rand( $minIncrease, min( $retryCount, 4 ) ) ) );
	}

	/**
	 * Construct the list of connections suited for this job.
	 * NOTE: only suited for jobs that work on multiple clusters by
	 * inspecting the 'cluster' job param
	 *
	 * @return Connection[] indexed by cluster name
	 */
	protected function decideClusters() {
		$params = $this->getParams();
		$searchConfig = $this->getSearchConfig();
		$jobType = $this->getType();

		$cluster = $params['cluster'] ?? null;
		$assignment = $searchConfig->getClusterAssignment();
		if ( $cluster === null ) {
			$clusterNames = $assignment->getWritableClusters();
		} elseif ( $assignment->canWriteToCluster( $cluster ) ) {
			$clusterNames = [ $cluster ];
		} else {
			// Just in case a job is present in the queue but its cluster
			// has been removed from the config file.
			LoggerFactory::getInstance( 'CirrusSearch' )->warning(
				"Received {command} job for unwritable cluster {cluster}",
				[
					'command' => $jobType,
					'cluster' => $cluster
				]
			);
			// this job does not allow retries so we just need to throw an exception
			throw new \RuntimeException( "Received {$jobType} job for an unwritable cluster $cluster." );
		}

		$config = $searchConfig;
		if ( isset( $params['external-index'] ) ) {
			$otherIndex = new ExternalIndex( $searchConfig, $params['external-index'] );
			if ( $otherIndex->getCrossClusterName() !== null ) {
				// We assume that the cluster configs is mostly shared across cluster groups
				// e.g. this group config is available in CirrusSearchClusters
				// So that changing the CirrusSearchReplicaGroup to the CrossClusterName of the external
				// index we build the correct config to write to desired replica group.
				$config = new HashSearchConfig( [ 'CirrusSearchReplicaGroup' => $otherIndex->getCrossClusterName() ],
					[ HashSearchConfig::FLAG_INHERIT ], $config );
			}
			$clusterNames = array_filter( $clusterNames, function ( $cluster ) use ( $otherIndex ) {
				return !$otherIndex->isClusterBlacklisted( $cluster );
			} );
		}

		// Limit private data writes, such as archive index, to appropriately
		// flagged clusters
		if ( $params['private_data'] ?? false ) {
			// $clusterNames could be empty after this filter.  All consumers
			// must work appropriately with no connections returned, typically
			// by looping over the connections and doing nothing when no
			// connections are provided.
			$clusterNames = array_filter( $clusterNames, function ( $name ) use ( $config ) {
				$settings = new ClusterSettings( $config, $name );
				return $settings->isPrivateCluster();
			} );
		}

		$conns = Connection::getClusterConnections( $clusterNames, $config );
		$timeout = $config->get( 'CirrusSearchClientSideUpdateTimeout' );
		foreach ( $conns as $connection ) {
			$connection->setTimeout( $timeout );
		}

		return $conns;
	}

	/**
	 * Some boilerplate stuff for all jobs goes here
	 *
	 * @return bool
	 */
	public function run() {
		if ( $this->getSearchConfig()->get( 'DisableSearchUpdate' ) ) {
			return true;
		}

		return $this->doJob();
	}

	/**
	 * Get options to enable delayed jobs. Note that this might not be possible the JobQueue
	 * implementation handling this job doesn't support it (JobQueueDB) but is possible
	 * for the high performance JobQueueRedis.  Note also that delays are minimums -
	 * at least JobQueueRedis makes no effort to remove the delay as soon as possible
	 * after it has expired.  By default it only checks every five minutes or so.
	 * Note yet again that if another delay has been set that is longer then this one
	 * then the _longer_ delay stays.
	 *
	 * @param string $jobClass name of the job class
	 * @param int $delay seconds to delay this job if possible
	 * @return array options to set to add to the job param
	 */
	public static function buildJobDelayOptions( $jobClass, $delay ): array {
		$jobQueue = JobQueueGroup::singleton()->get( $jobClass );
		if ( !$delay || !$jobQueue->delayedJobsEnabled() ) {
			return [];
		}
		return [ 'jobReleaseTimestamp' => time() + $delay ];
	}

	/**
	 * @param string $clazz
	 * @return string
	 */
	public static function buildJobName( $clazz ) {
		return 'cirrusSearch' . str_replace( 'CirrusSearch\\Job\\', '', $clazz );
	}

}
