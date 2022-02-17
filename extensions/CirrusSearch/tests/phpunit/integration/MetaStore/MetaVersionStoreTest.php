<?php

namespace CirrusSearch\MetaStore;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\Connection;
use MediaWiki\MediaWikiServices;

/**
 * Mostly stupid happy path tests. :(
 *
 * @covers \CirrusSearch\MetaStore\MetaVersionStore
 */
class MetaVersionStoreTest extends CirrusIntegrationTestCase {
	public function testBuildDocument() {
		list( $conn, $type ) = $this->mockConnection();
		$doc = MetaVersionStore::buildDocument( $conn, wfWikiId(), 'content' );
		$this->assertEquals( MetaVersionStore::METASTORE_TYPE, $doc->get( 'type' ) );
	}

	public function testUpdate() {
		list( $conn, $type ) = $this->mockConnection();
		$store = new MetaVersionStore( $conn );
		$doc = null;
		$type->expects( $this->once() )
			->method( 'addDocument' )
			->will( $this->returnCallback( function ( $arg ) use ( &$doc ) {
				$doc = $arg;
			} ) );

		$store->update( 'unittest', 'general' );
		$this->assertInstanceOf( \Elastica\Document::class, $doc );
	}

	public function testUpdateAll() {
		list( $conn, $type ) = $this->mockConnection();
		$store = new MetaVersionStore( $conn );
		$type->expects( $this->never() )
			->method( 'addDocument' );
		$type->expects( $this->once() )
			->method( 'addDocuments' )
			->will( $this->returnCallback( function ( $docs ) {
				$this->assertCount( 3, $docs );
			} ) );
		$store->updateAll( 'unittest' );
	}

	public function testBuildIndexProperties() {
		list( $conn, $type ) = $this->mockConnection();
		$store = new MetaVersionStore( $conn );
		$properties = $store->buildIndexProperties();
		// TODO: Would be nice to have some sort of check that these
		// are valid to elasticsearch. But thats more on integration
		// testing again
		$this->assertIsArray( $properties );
	}

	public function testFind() {
		list( $conn, $type ) = $this->mockConnection();
		$store = new MetaVersionStore( $conn );
		$type->expects( $this->once() )
			->method( 'getDocument' )
			->with( 'version-unittest_content' );
		$store->find( 'unittest', 'content' );
	}

	public function testFindAll() {
		list( $conn, $type ) = $this->mockConnection();
		$store = new MetaVersionStore( $conn );
		$search = null;
		$type->expects( $this->any() )
			->method( 'search' )
			->will( $this->returnCallback( function ( $passed ) use ( &$search ) {
				$search = $passed;
			} ) );
		// What can we really test? Feels more like integration
		// testing that needs the elasticsearch cluster. Or we
		// could VCR some results but they will change regularly
		$store->findAll();
		$this->assertNotNull( $search );

		$search = null;
		$store->findAll( 'unittest' );
		$this->assertNotNull( $search );
	}

	private function mockConnection( $returnAll = false ) {
		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'CirrusSearch' );
		$conn = $this->getMockBuilder( Connection::class )
			->setConstructorArgs( [ $config ] )
			// call real connection on unmocked methods
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

		$type->expects( $this->any() )
			->method( 'exists' )
			->will( $this->returnValue( true ) );

		return [ $conn, $type ];
	}
}
