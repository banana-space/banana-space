<?php

/**
 * @group Echo
 * @group Database
 */
class MWEchoThankYouEditTest extends MediaWikiTestCase {

	protected function setUp() : void {
		parent::setUp();
		$this->tablesUsed[] = 'echo_event';
		$this->tablesUsed[] = 'echo_notification';
	}

	private function deleteEchoData() {
		$db = MWEchoDbFactory::newFromDefault()->getEchoDb( DB_MASTER );
		$db->delete( 'echo_event', '*', __METHOD__ );
		$db->delete( 'echo_notification', '*', __METHOD__ );
	}

	/**
	 * @covers \EchoHooks::onPageSaveComplete
	 */
	public function testFirstEdit() {
		// setup
		$this->deleteEchoData();
		$user = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( 'Help:MWEchoThankYouEditTest_testFirstEdit' );

		// action
		$this->edit( $title, $user, 'this is my first edit' );

		// assertions
		$notificationMapper = new EchoNotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $user, 10, null, [ 'thank-you-edit' ] );
		$this->assertCount( 1, $notifications );

		/** @var EchoNotification $notification */
		$notification = reset( $notifications );
		$this->assertSame( 1, $notification->getEvent()->getExtraParam( 'editCount', 'not found' ) );
	}

	/**
	 * @covers \EchoHooks::onPageSaveComplete
	 */
	public function testTenthEdit() {
		// setup
		$this->deleteEchoData();
		$user = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( 'Help:MWEchoThankYouEditTest_testTenthEdit' );

		// action
		// we could fast-forward the edit-count to speed things up
		// but this is the only way to make sure duplicate notifications
		// are not generated
		for ( $i = 0; $i < 12; $i++ ) {
			$this->edit( $title, $user, "this is edit #$i" );
			// Reload to reflect deferred update
			$user->clearInstanceCache();
		}

		// assertions
		$notificationMapper = new EchoNotificationMapper();
		$notifications = $notificationMapper->fetchByUser( $user, 10, null, [ 'thank-you-edit' ] );
		$this->assertCount( 2, $notifications );

		/** @var EchoNotification $notification */
		$notification = reset( $notifications );
		$this->assertSame( 10, $notification->getEvent()->getExtraParam( 'editCount', 'not found' ) );
	}

	private function edit( Title $title, User $user, $text ) {
		$page = WikiPage::factory( $title );
		$content = ContentHandler::makeContent( $text, $title );
		$page->doEditContent( $content, 'test', 0, false, $user );
	}
}
