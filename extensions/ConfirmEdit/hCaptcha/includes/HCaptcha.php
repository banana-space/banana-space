<?php

namespace MediaWiki\Extensions\ConfirmEdit\hCaptcha;

use ApiBase;
use CaptchaAuthenticationRequest;
use ConfirmEditHooks;
use FormatJson;
use Html;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\MediaWikiServices;
use Message;
use RawMessage;
use SimpleCaptcha;
use Status;
use WebRequest;

class HCaptcha extends SimpleCaptcha {
	// used for hcaptcha-edit, hcaptcha-addurl, hcaptcha-badlogin, hcaptcha-createaccount,
	// hcaptcha-create, hcaptcha-sendemail via getMessage()
	protected static $messagePrefix = 'hcaptcha-';

	private $error;

	/**
	 * Get the captcha form.
	 * @param int $tabIndex
	 * @return array
	 */
	public function getFormInformation( $tabIndex = 1 ) {
		global $wgHCaptchaSiteKey;

		$output = Html::element( 'div', [
			'class' => [
				'h-captcha',
				'mw-confirmedit-captcha-fail' => (bool)$this->error,
			],
			'data-sitekey' => $wgHCaptchaSiteKey
		] );

		return [
			'html' => $output,
			'headitems' => [
				"<script src=\"https://hcaptcha.com/1/api.js\" async defer></script>"
			]
		];
	}

	/**
	 * @return string[]
	 */
	public static function getCSPUrls() {
		return [ 'https://hcaptcha.com', 'https://*.hcaptcha.com' ];
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

		\wfDebugLog( 'captcha', 'Unable to validate response: ' . $error );
	}

	/**
	 * @param WebRequest $request
	 * @return array
	 */
	protected function getCaptchaParamsFromRequest( WebRequest $request ) {
		$response = $request->getVal( 'h-captcha-response' );
		return [ '', $response ];
	}

	/**
	 * Check, if the user solved the captcha.
	 *
	 * Based on reference implementation:
	 * https://github.com/google/recaptcha#php and https://docs.hcaptcha.com/
	 *
	 * @param mixed $_ Not used
	 * @param string $token token from the POST data
	 * @return bool
	 */
	protected function passCaptcha( $_, $token ) {
		global $wgRequest, $wgHCaptchaSecretKey, $wgHCaptchaSendRemoteIP, $wgHCaptchaProxy;

		$url = 'https://hcaptcha.com/siteverify';
		$data = [
			'secret' => $wgHCaptchaSecretKey,
			'response' => $token,
		];
		if ( $wgHCaptchaSendRemoteIP ) {
			$data['remoteip'] = $wgRequest->getIP();
		}

		$options = [
			'method' => 'POST',
			'postData' => $data,
		];

		if ( $wgHCaptchaProxy ) {
			$options['proxy'] = $wgHCaptchaProxy;
		}

		$request = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $url, $options, __METHOD__ );

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
			$this->error = 'hcaptcha-api';
			$this->logCheckError( $response['error-codes'] );
			return false;
		}

		return $response['success'];
	}

	/**
	 * @param array &$resultArr
	 */
	protected function addCaptchaAPI( &$resultArr ) {
	}

	/**
	 * @return array
	 */
	public function describeCaptchaType() {
		global $wgHCaptchaSiteKey;
		return [
			'type' => 'hcaptcha',
			'mime' => 'application/javascript',
			'key' => $wgHCaptchaSiteKey,
		];
	}

	/**
	 * Show a message asking the user to enter a captcha on edit
	 * The result will be treated as wiki text
	 *
	 * @param string $action Action being performed
	 * @return Message
	 */
	public function getMessage( $action ) {
		$msg = parent::getMessage( $action );
		if ( $this->error ) {
			$msg = new RawMessage( '<div class="error">$1</div>', [ $msg ] );
		}
		return $msg;
	}

	/**
	 * @param ApiBase $module
	 * @param array &$params
	 * @param int $flags
	 * @return bool
	 */
	public function apiGetAllowedParams( ApiBase $module, &$params, $flags ) {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * @inheritDoc
	 */
	public function storeCaptcha( $info ) {
		return 'not used';
	}

	/**
	 * @inheritDoc
	 */
	public function retrieveCaptcha( $index ) {
		// just pretend it worked
		return [ 'index' => $index ];
	}

	/**
	 * @inheritDoc
	 */
	public function getCaptcha() {
		return [];
	}

	/**
	 * @return HCaptchaAuthenticationRequest
	 */
	public function createAuthenticationRequest() {
		return new HCaptchaAuthenticationRequest();
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
		global $wgHCaptchaSiteKey;

		$req = AuthenticationRequest::getRequestByClass(
			$requests,
			CaptchaAuthenticationRequest::class,
			true
		);
		if ( !$req ) {
			return;
		}

		// ugly way to retrieve error information
		$captcha = ConfirmEditHooks::getInstance();

		$formDescriptor['captchaWord'] = [
			'class' => HTMLHCaptchaField::class,
			'key' => $wgHCaptchaSiteKey,
			'error' => $captcha->getError(),
		] + $formDescriptor['captchaWord'];
	}
}
