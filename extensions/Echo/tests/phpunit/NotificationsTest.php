<?php

use MediaWiki\MediaWikiServices;

/**
 * Tests for the built in notification types
 *
 * @group Database
 */
class NotificationsTest extends MediaWikiTestCase {

	/** @var User $sysop */
	// @codingStandardsIgnoreStart
	var $sysop;
	// @codingStandardsIgnoreEnd

	protected function setUp() : void {
		parent::setUp();
		$this->sysop = User::newFromName( 'UTSysop' );
		$this->setMwGlobals( 'wgUser', $this->sysop );
	}

	/**
	 * Helper function to get a user's latest notification
	 * @param User $user
	 * @return EchoEvent
	 */
	public static function getLatestNotification( $user ) {
		$notifMapper = new EchoNotificationMapper();
		$notifs = $notifMapper->fetchUnreadByUser( $user, 1, '', [ 'user-rights' ] );
		$notif = array_pop( $notifs );

		return $notif->getEvent();
	}

	/**
	 * @covers \EchoHooks::onUserGroupsChanged
	 */
	public function testUserRightsNotif() {
		$user = new User();
		$user->setName( 'Dummy' );
		$user->addToDatabase();

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $this->sysop );
		$ur = new UserrightsPage(
			MediaWikiServices::getInstance()->getPermissionManager()
		);
		$ur->setContext( $context );
		$ur->doSaveUserGroups( $user, [ 'sysop' ], [], 'reason' );
		$event = self::getLatestNotification( $user );
		$this->assertEquals( $event->getType(), 'user-rights' );
		$this->assertEquals( $this->sysop->getName(), $event->getAgent()->getName() );
		$extra = $event->getExtra();
		$this->assertArrayHasKey( 'add', $extra );
		$this->assertArrayEquals( [ 'sysop' ], $extra['add'] );
		$this->assertArrayEquals( [], $extra['remove'] );
	}

}
