<?php

use MediaWiki\Auth\AuthenticationRequestTestCase;

require_once __DIR__ . '/../../ReCaptchaNoCaptcha/ReCaptchaNoCaptchaAuthenticationRequest.php';

/**
 * @covers ReCaptchaNoCaptchaAuthenticationRequest
 */
class ReCaptchaNoCaptchaAuthenticationRequestTest extends AuthenticationRequestTestCase {
	protected function getInstance( array $args = [] ) {
		return new ReCaptchaNoCaptchaAuthenticationRequest();
	}

	public function provideLoadFromSubmission() {
		return [
			'no proof' => [ [], [], false ],
			'normal' => [ [], [ 'captchaWord' => 'abc' ], [ 'captchaWord' => 'abc' ] ],
		];
	}
}
