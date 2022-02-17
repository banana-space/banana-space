<?php

namespace CirrusSearch\Elastica;

use CirrusSearch\CirrusTestCase;
use Elastica\Client;
use Elastica\Index;
use Elastica\Request;
use Elastica\Response;
use Elastica\Type;

/**
 * @covers \CirrusSearch\Elastica\ReindexRequest
 */
class ReindexRequestTest extends CirrusTestCase {
	public function testAcceptsIndexSourceAndDest() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();
		$sourceIndex = new Index( $client, 'source_idx' );
		$destIndex = new Index( $client, 'dest_idx' );

		$req = new ReindexRequest( $sourceIndex, $destIndex );
		$this->assertEquals( [
			'source' => [
				'index' => 'source_idx',
				'size' => 100,
			],
			'dest' => [
				'index' => 'dest_idx',
			],
		], $req->toArray() );
	}

	public function testAcceptsTypeSourceAndDest() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();
		$sourceType = new Type( new Index( $client, 'source_idx' ), 'source_type' );
		$destType = new Type( new Index( $client, 'dest_idx' ), 'dest_type' );
		$req = new ReindexRequest( $sourceType, $destType );
		$this->assertEquals( [
			'source' => [
				'index' => 'source_idx',
				'type' => 'source_type',
				'size' => 100,
			],
			'dest' => [
				'index' => 'dest_idx',
				'type' => 'dest_type',
			]
		], $req->toArray() );
	}

	public function testOneSliceByDefault() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();
		$sourceIndex = new Index( $client, 'source_idx' );
		$destIndex = new Index( $client, 'dest_idx' );
		$req = new ReindexRequest( $sourceIndex, $destIndex );

		$client->expects( $this->once() )
			->method( 'request' )
			->with( '_reindex', Request::POST, $req->toArray(), [
				'slices' => 1,
				'requests_per_second' => -1,
			] )
			->will( $this->returnValue( new Response( '{}', 200 ) ) );

		$this->assertInstanceOf( ReindexResponse::class, $req->reindex() );
	}

	public function testSlicesAreConfigurable() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();
		$sourceIndex = new Index( $client, 'source_idx' );
		$destIndex = new Index( $client, 'dest_idx' );
		$req = new ReindexRequest( $sourceIndex, $destIndex );
		$req->setSlices( 12 );

		$client->expects( $this->once() )
			->method( 'request' )
			->with( '_reindex', Request::POST, $req->toArray(), [
				'slices' => 12,
				'requests_per_second' => -1,
			] )
			->will( $this->returnValue( new Response( '{}', 200 ) ) );

		$this->assertInstanceOf( ReindexResponse::class, $req->reindex() );
	}

	public function setRequestsPerSecondIsConfigurable() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();
		$sourceIndex = new Index( $client, 'source_idx' );
		$destIndex = new Index( $client, 'dest_idx' );
		$req = new ReindexRequest( $sourceIndex, $destIndex );
		$req->setRequestsPerSecond( 42 );

		$client->expects( $this->once() )
			->method( 'request' )
			->with( '_reindex', Request::POST, $req->toArray(), [
				'slices' => 12,
				'requests_per_second' => 42,
			] )
			->will( $this->returnValue( new Response( '{}', 200 ) ) );

		$this->assertInstanceOf( ReindexResponse::class, $req->reindex() );
	}

	public function testReindexTask() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();
		$sourceIndex = new Index( $client, 'source_idx' );
		$destIndex = new Index( $client, 'dest_idx' );
		$req = new ReindexRequest( $sourceIndex, $destIndex );

		$client->expects( $this->once() )
			->method( 'request' )
			->with( '_reindex', Request::POST, $req->toArray(), [
				'slices' => 1,
				'requests_per_second' => -1,
				'wait_for_completion' => 'false',
			] )
			->will( $this->returnValue( new Response( '{"task": "qwerty:4321"}', 200 ) ) );

		$task = $req->reindexTask();
		$this->assertInstanceOf( ReindexTask::class, $task );
		$this->assertEquals( "qwerty:4321", $task->getId() );
	}

	public function testProvideScript() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();
		$sourceIndex = new Index( $client, 'source_idx' );
		$destIndex = new Index( $client, 'dest_idx' );
		$req = new ReindexRequest( $sourceIndex, $destIndex );
		$req->setScript( [
			'lang' => 'painless',
			'inline' => 'fofofo;'
		] );

		$this->assertEquals( [
			'source' => [
				'index' => 'source_idx',
				'size' => 100,
			],
			'dest' => [
				'index' => 'dest_idx',
			],
			'script' => [
				'lang' => 'painless',
				'inline' => 'fofofo;',
			]
		], $req->toArray() );
	}

	public function setProvideRemoteInfo() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();
		$sourceIndex = new Index( $client, 'source_idx' );
		$destIndex = new Index( $client, 'dest_idx' );
		$req = new ReindexRequest( $sourceIndex, $destIndex );
		$req->setRemoteInfo( [
			'host' => 'http://otherhost:9200',
		] );
		$this->assertEquals( [
			'source' => [
				'index' => 'source_idx',
				'size' => 100,
				'remote' => [
					'host' => 'http://otherhost:9200'
				],
			],
			'dest' => [
				'index' => 'dest_idx',
			],
		], $req->toArray() );
	}

	public function testPerformsRequestAgainstDestinationCluster() {
		$sourceClient = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();
		$destClient = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();

		$sourceClient->expects( $this->never() )->method( 'request' );
		$destClient->expects( $this->once() )
			->method( 'request' )
			->will( $this->returnValue( new Response( '{}', 200 ) ) );
		$sourceIndex = new Index( $sourceClient, 'source_idx' );
		$destIndex = new Index( $destClient, 'dest_idx' );
		$req = new ReindexRequest( $sourceIndex, $destIndex );
		$this->assertInstanceOf( ReindexResponse::class, $req->reindex() );
	}
}
