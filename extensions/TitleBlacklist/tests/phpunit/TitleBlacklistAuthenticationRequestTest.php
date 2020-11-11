<?php

use MediaWiki\Auth\AuthenticationRequestTestCase;
use MediaWiki\Auth\AuthManager;

/**
 * @covers TitleBlacklistAuthenticationRequest
 */
class TitleBlacklistAuthenticationRequestTest extends AuthenticationRequestTestCase {
	public function setUp() {
		global $wgDisableAuthManager;
		if ( !class_exists( AuthManager::class ) || $wgDisableAuthManager ) {
			$this->markTestSkipped( 'AuthManager is disabled' );
		}
		parent::setUp();
	}

	protected function getInstance( array $args = [] ) {
		return new TitleBlacklistAuthenticationRequest();
	}

	public function provideLoadFromSubmission() {
		return [
			'empty' => [ [], [], [ 'ignoreTitleBlacklist' => false ] ],
			'true' => [ [], [ 'ignoreTitleBlacklist' => '1' ], [ 'ignoreTitleBlacklist' => true ] ],
		];
	}
}
