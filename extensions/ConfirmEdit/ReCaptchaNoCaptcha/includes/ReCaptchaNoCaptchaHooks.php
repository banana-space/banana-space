<?php

class ReCaptchaNoCaptchaHooks {
	/**
	 * Adds extra variables to the global config
	 *
	 * @param array &$vars Global variables object
	 * @return bool Always true
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $wgReCaptchaSiteKey;
		global $wgCaptchaClass;

		if ( $wgCaptchaClass === 'ReCaptchaNoCaptcha' ) {
			$vars['wgConfirmEditConfig'] = [
				'reCaptchaSiteKey' => $wgReCaptchaSiteKey,
				'reCaptchaScriptURL' => 'https://www.recaptcha.net/recaptcha/api.js'
			];
		}

		return true;
	}
}
