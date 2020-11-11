<?php

use MediaWiki\Auth\AuthenticationRequest;

class ReCaptcha extends SimpleCaptcha {
	// used for recaptcha-edit, recaptcha-addurl, recaptcha-badlogin, recaptcha-createaccount,
	// recaptcha-create, recaptcha-sendemail via getMessage()
	protected static $messagePrefix = 'recaptcha-';

	// reCAPTHCA error code returned from recaptcha_check_answer
	private $recaptcha_error = null;

	/**
	 * Displays the reCAPTCHA widget.
	 * If $this->recaptcha_error is set, it will display an error in the widget.
	 * @param int $tabIndex
	 * @return array
	 */
	function getFormInformation( $tabIndex = 1 ) {
		global $wgReCaptchaPublicKey, $wgReCaptchaTheme;

		wfDeprecated( 'ConfirmEdit module ReCaptcha', '1.28' );
		$useHttps = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' );
		$js = 'var RecaptchaOptions = ' . Xml::encodeJsVar(
			[ 'theme' => $wgReCaptchaTheme, 'tabindex' => $tabIndex ]
		);

		return [
			'html' => Html::inlineScript( $js ) .
				recaptcha_get_html( $wgReCaptchaPublicKey, $this->recaptcha_error, $useHttps )
		];
	}

	/**
	 * @param WebRequest $request
	 * @return array
	 */
	protected function getCaptchaParamsFromRequest( WebRequest $request ) {
		// API is hardwired to return captchaId and captchaWord,
		// so use that if the standard two are empty
		$challenge = $request->getVal( 'recaptcha_challenge_field', $request->getVal( 'captchaId' ) );
		$response = $request->getVal( 'recaptcha_response_field', $request->getVal( 'captchaWord' ) );
		return [ $challenge, $response ];
	}

	/**
	 * Calls the library function recaptcha_check_answer to verify the users input.
	 * Sets $this->recaptcha_error if the user is incorrect.
	 * @param string $challenge Challenge value
	 * @param string $response Response value
	 * @return bool
	 */
	function passCaptcha( $challenge, $response ) {
		global $wgReCaptchaPrivateKey, $wgRequest;

		if ( $response === null ) {
			// new captcha session
			return false;
		}

		$ip = $wgRequest->getIP();

		$recaptcha_response =
			recaptcha_check_answer( $wgReCaptchaPrivateKey, $ip, $challenge, $response );

		if ( !$recaptcha_response->is_valid ) {
			$this->recaptcha_error = $recaptcha_response->error;
			return false;
		}

		$recaptcha_error = null;

		return true;
	}

	/**
	 * @param array &$resultArr
	 */
	function addCaptchaAPI( &$resultArr ) {
		$resultArr['captcha'] = $this->describeCaptchaType();
		$resultArr['captcha']['error'] = $this->recaptcha_error;
	}

	/**
	 * @return array
	 */
	public function describeCaptchaType() {
		global $wgReCaptchaPublicKey;
		return [
			'type' => 'recaptcha',
			'mime' => 'image/png',
			'key' => $wgReCaptchaPublicKey,
		];
	}

	/**
	 * @param ApiBase &$module
	 * @param array &$params
	 * @param int $flags
	 * @return bool
	 */
	public function APIGetAllowedParams( &$module, &$params, $flags ) {
		if ( $flags && $this->isAPICaptchaModule( $module ) ) {
			$params['recaptcha_challenge_field'] = [
				ApiBase::PARAM_HELP_MSG => 'recaptcha-apihelp-param-recaptcha_challenge_field',
			];
			$params['recaptcha_response_field'] = [
				ApiBase::PARAM_HELP_MSG => 'recaptcha-apihelp-param-recaptcha_response_field',
			];
		}

		return true;
	}

	/**
	 * @return null
	 */
	public function getError() {
		// do not treat failed captcha attempts as errors
		if ( in_array( $this->recaptcha_error, [
			'invalid-request-cookie', 'incorrect-captcha-sol',
		], true ) ) {
			return null;
		}

		return $this->recaptcha_error;
	}

	public function storeCaptcha( $info ) {
		// ReCaptcha is stored by Google; the ID will be generated at that time as well, and
		// the one returned here won't be used. Just pretend this worked.
		return 'not used';
	}

	public function retrieveCaptcha( $index ) {
		// just pretend it worked
		return [ 'index' => $index ];
	}

	public function getCaptcha() {
		// ReCaptcha is handled by frontend code + an external provider; nothing to do here.
		return [];
	}

	/**
	 * @param array $captchaData
	 * @param string $id
	 * @return Message
	 */
	public function getCaptchaInfo( $captchaData, $id ) {
		return wfMessage( 'recaptcha-info' );
	}

	/**
	 * @return ReCaptchaAuthenticationRequest
	 */
	public function createAuthenticationRequest() {
		return new ReCaptchaAuthenticationRequest();
	}

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		global $wgReCaptchaPublicKey, $wgReCaptchaTheme;

		$req = AuthenticationRequest::getRequestByClass( $requests,
			CaptchaAuthenticationRequest::class, true );
		if ( !$req ) {
			return;
		}

		// ugly way to retrieve error information
		$captcha = ConfirmEditHooks::getInstance();

		$formDescriptor['captchaInfo'] = [
			'class' => HTMLReCaptchaField::class,
			'key' => $wgReCaptchaPublicKey,
			'theme' => $wgReCaptchaTheme,
			'secure' => isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on',
			'error' => $captcha->getError(),
		] + $formDescriptor['captchaInfo'];

		// the custom form element cannot return multiple fields; work around that by
		// "redirecting" ReCaptcha names to standard names
		$formDescriptor['captchaId'] = [
			'class' => HTMLSubmittedValueField::class,
			'name' => 'recaptcha_challenge_field',
		];
		$formDescriptor['captchaWord'] = [
			'class' => HTMLSubmittedValueField::class,
			'name' => 'recaptcha_response_field',
		];
	}
}
