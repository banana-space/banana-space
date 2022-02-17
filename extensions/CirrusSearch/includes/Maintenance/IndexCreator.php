<?php

namespace CirrusSearch\Maintenance;

use Elastica\Index;
use Status;

class IndexCreator {

	/**
	 * @var Index
	 */
	private $index;

	/**
	 * @var array
	 */
	private $analysisConfig;

	/**
	 * @var array|null
	 */
	private $similarityConfig;

	/**
	 * @var array
	 */
	private $mapping;

	/**
	 * @param Index $index
	 * @param array $analysisConfig
	 * @param array|null $similarityConfig
	 */
	public function __construct( Index $index, array $analysisConfig, array $similarityConfig = null ) {
		$this->index = $index;
		$this->analysisConfig = $analysisConfig;
		$this->similarityConfig = $similarityConfig;
	}

	/**
	 * @param bool $rebuild
	 * @param int $maxShardsPerNode
	 * @param int $shardCount
	 * @param string $replicaCount
	 * @param int $refreshInterval
	 * @param array $mergeSettings
	 * @param bool $searchAllFields
	 * @param array $extraSettings
	 *
	 * @return Status
	 */
	public function createIndex(
		$rebuild,
		$maxShardsPerNode,
		$shardCount,
		$replicaCount,
		$refreshInterval,
		array $mergeSettings,
		$searchAllFields,
		array $extraSettings
	) {
		$args = $this->buildArgs(
			$maxShardsPerNode,
			$shardCount,
			$replicaCount,
			$refreshInterval,
			$mergeSettings,
			$searchAllFields,
			$extraSettings
		);

		try {
			$response = $this->index->create( $args, $rebuild );

			if ( $response->hasError() === true ) {
				return Status::newFatal( $response->getError() );
			}
		} catch ( \Elastica\Exception\InvalidException $ex ) {
			return Status::newFatal( $ex->getMessage() );
		} catch ( \Elastica\Exception\ResponseException $ex ) {
			return Status::newFatal( $ex->getMessage() );
		}

		return Status::newGood();
	}

	/**
	 * @param int $maxShardsPerNode
	 * @param int $shardCount
	 * @param string $replicaCount
	 * @param int $refreshInterval
	 * @param array $mergeSettings
	 * @param bool $searchAllFields
	 * @param array $extraSettings
	 *
	 * @return array
	 */
	private function buildArgs(
		$maxShardsPerNode,
		$shardCount,
		$replicaCount,
		$refreshInterval,
		array $mergeSettings,
		$searchAllFields,
		array $extraSettings
	) {
		$args = [
			'settings' => [
				'number_of_shards' => $shardCount,
				'auto_expand_replicas' => $replicaCount,
				'analysis' => $this->analysisConfig,
				'refresh_interval' => $refreshInterval . 's',
				'routing.allocation.total_shards_per_node' => $maxShardsPerNode,
			] + $extraSettings
		];

		if ( $mergeSettings ) {
			$args['settings']['merge.policy'] = $mergeSettings;
		}

		$similarity = $this->similarityConfig;
		if ( $similarity ) {
			$args['settings']['similarity'] = $similarity;
		}

		if ( $searchAllFields ) {
			// Use our weighted all field as the default rather than _all which is disabled.
			$args['settings']['index.query.default_field'] = 'all';
		}

		return $args;
	}

}
