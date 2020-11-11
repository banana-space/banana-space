<?php

class CaptchaCacheStore extends CaptchaStore {
	function store( $index, $info ) {
		global $wgCaptchaSessionExpiration;

		ObjectCache::getMainStashInstance()->set(
			wfMemcKey( 'captcha', $index ),
			$info,
			$wgCaptchaSessionExpiration
		);
	}

	function retrieve( $index ) {
		$info = ObjectCache::getMainStashInstance()->get( wfMemcKey( 'captcha', $index ) );
		if ( $info ) {
			return $info;
		} else {
			return false;
		}
	}

	function clear( $index ) {
		ObjectCache::getMainStashInstance()->delete( wfMemcKey( 'captcha', $index ) );
	}

	function cookiesNeeded() {
		return false;
	}
}
