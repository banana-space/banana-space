<?php

namespace CirrusSearch;

/**
 * Represents an external index referenced by the OtherIndex functionality.
 * Typically sourced from $wgCirrusSearchExtraIndex.
 */
class ExternalIndex {
	/**
	 * @var SearchConfig
	 */
	private $config;

	/**
	 * @var string replica group to write to, null if the index requested is hosted on the same
	 * cluster group declared in as the default group in this SearchConfig $config.
	 */
	private $crossClusterName;

	/**
	 * @var string Name of index on external clusters
	 */
	private $indexName;

	/**
	 * @var string Name of index on external clusters. Can include a group prefix
	 *  when required.
	 */
	private $groupAndIndexName;

	/**
	 * @param Searchconfig $config
	 * @param string $indexName Name of index on external clusters. Can include a group prefix
	 * (e.g. "cluster_group:index_name")
	 */
	public function __construct( SearchConfig $config, $indexName ) {
		$this->config = $config;
		$this->groupAndIndexName = $indexName;
		$groupAndIndex = explode( ':', $indexName, 2 );
		if ( count( $groupAndIndex ) === 2 ) {
			$currentGroup = $config->getClusterAssignment()->getCrossClusterName();
			$this->crossClusterName = $currentGroup !== $groupAndIndex[0] ? $groupAndIndex[0] : null;
			$this->indexName = $groupAndIndex[1];
		} else {
			$this->indexName = $indexName;
		}
	}

	/**
	 * @return string Name of index on external clusters. Can include a group prefix
	 *  when required.
	 */
	public function getGroupAndIndexName() {
		return $this->groupAndIndexName;
	}

	/**
	 * @return string The name of the external index.
	 */
	public function getIndexName() {
		return $this->indexName;
	}

	/**
	 * @return string|null The group external index writes must be sent to, null to send to current group.
	 */
	public function getCrossClusterName() {
		return $this->crossClusterName;
	}

	/**
	 * @param string|null $sourceCrossClusterName Name of the cluster as configured in the cross-cluster
	 * search settings, null for simple&deprecated configs.
	 * @return string The name of the index to search. Includes
	 *   cross-cluster identifier if necessary.
	 */
	public function getSearchIndex( $sourceCrossClusterName ) {
		$currentGroup = $this->crossClusterName ?? $this->config->getClusterAssignment()->getCrossClusterName();

		return $sourceCrossClusterName === $currentGroup || $currentGroup === null
			? $this->indexName
			: "{$currentGroup}:{$this->indexName}";
	}

	/**
	 * @return array Two item array first containing a wiki name and second a map
	 *  from template name to weight for that template.
	 */
	public function getBoosts() {
		$boosts = $this->config->getElement( 'CirrusSearchExtraIndexBoostTemplates', $this->indexName );
		if ( isset( $boosts['wiki'] ) ) {
			return [ $boosts['wiki'], $boosts['boosts'] ?? [] ];
		} else {
			return [ '', [] ];
		}
	}

	/**
	 * @param string $cluster cluster
	 * @return bool true if writes must be avoided to this cluster replica (cluster as in DC).
	 */
	public function isClusterBlacklisted( $cluster ) {
		return (bool)$this->config->getElement( 'CirrusSearchExtraIndexClusterBlacklist', $this->indexName, $cluster );
	}
}
