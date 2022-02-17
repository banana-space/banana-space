<?php

namespace CirrusSearch;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\ClusterSettings
 */
class ClusterSettingsTest extends CirrusTestCase {

	public static function provideShardCount() {
		return [
			'Handles per-index shard counts' => [
				[ 'general' => 7 ],
				'dc-foo',
				'general',
				7,
			],

			'Handles per-cluster shard counts' => [
				[ 'content' => 6, 'dc-foo' => [ 'content' => 9 ] ],
				'dc-foo',
				'content',
				9,
			],
		];
	}

	/**
	 * @dataProvider provideShardCount
	 */
	public function testShardCount( $shardCounts, $cluster, $indexType, $expect ) {
		$config = $this->getMockBuilder( SearchConfig::class )
			->disableOriginalConstructor()
			->getMock();
		$config->expects( $this->any() )
			->method( 'get' )
			->with( 'CirrusSearchShardCount' )
			->will( $this->returnValue( $shardCounts ) );

		$settings = new ClusterSettings( $config, $cluster );
		$this->assertEquals( $expect, $settings->getShardCount( $indexType ) );
	}

	public static function provideReplicaCounts() {
		return [
			'Simple replica config returns exact setting ' => [
				'0-2',
				'dc-foo',
				'content',
				'0-2',
			],

			'Accepts array for replica config' => [
				[ 'content' => '1-2' ],
				'dc-foo',
				'content',
				'1-2',
			],

			'Accepts per-cluster replica config' => [
				[ 'content' => '1-2', 'dc-foo' => [ 'content' => '2-3' ] ],
				'dc-foo',
				'content',
				'2-3'
			],
		];
	}

	/**
	 * @dataProvider provideReplicaCounts
	 */
	public function testReplicaCount( $replicas, $cluster, $indexType, $expect ) {
		$config = $this->getMockBuilder( SearchConfig::class )
			->disableOriginalConstructor()
			->getMock();
		$config->expects( $this->any() )
			->method( 'get' )
			->with( 'CirrusSearchReplicas' )
			->will( $this->returnValue( $replicas ) );

		$settings = new ClusterSettings( $config, $cluster );
		$this->assertEquals( $expect, $settings->getReplicaCount( $indexType ) );
	}

	public static function provideMaxShardsPerNode() {
		return [
			'empty configuration' => [
				'maxShardsPerNode' => [],
				'cluster' => 'default',
				'indexType' => 'content',
				'expect' => -1,
			],
			'explicitly unbounded' => [
				'maxShardsPerNode' => [ 'content' => 1, 'general' => 'unlimited' ],
				'cluster' => 'default',
				'indexType' => 'general',
				'expect' => -1,
			],
			'defined for index type' => [
				'maxShardsPerNode' => [ 'content' => 1 ],
				'cluster' => 'default',
				'indexType' => 'content',
				'expect' => 1,
			],
			'defined for other index type' => [
				'maxShardsPerNode' => [ 'general' => 1 ],
				'cluster' => 'default',
				'indexType' => 'content',
				'expect' => -1,
			],
			'defined per cluster (1/2)' => [
				'maxShardsPerNode' => [
					'cluster1' => [ 'content' => 3 ],
					'cluster2' => [ 'content' => 1 ],
				],
				'cluster' => 'cluster1',
				'indexType' => 'content',
				'expect' => 3,
			],

			'defined per cluster (2/2)' => [
				'maxShardsPerNode' => [
					'cluster1' => [ 'content' => 3 ],
					'cluster2' => [ 'content' => 1 ],
				],
				'cluster' => 'cluster2',
				'indexType' => 'content',
				'expect' => 1,
			],
			'mixed per-cluster and global defaults (1/2)' => [
				'maxShardsPerNode' => [
					'cluster1' => [ 'content' => 3 ],
					'content' => 1,
				],
				'cluster' => 'cluster1',
				'indexType' => 'content',
				'expect' => 3,
			],
			'mixed per-cluster and global defaults (2/2)' => [
				'maxShardsPerNode' => [
					// Fully defined, with cluster + indexName, must take precedence
					'cluster1' => [ 'content' => 3 ],
					'content' => 1,
				],
				'cluster' => 'cluster1',
				'indexType' => 'content',
				'expect' => 3,
			],
		];
	}

	/**
	 * @dataProvider provideMaxShardsPerNode
	 */
	public function testGetMaxShardsPerNode( $maxShardsPerNode, $cluster, $indexType, $expect ) {
		$config = $this->getMockBuilder( SearchConfig::class )
			->disableOriginalConstructor()
			->getMock();
		$config->expects( $this->any() )
			->method( 'get' )
			->with( 'CirrusSearchMaxShardsPerNode' )
			->will( $this->returnValue( $maxShardsPerNode ) );

		$settings = new ClusterSettings( $config, $cluster );
		$this->assertEquals( $expect, $settings->getMaxShardsPerNode( $indexType ) );
	}

	public static function provideDropDelayedJobsAfter() {
		return [
			'Simple integer timeout is returned directly' => [
				60, 'dc-foo', 60
			],
			'Can set per-cluster timeout' => [
				[ 'dc-foo' => 99, 'labsearch' => 42 ],
				'labsearch',
				42
			],
		];
	}

	/**
	 * @dataProvider provideDropDelayedJobsAfter()
	 */
	public function testDropDelayedJobsAfter( $timeout, $cluster, $expect ) {
		$config = $this->getMockBuilder( SearchConfig::class )
			->disableOriginalConstructor()
			->getMock();
		$config->expects( $this->any() )
			->method( 'get' )
			->with( 'CirrusSearchDropDelayedJobsAfter' )
			->will( $this->returnValue( $timeout ) );

		$settings = new ClusterSettings( $config, $cluster );
		$this->assertEquals( $expect, $settings->getDropDelayedJobsAfter() );
	}

	public static function provideIsPrivate() {
		return [
			'null allows all' => [
				'expected' => true,
				'cluster' => 'dc.a',
				'privateClusters' => null,
			],
			'listed clusters are private' => [
				'expected' => true,
				'cluster' => 'dc.a',
				'privateClusters' => [ 'dc.a', 'dc.b' ],
			],
			'unlisted clusters are not private' => [
				'expected' => false,
				'cluster' => 'unk',
				'privateClusters' => [ 'dc.a', 'dc.b' ],
			],
		];
	}

	/**
	 * @dataProvider provideIsPrivate
	 */
	public function testIsPrivate( $expected, $cluster, $privateClusters ) {
		$config = new HashSearchConfig( [
			'CirrusSearchPrivateClusters' => $privateClusters,
		] );
		$settings = new ClusterSettings( $config, $cluster );
		$this->assertEquals( $expected, $settings->isPrivateCluster() );
	}
}
