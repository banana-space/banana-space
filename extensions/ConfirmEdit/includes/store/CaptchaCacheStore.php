<?php

use MediaWiki\MediaWikiServices;

class CaptchaCacheStore extends CaptchaStore {
	/** @var BagOStuff */
	private $cache;

	public function __construct() {
		parent::__construct();

		$this->cache = MediaWikiServices::getInstance()->getMainObjectStash();
	}

	/**
	 * @inheritDoc
	 */
	public function store( $index, $info ) {
		global $wgCaptchaSessionExpiration;

		$cache = $this->cache;
		$cache->set(
			$cache->makeKey( 'captcha', $index ),
			$info,
			$wgCaptchaSessionExpiration
		);
	}

	/**
	 * @inheritDoc
	 */
	public function retrieve( $index ) {
		$cache = $this->cache;
		return $cache->get( $cache->makeKey( 'captcha', $index ) ) ?: false;
	}

	/**
	 * @inheritDoc
	 */
	public function clear( $index ) {
		$cache = $this->cache;
		$cache->delete( $cache->makeKey( 'captcha', $index ) );
	}

	public function cookiesNeeded() {
		return false;
	}
}
