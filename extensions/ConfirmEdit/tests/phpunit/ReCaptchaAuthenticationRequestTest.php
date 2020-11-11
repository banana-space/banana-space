<?php

use MediaWiki\Auth\AuthenticationRequestTestCase;

require_once __DIR__ . '/../../ReCaptcha/ReCaptchaAuthenticationRequest.php';

/**
 * @covers ReCaptchaAuthenticationRequest
 */
class ReCaptchaAuthenticationRequestTest extends AuthenticationRequestTestCase {
	protected function getInstance( array $args = [] ) {
		return new ReCaptchaAuthenticationRequest();
	}

	public function provideLoadFromSubmission() {
		return [
			'no challange id' => [ [], [ 'captchaWord' => 'abc' ], false ],
			'no solution' => [ [], [ 'captchaId' => '123' ], false ],
			'normal' => [ [], [ 'captchaId' => '123', 'captchaWord' => 'abc' ],
				[ 'captchaId' => '123', 'captchaWord' => 'abc' ] ],
		];
	}
}
