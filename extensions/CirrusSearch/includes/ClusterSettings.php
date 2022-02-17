<?php

namespace CirrusSearch;

/**
 * Handles resolving configuration variables into specific settings
 * for a specific cluster.
 */
class ClusterSettings {

	/**
	 * @var SearchConfig
	 */
	protected $config;

	/**
	 * @var string
	 */
	protected $cluster;

	/**
	 * @param SearchConfig $config
	 * @param string $cluster
	 */
	public function __construct( SearchConfig $config, $cluster ) {
		$this->config = $config;
		$this->cluster = $cluster;
	}

	/**
	 * @return bool True when the cluster is allowed to contain private indices
	 */
	public function isPrivateCluster() {
		$privateClusters = $this->config->get( 'CirrusSearchPrivateClusters' );
		if ( $privateClusters === null ) {
			return true;
		} else {
			return in_array( $this->cluster, $privateClusters );
		}
	}

	/**
	 * @param string $indexType
	 * @return int Number of shards the index should have
	 */
	public function getShardCount( $indexType ) {
		$settings = $this->config->get( 'CirrusSearchShardCount' );
		if ( isset( $settings[$this->cluster][$indexType] ) ) {
			return $settings[$this->cluster][$indexType];
		} elseif ( isset( $settings[$indexType] ) ) {
			return $settings[$indexType];
		}
		throw new \Exception( "Could not find a shard count for "
			. "{$indexType}. Did you add an index to "
			. "\$wgCirrusSearchNamespaceMappings but forget to "
			. "add it to \$wgCirrusSearchShardCount?" );
	}

	/**
	 * @param string $indexType
	 * @return string Number of replicas Elasticsearch can expand or contract to
	 *  in the format of '0-2' for the minimum and maximum number of replicas. May
	 *  also be the string 'false' when replicas are disabled.
	 */
	public function getReplicaCount( $indexType ) {
		$settings = $this->config->get( 'CirrusSearchReplicas' );
		if ( !is_array( $settings ) ) {
			return $settings;
		} elseif ( isset( $settings[$this->cluster][$indexType] ) ) {
			return $settings[$this->cluster][$indexType];
		} elseif ( isset( $settings[$indexType] ) ) {
			return $settings[$indexType];
		}
		throw new \Exception( "If \$wgCirrusSearchReplicas is " .
			"an array it must contain all index types." );
	}

	/**
	 * @param string $indexType
	 * @return int Number of shards per node, or 'unlimited'.
	 */
	public function getMaxShardsPerNode( $indexType ) {
		$settings = $this->config->get( 'CirrusSearchMaxShardsPerNode' );
		$max = $settings[$this->cluster][$indexType] ?? $settings[$indexType] ?? -1;
		// Allow convenience setting of 'unlimited' which translates to elasticsearch -1 (unbounded).
		return $max === 'unlimited' ? -1 : $max;
	}

	/**
	 * @return int
	 */
	public function getDropDelayedJobsAfter() {
		$timeout = $this->config->get( 'CirrusSearchDropDelayedJobsAfter' );
		if ( is_int( $timeout ) ) {
			return $timeout;
		} elseif ( isset( $timeout[$this->cluster] ) ) {
			return $timeout[$this->cluster];
		}
		throw new \Exception( "If \$wgCirrusSearchDropDelayedJobsAfter is " .
			"an array it must contain all configured clusters." );
	}

	/**
	 * @return int Connect timeout to use when initializing connection.
	 * Fallback to 0 (300 sec) if not specified in cirrus config.
	 */
	public function getConnectTimeout() {
		$timeout = $this->config->get( 'CirrusSearchClientSideConnectTimeout' );
		if ( is_int( $timeout ) ) {
			return $timeout;
		}
		// 0 means no timeout (defaults to 300 sec)
		return $timeout[$this->cluster] ?? 0;
	}
}
