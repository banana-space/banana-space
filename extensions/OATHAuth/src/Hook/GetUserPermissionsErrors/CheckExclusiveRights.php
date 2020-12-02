<?php

namespace MediaWiki\Extension\OATHAuth\Hook\GetUserPermissionsErrors;

use ConfigException;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Session\Session;
use RequestContext;
use Title;
use User;

class CheckExclusiveRights {
	/**
	 * Array of rights that a user should only have
	 * if they authenticated with 2FA
	 *
	 * @var array
	 */
	protected $exclusiveRights;

	/**
	 * @var Session
	 */
	protected $session;

	/**
	 * @var Title
	 */
	protected $title;

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var string
	 */
	protected $action;

	/**
	 * @var string|array
	 */
	protected $result;

	/**
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array &$result
	 * @return bool
	 * @throws ConfigException
	 */
	public static function callback( $title, $user, $action, &$result ) {
		$config = RequestContext::getMain()->getConfig();
		if ( !$config->has( 'OATHExclusiveRights' ) ) {
			return true;
		}
		$session = $user->getRequest()->getSession();
		$exclusiveRights = $config->get( 'OATHExclusiveRights' );
		$handler = new static( $exclusiveRights, $session, $title, $user, $action, $result );
		return $handler->execute();
	}

	/**
	 * @param array $exclusiveRights
	 * @param Session $session
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array &$result
	 */
	protected function __construct( $exclusiveRights, $session, $title, $user, $action, &$result ) {
		$this->exclusiveRights = $exclusiveRights;
		$this->session = $session;
		$this->title = $title;
		$this->user = $user;
		$this->action = $action;
		$this->result = &$result;
	}

	/**
	 * Take away user rights if not authenticated with 2FA
	 *
	 * @return bool
	 */
	protected function execute() {
		if ( !$this->authenticatedOver2FA() && $this->actionBlocked() ) {
			$this->addError();
			return false;
		}
		return true;
	}

	/**
	 * @return bool
	 */
	private function authenticatedOver2FA() {
		return (bool)$this->session->get( OATHAuth::AUTHENTICATED_OVER_2FA, false );
	}

	private function actionBlocked() {
		return in_array( $this->action, $this->exclusiveRights );
	}

	private function addError() {
		$this->result = 'oathauth-action-exclusive-to-2fa';
	}
}
