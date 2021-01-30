<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EchoTitleLocalCache
 * @group Database
 */
class EchoTitleLocalCacheTest extends MediaWikiTestCase {

	public function testCreate() {
		$cache = EchoTitleLocalCache::create();
		$this->assertInstanceOf( EchoTitleLocalCache::class, $cache );
	}

	public function testAdd() {
		$cache = $this->getMockBuilder( EchoTitleLocalCache::class )
			->setMethods( [ 'resolve' ] )->getMock();

		$cache->add( 1 );
		$cache->add( 9 );

		// Resolutions should be batched
		$cache->expects( $this->exactly( 1 ) )->method( 'resolve' )
			->with( [ 1, 9 ] )->willReturn( [] );

		// Trigger
		$cache->get( 9 );
	}

	public function testGet() {
		$cache = $this->getMockBuilder( EchoTitleLocalCache::class )
			->setMethods( [ 'resolve' ] )->getMock();
		$cachePriv = TestingAccessWrapper::newFromObject( $cache );

		// First title included in cache
		$res1 = $this->insertPage( 'EchoTitleLocalCacheTest_testGet1' );
		$cachePriv->targets->set( $res1['id'], $res1['title'] );
		// Second title not in internal cache, resolves from db.
		$res2 = $this->insertPage( 'EchoTitleLocalCacheTest_testGet2' );
		$cache->expects( $this->exactly( 1 ) )->method( 'resolve' )
			->with( [ $res2['id'] ] )
			->willReturn( [ $res2['id'] => $res2['title'] ] );

		// Register demand for both
		$cache->add( $res1['id'] );
		$cache->add( $res2['id'] );

		// Should not call resolve() for first title
		$this->assertSame( $res1['title'], $cache->get( $res1['id'] ), 'First title' );

		// Should resolve() for second title
		$this->assertSame( $res2['title'], $cache->get( $res2['id'] ), 'Second title' );
	}

	public function testClearAll() {
		$cache = $this->getMockBuilder( EchoTitleLocalCache::class )
			->setMethods( [ 'resolve' ] )->getMock();

		// Add 1 to cache
		$cachePriv = TestingAccessWrapper::newFromObject( $cache );
		$cachePriv->targets->set( 1, $this->mockTitle() );
		// Add 2 and 3 to demand
		$cache->add( 2 );
		$cache->add( 3 );
		$cache->clearAll();

		$this->assertNull( $cache->get( 1 ), 'Cache was cleared' );

		// Lookups batch was cleared
		$cache->expects( $this->exactly( 1 ) )->method( 'resolve' )
			->with( [ 4 ] )
			->willReturn( [] );
		$cache->add( 4 );
		$cache->get( 4 );
	}

	/**
	 * @return Title
	 */
	protected function mockTitle() {
		$title = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->getMock();

		return $title;
	}
}
