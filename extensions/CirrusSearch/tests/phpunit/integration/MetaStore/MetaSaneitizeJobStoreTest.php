<?php

namespace CirrusSearch\MetaStore;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\Connection;
use MediaWiki\MediaWikiServices;

/**
 * @covers \CirrusSearch\MetaStore\MetaSaneitizeJobStore
 */
class MetaSaneitizeJobStoreTest extends CirrusIntegrationTestCase {
	public function testCreate() {
		list( $conn, $type ) = $this->mockConnection();
		$type->expects( $this->once() )
			->method( 'addDocument' );
		$store = new MetaSaneitizeJobStore( $conn );
		$doc = $store->create( 'foo', 2018 );
		$this->assertEquals( MetaSaneitizeJobStore::METASTORE_TYPE, $doc->get( 'type' ) );
	}

	public function testGetMissing() {
		list( $conn, $type ) = $this->mockConnection();
		$type->expects( $this->once() )
			->method( 'getDocument' )
			->will( $this->throwException(
				new \Elastica\Exception\NotFoundException() ) );

		$store = new MetaSaneitizeJobStore( $conn );
		$this->assertNull( $store->get( 'foo' ) );
	}

	public function testGet() {
		list( $conn, $type ) = $this->mockConnection();
		$type->expects( $this->once() )
			->method( 'getDocument' )
			->will( $this->returnValue( 'FOUND' ) );
		$store = new MetaSaneitizeJobStore( $conn );
		$this->assertEquals( 'FOUND', $store->get( 'foo' ) );
	}

	public function mockConnection() {
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'CirrusSearch' );
		$conn = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $config ] )
			->setMethods( [ 'getIndex' ] )
			->getMock();

		$index = $this->getMockBuilder( \Elastica\Index::class )
			->disableOriginalConstructor()
			->getMock();
		$conn->expects( $this->any() )
			->method( 'getIndex' )
			->with( MetaStoreIndex::INDEX_NAME )
			->will( $this->returnValue( $index ) );

		$type = $this->getMockBuilder( \Elastica\Type::class )
			->disableOriginalConstructor()
			->getMock();
		$index->expects( $this->any() )
			->method( 'getType' )
			->with( MetaStoreIndex::INDEX_NAME )
			->will( $this->returnValue( $type ) );

		return [ $conn, $type ];
	}
}
