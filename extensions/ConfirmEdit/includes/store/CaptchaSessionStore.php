<?php

use MediaWiki\Session\SessionManager;

class CaptchaSessionStore extends CaptchaStore {
	protected function __construct() {
		// Make sure the session is started
		SessionManager::getGlobalSession()->persist();
	}

	function store( $index, $info ) {
		SessionManager::getGlobalSession()->set( 'captcha' . $index, $info );
	}

	function retrieve( $index ) {
		return SessionManager::getGlobalSession()->get( 'captcha' . $index, false );
	}

	function clear( $index ) {
		SessionManager::getGlobalSession()->remove( 'captcha' . $index );
	}

	function cookiesNeeded() {
		return true;
	}
}
