<?php

use MediaWiki\Auth\AuthenticationRequest;

/**
 * Authentication request for ReCaptcha v1. Unlike the parent class, no session storage is used;
 * that's handled by Google.
 */
class ReCaptchaAuthenticationRequest extends CaptchaAuthenticationRequest {
	public function __construct() {
		parent::__construct( null, null );
	}

	public function loadFromSubmission( array $data ) {
		// unhack the hack in parent
		return AuthenticationRequest::loadFromSubmission( $data );
	}

	public function getFieldInfo() {
		$fieldInfo = parent::getFieldInfo();
		if ( !$fieldInfo ) {
			return false;
		}

		return array_merge( $fieldInfo, [
			'captchaId' => [
				'type' => 'string',
				'label' => wfMessage( 'recaptcha-id-label' ),
				'help' => wfMessage( 'recaptcha-id-help' ),
			],
			'captchaWord' => [
				'type' => 'string',
				'label' => wfMessage( 'recaptcha-label' ),
				'help' => wfMessage( 'recaptcha-help' ),
			],
		] );
	}
}
