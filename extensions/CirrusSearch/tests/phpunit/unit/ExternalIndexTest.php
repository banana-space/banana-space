<?php

namespace CirrusSearch;

/**
 * @covers \CirrusSearch\ExternalIndex
 */
class ExternalIndexTest extends CirrusTestCase {

	public function testGetSearchIndex() {
		$config = new HashSearchConfig( [ 'CirrusSearchReplicaGroup' => 'default' ] );
		$idx = new ExternalIndex( $config, 'foo' );
		$this->assertEquals( 'foo', $idx->getSearchIndex( $config->getClusterAssignment()->getCrossClusterName() ) );
		$this->assertEquals( 'default:foo', $idx->getSearchIndex( 'custom' ) );

		$idx = new ExternalIndex( $config, 'custom:foo' );
		$this->assertEquals( 'foo', $idx->getSearchIndex( 'custom' ) );
		$this->assertEquals( 'custom:foo', $idx->getSearchIndex( 'default' ) );

		$idx = new ExternalIndex( $config, 'default:foo' );
		$this->assertEquals( 'default:foo', $idx->getSearchIndex( 'custom' ) );
		$this->assertEquals( 'foo', $idx->getSearchIndex( 'default' ) );
	}

	public function testIsClusterBlacklisted() {
		$config = new HashSearchConfig( [
			'CirrusSearchReplicaGroup' => 'default',
			'CirrusSearchExtraIndexClusterBlacklist' => [
				'test' => [ 'foo' => true ]
			]
		] );
		$idx = new ExternalIndex( $config, 'test' );
		$this->assertTrue( $idx->isClusterBlacklisted( 'foo' ) );
		$this->assertFalse( $idx->isClusterBlacklisted( 'bar' ) );

		$config = new HashSearchConfig( [
			'CirrusSearchReplicaGroup' => 'custom_group',
			'CirrusSearchExtraIndexClusterBlacklist' => [
				'test' => [ 'foo' => true ]
			]
		] );

		$idx = new ExternalIndex( $config, 'test' );
		$this->assertTrue( $idx->isClusterBlacklisted( 'foo' ) );
		$this->assertFalse( $idx->isClusterBlacklisted( 'bar' ) );

		$idx = new ExternalIndex( $config, 'custom_group:test' );
		$this->assertTrue( $idx->isClusterBlacklisted( 'foo' ) );
		$this->assertFalse( $idx->isClusterBlacklisted( 'bar' ) );
	}

	public function getWriteClustersProvider() {
		$tests = [];

		// Current wiki writes to cluster `1` in datacenters `a` and `b`
		$config = [
			'CirrusSearchClusters' => [
				'DCA-group1' => [
					'replica' => 'DCA',
					'group' => 'group1',
					[]
				],
				'DCB-group1' => [
					'replica' => 'DCB',
					'group' => 'group1',
					[]
				],
				'DCA-group2' => [
					'replica' => 'DCA',
					'group' => 'group2',
					[]
				],
				'DCB-default' => [
					'replica' => 'DCB',
					'group' => 'group2',
					[]
				],
			],
			'CirrusSearchReplicaGroup' => 'group1',
			'CirrusSearchWriteClusters' => [ 'DCA', 'DCB' ],
			'CirrusSearchExtraIndex' => [
				NS_FILE => "group2:unittest"
			],
		];
		$assertions = [
			[ 'source' => 'group2', 'target' => null ],
			[ 'source' => 'group1', 'target' => 'group2' ],
			[ 'source' => 'random', 'target' => 'group2' ],
		];

		foreach ( $assertions as $testCase ) {
			$tests[] = [ $config, $testCase['source'], $testCase['target'] ];
		}

		return $tests;
	}

	/**
	 * @dataProvider getWriteClustersProvider
	 */
	public function testGetWriteClusters( $config, $sourceCluster, $targetCluster ) {
		$config = new HashSearchConfig( [ 'CirrusSearchReplicaGroup' => $sourceCluster ],
			[ HashSearchConfig::FLAG_INHERIT ], new HashSearchConfig( $config ) );
		$idx = new ExternalIndex( $config, $config->getElement( 'CirrusSearchExtraIndex', NS_FILE ) );
		$this->assertEquals( $targetCluster, $idx->getCrossClusterName() );
	}

	public function getBoostsProvider() {
		return [
			'unconfigured' => [ '', [], [] ],
			'configured for different index' => [ '', [], [
				'notme' => [ 'wiki' => 'otherwiki', 'boosts' => [ 'Zomg' => 0.44 ] ],
			] ],
			'configured for this index' => [ 'otherwiki', [ 'Zomg' => 0.44 ], [
				'testindex' => [ 'wiki' => 'otherwiki', 'boosts' => [ 'Zomg' => 0.44 ] ],
			] ],
		];
	}

	/**
	 * @dataProvider getBoostsProvider
	 */
	public function testGetBoosts( $expectedWiki, $expectedBoosts, $boostConfig ) {
		$config = new HashSearchConfig( [
			'CirrusSearchExtraIndexBoostTemplates' => $boostConfig,
			'CirrusSearchReplicaGroup' => 'default'
		] );
		$idx = new ExternalIndex( $config, 'testindex' );
		list( $wiki, $boosts ) = $idx->getBoosts();
		$this->assertEquals( $expectedWiki, $wiki );
		$this->assertEquals( $expectedBoosts, $boosts );
	}
}
