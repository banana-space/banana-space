<?php

/**
 * @covers \EchoTargetPage
 */
class EchoTargetPageTest extends MediaWikiUnitTestCase {

	public function testCreate() {
		$this->assertNull(
			EchoTargetPage::create(
				$this->mockTitle( 0 ),
				$this->mockEchoEvent()
			)
		);

		$this->assertInstanceOf(
			EchoTargetPage::class,
			EchoTargetPage::create(
				$this->mockTitle( 1 ),
				$this->mockEchoEvent()
			)
		);
	}

	/**
	 * @return EchoTargetPage
	 */
	public function testNewFromRow() {
		$row = (object)[
			'etp_page' => 2,
			'etp_event' => 3
		];
		$obj = EchoTargetPage::newFromRow( $row );
		$this->assertInstanceOf( EchoTargetPage::class, $obj );

		return $obj;
	}

	public function testNewFromRowWithException() {
		$row = (object)[
			'etp_event' => 3
		];
		$this->expectException( MWException::class );
		EchoTargetPage::newFromRow( $row );
	}

	/**
	 * @depends testNewFromRow
	 */
	public function testToDbArray( EchoTargetPage $obj ) {
		$row = $obj->toDbArray();
		$this->assertTrue( is_array( $row ) );

		// Not very common to assert that a field does _not_ exist
		// but since we are explicitly removing it, it seems to make sense.
		$this->assertArrayNotHasKey( 'etp_user', $row );

		$this->assertArrayHasKey( 'etp_page', $row );
		$this->assertArrayHasKey( 'etp_event', $row );
	}

	/**
	 * @return Title
	 */
	protected function mockTitle( $pageId ) {
		$event = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->getMock();
		$event->expects( $this->any() )
			->method( 'getArticleID' )
			->will( $this->returnValue( $pageId ) );

		return $event;
	}

	/**
	 * @return EchoEvent
	 */
	protected function mockEchoEvent( $eventId = 1 ) {
		$event = $this->getMockBuilder( EchoEvent::class )
			->disableOriginalConstructor()
			->getMock();
		$event->expects( $this->any() )
			->method( 'getId' )
			->will( $this->returnValue( $eventId ) );

		return $event;
	}

}
