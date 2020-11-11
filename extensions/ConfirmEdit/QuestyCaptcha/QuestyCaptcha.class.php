<?php

/**
 * QuestyCaptcha class
 *
 * @file
 * @author Benjamin Lees <emufarmers@gmail.com>
 * @ingroup Extensions
 */

use MediaWiki\Auth\AuthenticationRequest;

class QuestyCaptcha extends SimpleCaptcha {
	// used for questycaptcha-edit, questycaptcha-addurl, questycaptcha-badlogin,
	// questycaptcha-createaccount, questycaptcha-create, questycaptcha-sendemail via getMessage()
	protected static $messagePrefix = 'questycaptcha-';

	/**
	 * Validate a captcha response
	 * @param string $answer
	 * @param array $info
	 * @return bool
	 */
	function keyMatch( $answer, $info ) {
		if ( is_array( $info['answer'] ) ) {
			return in_array( strtolower( $answer ), array_map( 'strtolower', $info['answer'] ) );
		} else {
			return strtolower( $answer ) == strtolower( $info['answer'] );
		}
	}

	/**
	 * @param array &$resultArr
	 */
	function addCaptchaAPI( &$resultArr ) {
		$captcha = $this->getCaptcha();
		$index = $this->storeCaptcha( $captcha );
		$resultArr['captcha'] = $this->describeCaptchaType();
		$resultArr['captcha']['id'] = $index;
		$resultArr['captcha']['question'] = $captcha['question'];
	}

	/**
	 * @return array
	 */
	public function describeCaptchaType() {
		return [
			'type' => 'question',
			'mime' => 'text/html',
		];
	}

	/**
	 * @return array
	 */
	function getCaptcha() {
		global $wgCaptchaQuestions;

		// Backwards compatibility
		if ( $wgCaptchaQuestions === array_values( $wgCaptchaQuestions ) ) {
			return $wgCaptchaQuestions[ mt_rand( 0, count( $wgCaptchaQuestions ) - 1 ) ];
		}

		$question = array_rand( $wgCaptchaQuestions, 1 );
		$answer = $wgCaptchaQuestions[ $question ];
		return [ 'question' => $question, 'answer' => $answer ];
	}

	/**
	 * @param int $tabIndex
	 * @return array
	 */
	function getFormInformation( $tabIndex = 1 ) {
		$captcha = $this->getCaptcha();
		if ( !$captcha ) {
			die(
				"No questions found; set some in LocalSettings.php using the format from QuestyCaptcha.php."
			);
		}
		$index = $this->storeCaptcha( $captcha );
		return [
			'html' => "<p><label for=\"wpCaptchaWord\">{$captcha['question']}</label> " .
				Html::element( 'input', [
					'name' => 'wpCaptchaWord',
					'id'   => 'wpCaptchaWord',
					'class' => 'mw-ui-input',
					'required',
					'autocomplete' => 'off',
					'tabindex' => $tabIndex ] ) . // tab in before the edit textarea
				"</p>\n" .
				Xml::element( 'input', [
					'type'  => 'hidden',
					'name'  => 'wpCaptchaId',
					'id'    => 'wpCaptchaId',
					'value' => $index ] )
		];
	}

	function showHelp() {
		global $wgOut;
		$wgOut->setPageTitle( wfMessage( 'captchahelp-title' )->text() );
		$wgOut->addWikiMsg( 'questycaptchahelp-text' );
		if ( CaptchaStore::get()->cookiesNeeded() ) {
			$wgOut->addWikiMsg( 'captchahelp-cookies-needed' );
		}
	}

	/**
	 * @param array $captchaData
	 * @param string $id
	 * @return mixed
	 */
	public function getCaptchaInfo( $captchaData, $id ) {
		return $captchaData['question'];
	}

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public function onAuthChangeFormFields( array $requests, array $fieldInfo,
		array &$formDescriptor, $action ) {
		/** @var CaptchaAuthenticationRequest $req */
		$req =
			AuthenticationRequest::getRequestByClass( $requests,
				CaptchaAuthenticationRequest::class, true );
		if ( !$req ) {
			return;
		}

		// declare RAW HTML output.
		$formDescriptor['captchaInfo']['raw'] = true;
		$formDescriptor['captchaWord']['label-message'] = null;
	}
}
