<?php

namespace CirrusSearch;

use CirrusSearch\Job\ElasticaDocumentsJsonSerde;
use CirrusSearch\Search\CirrusIndexField;
use Elastica\Bulk\ResponseSet;
use Elastica\Client;
use Elastica\Document;
use Elastica\Response;

/**
 * Test Updater methods
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @group CirrusSearch
 * @covers \CirrusSearch\DataSender
 */
class DataSenderTest extends CirrusIntegrationTestCase {
	/**
	 * @dataProvider provideDocs
	 */
	public function testSuperNoopExtraHandlers( array $rawDoc, array $hints, array $extraHandlers, array $expectedParams ) {
		$config = $this->buildConfig( $extraHandlers );
		$conn = new Connection( $config );
		$updater = new DataSender( $conn, $config );
		$doc = $this->builDoc( $rawDoc, $hints );
		$script = $updater->docToSuperDetectNoopScript( $doc );
		$this->assertEquals( 'super_detect_noop', $script->getLang() );
		$this->assertEquals( $expectedParams['handlers'], $script->getParams()['handlers'] );
		$this->assertEquals( $expectedParams['_source'], $script->getParams()['source'] );
	}

	public static function provideDocs() {
		return [
			'simple' => [
				[
					123 => [ 'title' => 'test' ]
				],
				[
					'incoming_links' => 'within 20%',
				],
				[
					'labels' => 'equals',
					'version' => 'documentVersion',
				],
				[
					'handlers' => [
						'incoming_links' => 'within 20%',
						'labels' => 'equals',
						'version' => 'documentVersion',
					],
					'_source' => [
						'title' => 'test',
					],
				],
			],
			'do not override' => [
				[
					123 => [ 'title' => 'test' ]
				],
				[
					'incoming_links' => 'within 20%',
				],
				[
					'labels' => 'equals',
					'version' => 'documentVersion',
					'incoming_links' => 'within 30%',
				],
				[
					'handlers' => [
						'incoming_links' => 'within 20%',
						'labels' => 'equals',
						'version' => 'documentVersion',
					],
					'_source' => [
						'title' => 'test',
					],
				],
			],
			'no hints' => [
				[
					123 => [ 'title' => 'test' ]
				],
				[],
				[
					'labels' => 'equals',
					'version' => 'documentVersion',
					'incoming_links' => 'within 30%',
				],
				[
					'handlers' => [
						'incoming_links' => 'within 30%',
						'labels' => 'equals',
						'version' => 'documentVersion',
					],
					'_source' => [
						'title' => 'test',
					],
				],
			],
		];
	}

	private function buildConfig( array $extraHandlers ) {
		return new HashSearchConfig( [
			'CirrusSearchWikimediaExtraPlugin' => [
				'super_detect_noop' => true,
				'super_detect_noop_handlers' => $extraHandlers,
			],
		], [ HashSearchConfig::FLAG_INHERIT ] );
	}

	private function builDoc( array $doc, array $hints ) {
		$doc = new \Elastica\Document( key( $doc ), reset( $doc ) );
		foreach ( $hints as $f => $h ) {
			CirrusIndexField::addNoopHandler( $doc, $f, $h );
		}
		return $doc;
	}

	public function provideTestSendDataRequest() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'dataSender/sendData-*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, - strlen( '.config' ) );
			$fixture = CirrusIntegrationTestCase::loadFixture( $testFile );
			$expectedFile = dirname( $testFile ) . "/$testName.expected";
			$tests[$testName] = [
				$fixture['config'],
				$fixture['indexType'],
				$fixture['documents'],
				$expectedFile,
			];
		}
		return $tests;
	}

	/**
	 * @dataProvider provideTestSendDataRequest
	 */
	public function testSendDataRequest( array $config, $indexType, array $documents, $expectedFile ) {
		$minimalSetup = [
			'CirrusSearchClusters' => [
				'default' => [ 'localhost' ]
			],
			'CirrusSearchReplicaGroup' => 'default',
			'CirrusSearchUpdateConflictRetryCount' => 5,
		];
		$searchConfig = new HashSearchConfig( $config + $minimalSetup );
		$serde = new ElasticaDocumentsJsonSerde();
		$documents = $serde->deserialize( $documents );
		$mockClient = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->setProxyTarget( new Client( [ 'connections' => [] ] ) )
			->setMethods( [ 'request' ] )
			->getMock();

		$mockClient->expects( $this->once() )
			->method( 'request' )
			->will( $this->returnCallback(
				function ( $path, $method, $data, $params, $contentType ) use ( $documents, $expectedFile ) {
					$actual = [
						'path' => $path,
						'method' => $method,
						'data' => $this->unBulkify( $data ),
						'params' => $params,
						'contentType' => $contentType,
					];
					$this->assertFileContains(
						CirrusIntegrationTestCase::fixturePath( $expectedFile ),
						CirrusIntegrationTestCase::encodeFixture( $actual ),
						self::canRebuildFixture()
					);
					$responses = array_map(
						function ( Document $d ) {
							return new Response( [ 'result' => 'updated', 200 ] );
						},
						$documents
					);
					return new ResponseSet( new Response( [], 200 ), $responses );
				}
			) );

		$mockCon = $this->getMockBuilder( Connection::class )
			->disableOriginalConstructor()
			->setProxyTarget( new Connection( $searchConfig, 'default' ) )
			->setMethods( [ 'getClient' ] )
			->getMock();
		$mockCon->expects( $this->atLeastOnce() )
			->method( 'getClient' )
			->willReturn( $mockClient );
		$sender = new DataSender( $mockCon, $searchConfig, function () {
			return true;
		} );
		$sender->sendData( $indexType, $documents );
	}

	public function provideTestSendDeletesRequest() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'dataSender/sendDeletes-request-*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, - strlen( '.config' ) );
			$fixture = CirrusIntegrationTestCase::loadFixture( $testFile );
			$expectedFile = dirname( $testFile ) . "/$testName.expected";
			$tests[$testName] = [
				$fixture['config'],
				$fixture['indexType'],
				$fixture['ids'],
				$expectedFile,
			];
		}
		return $tests;
	}

	/**
	 * @dataProvider provideTestSendDeletesRequest
	 */
	public function testSendDeletesRequest( array $config, $indexType, array $ids, $expectedFile ) {
		$minimalSetup = [
			'CirrusSearchClusters' => [
				'default' => [ 'localhost' ]
			],
			'CirrusSearchReplicaGroup' => 'default',
		];
		$searchConfig = new HashSearchConfig( $config + $minimalSetup );
		$serde = new ElasticaDocumentsJsonSerde();
		$mockClient = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->setProxyTarget( new Client( [ 'connections' => [] ] ) )
			->setMethods( [ 'request' ] )
			->getMock();

		$mockClient->expects( $this->once() )
			->method( 'request' )
			->will( $this->returnCallback(
				function ( $path, $method, $data, $params, $contentType ) use ( $ids, $expectedFile ) {
					$actual = [
						'path' => $path,
						'method' => $method,
						'data' => $this->unBulkify( $data ),
						'params' => $params,
						'contentType' => $contentType,
					];
					$this->assertFileContains(
						CirrusIntegrationTestCase::fixturePath( $expectedFile ),
						CirrusIntegrationTestCase::encodeFixture( $actual ),
						self::canRebuildFixture()
					);
					$responses = array_map(
						function ( $d ) {
							return new Response( [ 'result' => 'updated', 200 ] );
						},
						$ids
					);
					return new ResponseSet( new Response( [], 200 ), $responses );
				}
			) );

		$mockCon = $this->getMockBuilder( Connection::class )
			->disableOriginalConstructor()
			->setProxyTarget( new Connection( $searchConfig, 'default' ) )
			->setMethods( [ 'getClient' ] )
			->getMock();
		$mockCon->expects( $this->atLeastOnce() )
			->method( 'getClient' )
			->willReturn( $mockClient );
		$sender = new DataSender( $mockCon, $searchConfig, function () {
			return true;
		} );
		$sender->sendDeletes( $ids, $indexType );
	}

	public function provideTestSendOtherIndexUpdatesRequest() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'dataSender/sendOtherIndexUpdates-request-*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, - strlen( '.config' ) );
			$fixture = CirrusIntegrationTestCase::loadFixture( $testFile );
			$expectedFile = dirname( $testFile ) . "/$testName.expected";
			$tests[$testName] = [
				$fixture['config'],
				$fixture['localSite'],
				$fixture['indexName'],
				$fixture['batchSize'],
				$fixture['actions'],
				$expectedFile,
			];
		}
		return $tests;
	}

	/**
	 * @dataProvider provideTestSendOtherIndexUpdatesRequest
	 */
	public function testSendOtherIndexUpdatesRequest( array $config, $localSite, $indexName, $batchSize, array $actions, $expectedFile ) {
		$minimalSetup = [
			'CirrusSearchClusters' => [
				'default' => [ 'localhost' ]
			],
			'CirrusSearchReplicaGroup' => 'default',
		];
		$searchConfig = new HashSearchConfig( $config + $minimalSetup );
		$mockClient = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->setProxyTarget( new Client( [ 'connections' => [] ] ) )
			->setMethods( [ 'request' ] )
			->getMock();

		$actualCalls = [];
		$mockClient->expects( $this->exactly( count( array_chunk( $actions, $batchSize ) ) ) )
			->method( 'request' )
			->will( $this->returnCallback(
				function ( $path, $method, $data, $params, $contentType ) use ( &$actualCalls ) {
					$lines = $this->unBulkify( $data );
					$actualCalls[] = [
						'path' => $path,
						'method' => $method,
						'data' => $lines,
						'params' => $params,
						'contentType' => $contentType,
					];
					$responses = array_map(
						function ( $d ) {
							return new Response( [ 'result' => 'updated', 200 ] );
						},
						range( 0, count( $lines ) / 2 )
					);
					return new ResponseSet( new Response( [], 200 ), $responses );
				}
			) );

		$mockCon = $this->getMockBuilder( Connection::class )
			->disableOriginalConstructor()
			->setProxyTarget( new Connection( $searchConfig, 'default' ) )
			->setMethods( [ 'getClient' ] )
			->getMock();
		$mockCon->expects( $this->atLeastOnce() )
			->method( 'getClient' )
			->willReturn( $mockClient );
		$sender = new DataSender( $mockCon, $searchConfig, function () {
			return true;
		} );
		$sender->sendOtherIndexUpdates( $localSite, $indexName, $actions, $batchSize );

		$merged = [];
		foreach ( $actualCalls as $nb => $actualCall ) {
			if ( isset( $merged['path'] ) ) {
				foreach ( [ 'path', 'method', 'params', 'contentType' ] as $k ) {
					$this->assertEquals( $merged[$k], $actualCall[$k], "Bulk message $nb has same value for $k the the first bulk" );
				}
				$merged['data'][] = $actualCall['data'];
			} else {
				$merged = $actualCall;
				$merged['data'] = [ $actualCall['data'] ];
			}
		}
		$this->assertFileContains(
			CirrusIntegrationTestCase::fixturePath( $expectedFile ),
			CirrusIntegrationTestCase::encodeFixture( $merged ),
			self::canRebuildFixture()
		);
	}

	private function unBulkify( $data ) {
		return array_map(
			function ( $d ) {
				return json_decode( $d, true );
			},
			array_slice( explode( "\n", $data ), 0, -1 )
		);
	}
}
