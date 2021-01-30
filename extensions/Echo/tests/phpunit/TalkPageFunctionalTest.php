<?php

/**
 * @group Echo
 * @group Database
 * @group medium
 */
class EchoTalkPageFunctionalTest extends ApiTestCase {

	protected function setUp() : void {
		parent::setUp();
		$this->db->delete( 'echo_event', '*' );
	}

	/**
	 * Creates and updates a user talk page a few times to ensure proper events are
	 * created. The user performing the edits is self::$users['sysop'].
	 * @covers \EchoDiscussionParser
	 */
	public function testAddCommentsToTalkPage() {
		$talkPage = self::$users['uploader']->getUser()->getName();

		$messageCount = 0;
		$this->assertCount( $messageCount, $this->fetchAllEvents() );

		// Start a talkpage
		$content = "== Section 8 ==\n\nblah blah ~~~~\n";
		$this->editPage( $talkPage, $content, '', NS_USER_TALK );

		// Ensure the proper event was created
		$events = $this->fetchAllEvents();
		// +1 is due to 0 index
		$this->assertCount( 1 + $messageCount, $events, 'After initial edit a single event must exist.' );
		$row = array_shift( $events );
		$this->assertEquals( 'edit-user-talk', $row->event_type );
		$this->assertEventSectionTitle( 'Section 8', $row );

		// Add another message to the talk page
		$messageCount++;
		$content .= "More content ~~~~\n";
		$this->editPage( $talkPage, $content, '', NS_USER_TALK );

		// Ensure another event was created
		$events = $this->fetchAllEvents();
		$this->assertCount( 1 + $messageCount, $events );
		$row = array_shift( $events );
		$this->assertEquals( 'edit-user-talk', $row->event_type );
		$this->assertEventSectionTitle( 'Section 8', $row );

		// Add a new section and a message within it
		$messageCount++;
		$content .= "\n\n== EE ==\n\nhere we go with a new section ~~~~\n";
		$this->editPage( $talkPage, $content, '', NS_USER_TALK );

		// Ensure this event has the new section title
		$events = $this->fetchAllEvents();
		$this->assertCount( 1 + $messageCount, $events );
		$row = array_pop( $events );
		$this->assertEquals( 'edit-user-talk', $row->event_type );
		$this->assertEventSectionTitle( 'EE', $row );
	}

	protected function assertEventSectionTitle( $sectionTitle, $row ) {
		$this->assertNotNull( $row->event_extra, 'Event must contain extra data.' );
		$extra = unserialize( $row->event_extra );
		$this->assertArrayHasKey( 'section-title', $extra, 'Extra data must include a section-title key.' );
		$this->assertEquals( $sectionTitle, $extra['section-title'], 'Detected section title must match' );
	}

	/**
	 * @return \stdClass[] All non-watchlist events in db sorted from oldest to newest
	 */
	protected function fetchAllEvents() {
		$res = $this->db->select( 'echo_event', EchoEvent::selectFields(), [
				'event_type != "watchlist-change"'
			], __METHOD__, [ 'ORDER BY' => 'event_id ASC' ] );

		return iterator_to_array( $res );
	}

}
