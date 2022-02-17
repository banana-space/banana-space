<?php

namespace CirrusSearch;

use Title;

/**
 * @covers \CirrusSearch\OtherIndexesUpdater
 */
class OtherIndexesUpdaterTest extends CirrusIntegrationTestCase {

	public function getExternalIndexesProvider() {
		return [
			'empty config must return empty external indexes' => [
				[ 'Main_Page' => [] ],
				[],
			],
			'config for NS_FILE should only return values for NS_FILE' => [
				[
					'Main_Page' => [],
					'File:Foo' => [ 'zomg' ],
				],
				[ NS_FILE => [ 'zomg' ] ],
			],
		];
	}

	/**
	 * @covers \CirrusSearch\OtherIndexesUpdater::getExternalIndexes
	 * @dataProvider getExternalIndexesProvider
	 */
	public function testGetExternalIndexes( $assertions, $extraIndexes ) {
		$config = new HashSearchConfig( [
			'CirrusSearchExtraIndexes' => $extraIndexes,
			'CirrusSearchReplicaGroup' => 'default',
		] );

		foreach ( $assertions as $title => $expectedIndices ) {
			$found = array_map(
				function ( $other ) {
					return $other->getSearchIndex( 'default' );
				},
				OtherIndexesUpdater::getExternalIndexes( $config, Title::newFromText( $title ) )
			);

			$this->assertEquals( $expectedIndices, $found );
		}
	}

	public function getExtraIndexesForNamespaceProvider() {
		return [
			'Unconfigured does not issue warnings' => [
				[
					[ [ NS_MAIN ], [] ],
				],
				[]
			],
			'Includes configured namespaces' => [
				[
					[ [ NS_MAIN ], [] ],
					[ [ NS_MAIN, NS_FILE ], [ 'zomg' ] ],
					[ [ NS_FILE ], [ 'zomg' ] ],
				],
				[
					NS_FILE => [ 'zomg' ],
				]
			],
		];
	}

	/**
	 * @covers \CirrusSearch\OtherIndexesUpdater::getExtraIndexesForNamespaces
	 * @dataProvider getExtraIndexesForNamespaceProvider
	 */
	public function testGetExtraIndexesForNamespace( $assertions, $extraIndexes ) {
		$config = new HashSearchConfig( [
			'CirrusSearchExtraIndexes' => $extraIndexes,
			'CirrusSearchReplicaGroup' => 'default',
		] );

		foreach ( $assertions as $assertion ) {
			list( $namespaces, $indices ) = $assertion;
			$found = array_map(
				function ( $other ) {
					return $other->getSearchIndex( 'default' );
				},
				OtherIndexesUpdater::getExtraIndexesForNamespaces( $config, $namespaces )
			);
			$this->assertEquals( $indices, $found );
		}
	}

	public function testUpdateOtherIndex() {
		// multi
		$responseString = json_encode( [
			'responses' => [ [
				'took' => 1,
				'timed_out' => false,
				'hits' => [
					'total' => 1,
					'max_score' => 0,
					'hits' => [ [
						'_id' => 12345
					] ]
				]
			] ]
		] );
		$response = new \Elastica\Response( $responseString, 200 );
		$transport = $this->getMockBuilder( \Elastica\Transport\AbstractTransport::class )
			->disableOriginalConstructor()
			->getMock();
		$transport->expects( $this->any() )
			->method( 'exec' )
			->will( $this->returnValue( $response ) );

		$config = new HashSearchConfig( [
			'CirrusSearchWikimediaExtraPlugin' => [
				'super_detect_noop' => true,
			],
			'CirrusSearchReplicaGroup' => 'a',
			'CirrusSearchExtraIndexes' => [
				NS_MAIN => [ 'otherplace:phpunit_other_index' ],
			],
			'CirrusSearchDefaultCluster' => 'default',
			'CirrusSearchClusters' => [
				'default' => [
					[ 'transport' => $transport ],
				]
			],
		] );

		$conn = new Connection( $config );
		$oi = $this->getMockBuilder( OtherIndexesUpdater::class )
			->setConstructorArgs( [ $conn, $config, [], wfWikiId() ] )
			->setMethods( [ 'runUpdates' ] )
			->getMock();
		$oi->expects( $this->once() )
			->method( 'runUpdates' )
			->will( $this->returnCallback( function ( \Title $title, array $updates ) {
				$this->assertCount( 1, $updates );
				foreach ( $updates as $data ) {
					list( $otherIndex, $actions ) = $data;
					$this->assertIsArray( $actions );
					$this->assertCount( 1, $actions );
					$action = $actions[0];
					$this->assertArrayHasKey( 'docId', $action );
					$this->assertArrayHasKey( 'ns', $action );
					$this->assertArrayHasKey( 'dbKey', $action );
					$this->assertEquals( 'otherplace:phpunit_other_index', $otherIndex->getGroupAndIndexName() );
				}
			} ) );
		$oi->updateOtherIndex( [ Title::newMainPage() ] );
	}
}
