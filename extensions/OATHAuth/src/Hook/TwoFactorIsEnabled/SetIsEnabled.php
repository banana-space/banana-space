<?php

namespace MediaWiki\Extension\OATHAuth\Hook\TwoFactorIsEnabled;

use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\MediaWikiServices;
use RequestContext;

class SetIsEnabled {
	/**
	 * @var OATHUser
	 */
	protected $authUser;

	/**
	 * @var bool
	 */
	protected $isEnabled;

	/**
	 * @param bool &$isEnabled
	 * @return bool
	 */
	public static function callback( &$isEnabled ) {
		$userRepo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
		$authUser = $userRepo->findByUser( RequestContext::getMain()->getUser() );
		$handler = new static( $authUser, $isEnabled );
		return $handler->execute();
	}

	/**
	 * SetIsEnabled constructor.
	 * @param OATHUser $authUser
	 * @param bool &$isEnabled
	 */
	protected function __construct( $authUser, &$isEnabled ) {
		$this->authUser = $authUser;
		$this->isEnabled = &$isEnabled;
	}

	protected function execute() {
		if ( $this->authUser && $this->authUser->getModule() !== null ) {
			$this->isEnabled = true;
			# This two-factor extension is enabled by the user,
			# we don't need to check others.
			return false;
		} else {
			$this->isEnabled = false;
			# This two-factor extension isn't enabled by the user,
			# but others may be.
			return true;
		}
	}
}
