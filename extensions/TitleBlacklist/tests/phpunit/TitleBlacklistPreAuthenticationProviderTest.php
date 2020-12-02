<?php

use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;

/**
 * @group Database
 * @covers TitleBlacklistPreAuthenticationProvider
 */
class TitleBlacklistPreAuthenticationProviderTest extends MediaWikiTestCase {
	/**
	 * @dataProvider provideGetAuthenticationRequests
	 */
	public function testGetAuthenticationRequests( $action, $username, $expectedReqs ) {
		$provider = new TitleBlacklistPreAuthenticationProvider();
		$provider->setManager( MediaWikiServices::getInstance()->getAuthManager() );
		$reqs = $provider->getAuthenticationRequests( $action, [ 'username' => $username ] );
		$this->assertEquals( $expectedReqs, $reqs );
	}

	public function provideGetAuthenticationRequests() {
		return [
			[ AuthManager::ACTION_LOGIN, null, [] ],
			[ AuthManager::ACTION_CREATE, null, [] ],
			[ AuthManager::ACTION_CREATE, 'UTSysop', [ new TitleBlacklistAuthenticationRequest() ] ],
			[ AuthManager::ACTION_CHANGE, null, [] ],
			[ AuthManager::ACTION_REMOVE, null, [] ],
		];
	}
}
