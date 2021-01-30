<?php

/**
 * @covers \EchoContainmentSet
 * @group Echo
 * @group Database
 */
class ContainmentSetTest extends MediaWikiTestCase {

	public function testGenericContains() {
		$list = new EchoContainmentSet( self::getTestUser()->getUser() );

		$list->addArray( [ 'foo', 'bar' ] );
		$this->assertTrue( $list->contains( 'foo' ) );
		$this->assertTrue( $list->contains( 'bar' ) );
		$this->assertFalse( $list->contains( 'whammo' ) );

		$list->addArray( [ 'whammo' ] );
		$this->assertTrue( $list->contains( 'whammo' ) );

		$list->addArray( [ 0 ] );
		$this->assertFalse( $list->contains( 'baz' ) );
	}

	public function testCachedListInnerListIsOnlyCalledOnce() {
		$innerCache = new HashBagOStuff; // simulate caching
		$wanCache = new WANObjectCache( [ 'cache' => $innerCache ] );

		$inner = [ 'bing', 'bang' ];
		// We use a mock instead of the real thing for the $this->once() assertion
		// verifying that the cache doesn't just keep asking the inner object
		$list = $this->getMockBuilder( EchoArrayList::class )
			->disableOriginalConstructor()
			->getMock();
		$list->expects( $this->once() )
			->method( 'getValues' )
			->will( $this->returnValue( $inner ) );

		$cached = new EchoCachedList( $wanCache, 'test_key', $list );

		// First run through should hit the main list, and save to innerCache
		$this->assertEquals( $inner, $cached->getValues() );
		$this->assertEquals( $inner, $cached->getValues() );

		// Reinitialize to get a fresh instance that will pull directly from
		// innerCache without hitting the $list
		$freshCached = new EchoCachedList( $wanCache, 'test_key', $list );
		$this->assertEquals( $inner, $freshCached->getValues() );
	}

	/**
	 * @group Database
	 */
	public function testOnWikiList() {
		$this->editPage( 'User:Foo/Bar-baz', "abc\ndef\r\nghi\n\n\n" );

		$list = new EchoOnWikiList( NS_USER, "Foo/Bar-baz" );
		$this->assertEquals(
			[ 'abc', 'def', 'ghi' ],
			$list->getValues()
		);
	}

	public function testOnWikiListNonExistant() {
		$list = new EchoOnWikiList( NS_USER, "Some_Non_Existant_Page" );
		$this->assertEquals( [], $list->getValues() );
	}
}
