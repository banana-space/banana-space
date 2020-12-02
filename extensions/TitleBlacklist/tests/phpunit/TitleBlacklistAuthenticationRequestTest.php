<?php

use MediaWiki\Auth\AuthenticationRequestTestCase;

/**
 * @covers TitleBlacklistAuthenticationRequest
 */
class TitleBlacklistAuthenticationRequestTest extends AuthenticationRequestTestCase {
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
