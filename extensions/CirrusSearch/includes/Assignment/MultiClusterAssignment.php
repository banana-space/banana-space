<?php

namespace CirrusSearch\Assignment;

use CirrusSearch\SearchConfig;
use Wikimedia\Assert\Assert;

class MultiClusterAssignment implements ClusterAssignment {
	/** @var SearchConfig */
	private $config;
	/** @var array[][]|null 2d array mapping (replica, group) to connection configuration */
	private $clusters;
	/** @var string */
	private $group;

	public function __construct( SearchConfig $config ) {
		$this->config = $config;
		$groupConfig = $config->get( 'CirrusSearchReplicaGroup' );
		if ( $groupConfig === null ) {
			throw new \RuntimeException( 'CirrusSearchReplicaGroup is null' );
		}
		if ( is_string( $groupConfig ) ) {
			$groupConfig = [
				'type' => 'constant',
				'group' => $groupConfig,
			];
		}
		$this->group = $this->evalGroupStrategy( $groupConfig );
	}

	/**
	 * @param array $groupConfig
	 * @return string
	 */
	private function evalGroupStrategy( array $groupConfig ) {
		// Determine which group this wiki belongs to
		switch ( $groupConfig['type'] ) {
		case 'constant':
			return $groupConfig['group'];
		case 'roundrobin':
			$wikiId = $this->config->getWikiId();
			$mod = count( $groupConfig['groups'] );
			Assert::precondition( $mod > 0, "At least one replica group must be defined for roundrobin" );
			$idx = crc32( $wikiId ) % $mod;
			return $groupConfig['groups'][$idx];
		default:
			throw new \RuntimeException( "Unknown replica group type: {$groupConfig['type']}" );
		}
	}

	private function initClusters(): array {
		$clusters = [];
		// We could require the input come in this shape, instead of reshaping
		// it when we start, but it seemed awkward to work with.
		foreach ( $this->config->get( 'CirrusSearchClusters' ) as $name => $config ) {
			$replica = $config['replica'] ?? $name;
			// Tempting to skip everything that doesn't match $this->group, but we have
			// to also track single group replicas with arbitrary group names.
			$group = $config['group'] ?? 'default';
			unset( $config['replica'], $config['group'] );
			if ( isset( $clusters[$replica][$group] ) ) {
				throw new \RuntimeException( "Multiple clusters for replica: $replica group: $group" );
			}
			$clusters[$replica][$group] = $config;
		}
		return $clusters;
	}

	/**
	 * @param string $cluster Name of requested cluster
	 * @return string Uniquely identifies the connection properties.
	 */
	public function uniqueId( $cluster ) {
		return "{$this->group}:$cluster";
	}

	/**
	 * @return string[] List of CirrusSearch cluster names to write to.
	 */
	public function getWritableClusters(): array {
		$clusters = $this->config->get( 'CirrusSearchWriteClusters' );
		if ( $clusters !== null ) {
			return $clusters;
		}
		// No explicitly configured set of write clusters. Write to all known replicas.
		if ( $this->clusters === null ) {
			$this->clusters = $this->initClusters();
		}
		return array_keys( $this->clusters );
	}

	/**
	 * Check if a cluster is declared "writable".
	 * NOTE: a cluster is considered writable even if one of its index is
	 * frozen.
	 * Before sending any writes in this cluster, the forzen index status
	 * must be checked fr the  target index.
	 * @see DataSender::isAvailableForWrites()
	 *
	 * @param string $cluster
	 * @return bool
	 */
	public function canWriteToCluster( $cluster ) {
		return in_array( $cluster, $this->getWritableClusters() );
	}

	/**
	 * @return string Name of the default search cluster.
	 */
	public function getSearchCluster() {
		return $this->config->get( 'CirrusSearchDefaultCluster' );
	}

	/**
	 * @return string Name to prefix indices with when
	 *  using cross-cluster-search.
	 */
	public function getCrossClusterName() {
		return $this->group;
	}

	/**
	 * @param string|null $replica
	 * @return string[]|array[]
	 */
	public function getServerList( $replica = null ): array {
		if ( $this->clusters === null ) {
			$this->clusters = $this->initClusters();
		}
		if ( $replica === null ) {
			$replica = $this->config->get( 'CirrusSearchDefaultCluster' );
		}
		if ( !isset( $this->clusters[$replica] ) ) {
			$available = implode( ',', array_keys( $this->clusters ) );
			throw new \RuntimeException( "Missing replica <$replica>, have <$available>" );
		} elseif ( isset( $this->clusters[$replica][$this->group] ) ) {
			return $this->clusters[$replica][$this->group];
		} elseif ( count( $this->clusters[$replica] ) === 1 ) {
			// If a replica only has a single elasticsearch cluster then by
			// definition everything goes there.
			return reset( $this->clusters[$replica] );
		} else {
			throw new \RuntimeException( "Missing replica: $replica group: {$this->group}" );
		}
	}
}
