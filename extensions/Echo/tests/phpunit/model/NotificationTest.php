<?php

/**
 * @covers \EchoNotification
 */
class EchoNotificationTest extends MediaWikiTestCase {

	public function testNewFromRow() {
		$row = $this->mockNotificationRow() + $this->mockEventRow();

		$notif = EchoNotification::newFromRow( (object)$row );
		$this->assertInstanceOf( EchoNotification::class, $notif );
		// getReadTimestamp() should return null
		$this->assertNull( $notif->getReadTimestamp() );
		$this->assertEquals(
			$notif->getTimestamp(),
			wfTimestamp( TS_MW, $row['notification_timestamp'] )
		);
		$this->assertInstanceOf( EchoEvent::class, $notif->getEvent() );
		$this->assertNull( $notif->getTargetPages() );

		// Provide a read timestamp
		$row['notification_read_timestamp'] = time() + 1000;
		$notif = EchoNotification::newFromRow( (object)$row );
		// getReadTimestamp() should return the timestamp in MW format
		$this->assertEquals(
			$notif->getReadTimestamp(),
			wfTimestamp( TS_MW, $row['notification_read_timestamp'] )
		);

		$notif = EchoNotification::newFromRow( (object)$row, [
			EchoTargetPage::newFromRow( (object)$this->mockTargetPageRow() )
		] );
		$this->assertNotEmpty( $notif->getTargetPages() );
		foreach ( $notif->getTargetPages() as $targetPage ) {
			$this->assertInstanceOf( EchoTargetPage::class, $targetPage );
		}
	}

	public function testNewFromRowWithException() {
		$row = $this->mockNotificationRow();
		// Provide an invalid event id
		$row['notification_event'] = -1;
		$this->expectException( MWException::class );
		EchoNotification::newFromRow( (object)$row );
	}

	/**
	 * Mock a notification row from database
	 */
	protected function mockNotificationRow() {
		return [
			'notification_user' => 1,
			'notification_event' => 1,
			'notification_timestamp' => time(),
			'notification_read_timestamp' => '',
			'notification_bundle_hash' => 'testhash',
		];
	}

	/**
	 * Mock an event row from database
	 */
	protected function mockEventRow() {
		return [
			'event_id' => 1,
			'event_type' => 'test_event',
			'event_variant' => '',
			'event_extra' => '',
			'event_page_id' => '',
			'event_agent_id' => '',
			'event_agent_ip' => '',
			'event_deleted' => 0,
		];
	}

	/**
	 * Mock a target page row
	 */
	protected function mockTargetPageRow() {
		return [
			'etp_page' => 2,
			'etp_event' => 1
		];
	}

}
