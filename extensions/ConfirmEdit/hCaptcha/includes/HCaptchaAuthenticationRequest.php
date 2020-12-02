<?php

namespace MediaWiki\Extensions\ConfirmEdit\hCaptcha;

use CaptchaAuthenticationRequest;
use MediaWiki\Auth\AuthenticationRequest;

class HCaptchaAuthenticationRequest extends CaptchaAuthenticationRequest {
	public function __construct() {
		parent::__construct( '', [] );
	}

	/**
	 * @inheritDoc
	 */
	public function loadFromSubmission( array $data ) {
		// unhack the hack in parent
		return AuthenticationRequest::loadFromSubmission( $data );
	}

	/**
	 * @inheritDoc
	 */
	public function getFieldInfo() {
		$fieldInfo = parent::getFieldInfo();

		return [
			'captchaWord' => [
				'type' => 'string',
				'label' => $fieldInfo['captchaInfo']['label'],
				'help' => \wfMessage( 'hcaptcha-help' ),
			],
		];
	}
}
