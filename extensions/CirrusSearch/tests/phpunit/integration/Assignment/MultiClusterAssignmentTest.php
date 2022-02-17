<?php

namespace CirrusSearch\Assignment;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\SearchConfig;

/**
 * @covers \CirrusSearch\Assignment\MultiClusterAssignment
 */
class MultiClusterAssignmentTest extends CirrusIntegrationTestCase {

	public function testSimpleConfig() {
		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'CirrusSearchClusters' => [
				'mycluster' => [ '127.0.0.1' ],
			],
			'CirrusSearchDefaultCluster' => 'mycluster',
			'CirrusSearchWriteClusters' => null,
			'CirrusSearchReplicaGroup' => 'default',
		] ) );
		$this->assertEquals( 'mycluster', $clusters->getSearchCluster() );
		$this->assertEquals( [ 'mycluster' ], $clusters->getWritableClusters() );
		$this->assertEquals( [ '127.0.0.1' ], $clusters->getServerList( 'mycluster' ) );
		// Should this throw exception? Cross cluster usage is invalid
		// with a single elasticsearch cluster, but it probably doesn't matter.
		$this->assertEquals( 'default', $clusters->getCrossClusterName() );
	}

	public function testGetServerListUnknownReplica() {
		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'CirrusSearchClusters' => [
				'mycluster' => [ '127.0.0.1' ],
			],
			'CirrusSearchDefaultCluster' => 'mycluster',
			'CirrusSearchWriteClusters' => null,
			'CirrusSearchReplicaGroup' => 'default',
		] ) );
		$this->expectException( \RuntimeException::class );
		$clusters->getServerList( 'catapult' );
	}

	public function testGetServerListSingleGroupReplica() {
		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'CirrusSearchClusters' => [
				'cluster_1.a' => [ 'replica' => 'cluster_1', 'group' => 'a', '127.0.0.1:9200' ],
				'cluster_1.b' => [ 'replica' => 'cluster_1', 'group' => 'b', '127.0.0.1:9201' ],
				'cluster_2' => [ '127.0.0.1:9202' ],
			],
			'CirrusSearchDefaultCluster' => 'mycluster',
			'CirrusSearchWriteClusters' => null,
			'CirrusSearchReplicaGroup' => 'a',
		] ) );
		$this->assertEquals( [ '127.0.0.1:9202' ], $clusters->getServerList( 'cluster_2' ) );
	}

	public function groupAssignmentProvider() {
		return [
			[
				'x', 'dc1', [ 'dc1' ],
				'x'
			],
			[
				'y', 'dc1', [ 'dc1' ],
				'y'
			],
			[
				'y', 'dc1', [ 'dc1' ],
				[ 'type' => 'constant', 'group' => 'y' ],
			],
		];
	}

	/**
	 * @dataProvider groupAssignmentProvider
	 */
	public function testGroupAssignment( $name, $search, $writable, $replicaGroup ) {
		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'CirrusSearchClusters' => [
				'x.dc1' => [ 'replica' => 'dc1', 'group' => 'x', 'x.dc1:9200' ],
				'y.dc1' => [ 'replica' => 'dc1', 'group' => 'y', 'y.dc1:9201' ],
			],
			'CirrusSearchDefaultCluster' => 'dc1',
			'CirrusSearchWriteClusters' => null,
			'CirrusSearchReplicaGroup' => $replicaGroup,
		] ) );

		$this->assertEquals( $name, $clusters->getCrossClusterName() );
		$this->assertEquals( $search, $clusters->getSearchCluster() );
		$this->assertEquals( $writable, $clusters->getWritableClusters() );
	}

	public function testMultipleGroupsRequiresReplicaGroupConfiguration() {
		$this->expectException( \RuntimeException::class );
		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'CirrusSearchClusters' => [
				'x.a' => [ 'replica' => 'a', 'group' => 'x', 'x.a:9200' ],
				'y.a' => [ 'replica' => 'a', 'group' => 'x', 'x.a:9201' ],
			],
			'CirrusSearchReplicaGroup' => null,
		] ) );
	}

	public function testNoDuplicateConfigs() {
		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'CirrusSearchClusters' => [
				'x.a' => [ 'replica' => 'a', 'group' => 'x', 'x.a:9200' ],
				'y.a' => [ 'replica' => 'a', 'group' => 'x', 'x.a:9201' ],
			],
			'CirrusSearchReplicaGroup' => 'x',
		] ) );
		// This isn't detected until we initialize the cluster config
		$this->expectException( \RuntimeException::class );
		$clusters->getServerList();
	}

	public function testReplicaGroupTypeMustExist() {
		$this->expectException( \RuntimeException::class );
		new MultiClusterAssignment( new HashSearchConfig( [
			'CirrusSearchClusters' => [
				'x.a' => [ 'replica' => 'a', 'group' => 'x', 'x.a:9200' ],
				'y.a' => [ 'replica' => 'a', 'group' => 'x', 'x.a:9201' ],
			],
			'CirrusSearchReplicaGroup' => [
				'type' => 'garbage',
				'groups' => [ 'x', 'y' ],
			],
		] ) );
	}

	public function testRoundRobin() {
		$defaults = [
			'CirrusSearchClusters' => [
				'x.a' => [ 'replica' => 'a', 'group' => 'x', 'x.a:9200' ],
				'y.a' => [ 'replica' => 'a', 'group' => 'y', 'y.a:9201' ],
			],
			'CirrusSearchDefaultCluster' => 'a',
			'CirrusSearchWriteClusters' => [ 'a' ],
			'CirrusSearchReplicaGroup' => [
				'type' => 'roundrobin',
				'groups' => [ 'x', 'y' ],
			],
		];
		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'_wikiID' => 'aawiki',
		] + $defaults ) );
		$this->assertEquals( 'y', $clusters->getCrossClusterName() );

		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'_wikiID' => 'bbwiki',
		] + $defaults ) );
		$this->assertEquals( 'y', $clusters->getCrossClusterName() );

		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'_wikiID' => 'ccwiki',
		] + $defaults ) );
		$this->assertEquals( 'x', $clusters->getCrossClusterName() );
	}

	public function testMultiDcMultiCluster() {
		$defaults = [
			'CirrusSearchClusters' => [
				'eqiad-a' => [ 'replica' => 'eqiad', 'group' => 'a', 'search.svc.eqiad.wmnet:9200' ],
				'eqiad-b' => [ 'replica' => 'eqiad', 'group' => 'b', 'search-b.svc.eqiad.wmnet:9201' ],
				'eqiad-c' => [ 'replica' => 'eqiad', 'group' => 'c', 'search-c.svc.eqiad.wmnet:9202' ],
				'codfw-a' => [ 'replica' => 'codfw', 'group' => 'a', 'search.svc.codfw.wmnet:9200' ],
				'codfw-b' => [ 'replica' => 'codfw', 'group' => 'b', 'search-b.svc.codfw.wmnet:9201' ],
				'codfw-c' => [ 'replica' => 'codfw', 'group' => 'c', 'search-c.svc.codfw.wmnet:9202' ],
				'cloud' => [ 'cloudsearch.svc.eqiad.wmnet:9200' ],
			],
			'CirrusSearchDefaultCluster' => 'eqiad',
			'CirrusSearchWriteClusters' => [ 'eqiad', 'codfw', 'cloud' ],
			// While prod use is likely round robin, it's much easier to test constants and roundrobin
			// is tested elsewhere.
			'CirrusSearchReplicaGroup' => 'b',
		];
		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'_wikiID' => 'aawiki',
		] + $defaults ) );

		$this->assertEquals( [ 'eqiad', 'codfw', 'cloud' ], $clusters->getWritableClusters() );
		$this->assertEquals( 'eqiad', $clusters->getSearchCluster() );
		$this->assertEquals( 'b', $clusters->getCrossClusterName() );
		$this->assertEquals( [ 'search-b.svc.eqiad.wmnet:9201' ], $clusters->getServerList() );
		$this->assertEquals( [ 'search-b.svc.eqiad.wmnet:9201' ], $clusters->getServerList( 'eqiad' ) );
		$this->assertEquals( [ 'search-b.svc.codfw.wmnet:9201' ], $clusters->getServerList( 'codfw' ) );
		$this->assertEquals( [ 'cloudsearch.svc.eqiad.wmnet:9200' ], $clusters->getServerList( 'cloud' ) );
		$this->assertNotEquals( $clusters->uniqueId( 'eqiad' ), $clusters->uniqueId( 'codfw' ) );
	}

	public function testWritableClusters() {
		$this->setMwGlobals( [
			'wgCirrusSearchClusters' => [
				'one' => [],
				'two' => [],
				'readonly' => [],
			],
			'wgCirrusSearchWriteClusters' => [ 'one', 'two', 'unknown' ]
		] );
		$config = new SearchConfig();
		// Unclear if it's right to not filter out with available cluster
		// ElasticaWrite should error out if the cluster is unknown tho.
		$assignment = $config->getClusterAssignment();
		$this->assertEquals( [ 'one', 'two', 'unknown' ], $assignment->getWritableClusters() );
		$this->assertTrue( $assignment->canWriteToCluster( 'one' ) );
		$this->assertTrue( $assignment->canWriteToCluster( 'unknown' ) );
		$this->assertFalse( $assignment->canWriteToCluster( 'readonly' ) );
	}

	public function testReplicasMustExist() {
		$clusters = new MultiClusterAssignment( new HashSearchConfig( [
			'CirrusSearchClusters' => [
				'phpunit' => [ 'group' => 'a' ]
			],
			'CirrusSearchReplicaGroup' => 'b',
		] ) );
		$this->expectException( \RuntimeException::class );
		$clusters->getServerList();
	}
}
