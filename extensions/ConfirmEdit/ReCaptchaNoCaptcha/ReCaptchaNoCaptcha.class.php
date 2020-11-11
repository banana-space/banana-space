<?php

use MediaWiki\Auth\AuthenticationRequest;

class ReCaptchaNoCaptcha extends SimpleCaptcha {
	// used for renocaptcha-edit, renocaptcha-addurl, renocaptcha-badlogin, renocaptcha-createaccount,
	// renocaptcha-create, renocaptcha-sendemail via getMessage()
	protected static $messagePrefix = 'renocaptcha-';

	private $error = null;
	/**
	 * Get the captcha form.
	 * @param int $tabIndex
	 * @return array
	 */
	function getFormInformation( $tabIndex = 1 ) {
		global $wgReCaptchaSiteKey, $wgLang;
		$lang = htmlspecialchars( urlencode( $wgLang->getCode() ) );

		$output = Html::element( 'div', [
			'class' => [
				'g-recaptcha',
				'mw-confirmedit-captcha-fail' => !!$this->error,
			],
			'data-sitekey' => $wgReCaptchaSiteKey
		] );
		$htmlUrlencoded = htmlspecialchars( urlencode( $wgReCaptchaSiteKey ) );
		$output .= <<<HTML
<noscript>
  <div>
    <div style="width: 302px; height: 422px; position: relative;">
      <div style="width: 302px; height: 422px; position: absolute;">
        <iframe src="https://www.google.com/recaptcha/api/fallback?k={$htmlUrlencoded}&hl={$lang}"
                frameborder="0" scrolling="no"
                style="width: 302px; height:422px; border-style: none;">
        </iframe>
      </div>
    </div>
    <div style="width: 300px; height: 60px; border-style: none;
                bottom: 12px; left: 25px; margin: 0px; padding: 0px; right: 25px;
                background: #f9f9f9; border: 1px solid #c1c1c1; border-radius: 3px;">
      <textarea id="g-recaptcha-response" name="g-recaptcha-response"
                class="g-recaptcha-response"
                style="width: 250px; height: 40px; border: 1px solid #c1c1c1;
                       margin: 10px 25px; padding: 0px; resize: none;" >
      </textarea>
    </div>
  </div>
</noscript>
HTML;
		return [
			'html' => $output,
			'headitems' => [
				// Insert reCAPTCHA script, in display language, if available.
				// Language falls back to the browser's display language.
				// See https://developers.google.com/recaptcha/docs/faq
				"<script src=\"https://www.google.com/recaptcha/api.js?hl={$lang}\" async defer></script>"
			]
		];
	}

	/**
	 * @param Status|array|string $info
	 */
	protected function logCheckError( $info ) {
		if ( $info instanceof Status ) {
			$errors = $info->getErrorsArray();
			$error = $errors[0][0];
		} elseif ( is_array( $info ) ) {
			$error = implode( ',', $info );
		} else {
			$error = $info;
		}

		wfDebugLog( 'captcha', 'Unable to validate response: ' . $error );
	}

	/**
	 * @param WebRequest $request
	 * @return array
	 */
	protected function getCaptchaParamsFromRequest( WebRequest $request ) {
		$index = 'not used'; // ReCaptchaNoCaptcha combines captcha ID + solution into a single value
		// API is hardwired to return captchaWord, so use that if the standard isempty
		$response = $request->getVal( 'g-recaptcha-response', $request->getVal( 'captchaWord' ) );
		return [ $index, $response ];
	}

	/**
	 * Check, if the user solved the captcha.
	 *
	 * Based on reference implementation:
	 * https://github.com/google/recaptcha#php
	 *
	 * @param mixed $_ Not used (ReCaptcha v2 puts index and solution in a single string)
	 * @param string $word captcha solution
	 * @return bool
	 */
	function passCaptcha( $_, $word ) {
		global $wgRequest, $wgReCaptchaSecretKey, $wgReCaptchaSendRemoteIP;

		$url = 'https://www.google.com/recaptcha/api/siteverify';
		// Build data to append to request
		$data = [
			'secret' => $wgReCaptchaSecretKey,
			'response' => $word,
		];
		if ( $wgReCaptchaSendRemoteIP ) {
			$data['remoteip'] = $wgRequest->getIP();
		}
		$url = wfAppendQuery( $url, $data );
		$request = MWHttpRequest::factory( $url, [ 'method' => 'GET' ] );
		$status = $request->execute();
		if ( !$status->isOK() ) {
			$this->error = 'http';
			$this->logCheckError( $status );
			return false;
		}
		$response = FormatJson::decode( $request->getContent(), true );
		if ( !$response ) {
			$this->error = 'json';
			$this->logCheckError( $this->error );
			return false;
		}
		if ( isset( $response['error-codes'] ) ) {
			$this->error = 'recaptcha-api';
			$this->logCheckError( $response['error-codes'] );
			return false;
		}

		return $response['success'];
	}

	/**
	 * @param array &$resultArr
	 */
	function addCaptchaAPI( &$resultArr ) {
		$resultArr['captcha'] = $this->describeCaptchaType();
		$resultArr['captcha']['error'] = $this->error;
	}

	/**
	 * @return array
	 */
	public function describeCaptchaType() {
		global $wgReCaptchaSiteKey;
		return [
			'type' => 'recaptchanocaptcha',
			'mime' => 'image/png',
			'key' => $wgReCaptchaSiteKey,
		];
	}

	/**
	 * Show a message asking the user to enter a captcha on edit
	 * The result will be treated as wiki text
	 *
	 * @param string $action Action being performed
	 * @return string Wikitext
	 */
	public function getMessage( $action ) {
		$msg = parent::getMessage( $action );
		if ( $this->error ) {
			$msg = new RawMessage( '<div class="error">$1</div>', [ $msg ] );
		}
		return $msg;
	}

	/**
	 * @param ApiBase &$module
	 * @param array &$params
	 * @param int $flags
	 * @return bool
	 */
	public function APIGetAllowedParams( &$module, &$params, $flags ) {
		if ( $flags && $this->isAPICaptchaModule( $module ) ) {
			$params['g-recaptcha-response'] = [
				ApiBase::PARAM_HELP_MSG => 'renocaptcha-apihelp-param-g-recaptcha-response',
			];
		}

		return true;
	}

	public function getError() {
		return $this->error;
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
		return wfMessage( 'renocaptcha-info' );
	}

	/**
	 * @return ReCaptchaNoCaptchaAuthenticationRequest
	 */
	public function createAuthenticationRequest() {
		return new ReCaptchaNoCaptchaAuthenticationRequest();
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
		global $wgReCaptchaSiteKey;

		$req = AuthenticationRequest::getRequestByClass( $requests,
			CaptchaAuthenticationRequest::class, true );
		if ( !$req ) {
			return;
		}

		// ugly way to retrieve error information
		$captcha = ConfirmEditHooks::getInstance();

		$formDescriptor['captchaWord'] = [
			'class' => HTMLReCaptchaNoCaptchaField::class,
			'key' => $wgReCaptchaSiteKey,
			'error' => $captcha->getError(),
		] + $formDescriptor['captchaWord'];
	}
}
