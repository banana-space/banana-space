<?php

use MediaWiki\Auth\AuthenticationRequestTestCase;

class TOTPAuthenticationRequestTest extends AuthenticationRequestTestCase {

	protected function getInstance( array $args = [] ) {
		return new TOTPAuthenticationRequest();
	}

	public function provideLoadFromSubmission() {
		return [
			[ [], [], false ],
			[ [], [ 'OATHToken' => '123456' ], [ 'OATHToken' => '123456' ] ],
		];
	}
}
