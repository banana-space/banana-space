<?php

namespace MediaWiki\Extension\OATHAuth\Module;

use MediaWiki\Auth\SecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\Auth\TOTPSecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\TOTPDisableForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\TOTPEnableForm;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use Message;
use MWException;

class TOTP implements IModule {
	public static function factory() {
		return new static();
	}

	/**
	 * Name of the module
	 * @return string
	 */
	public function getName() {
		return "totp";
	}

	/**
	 * @return Message
	 */
	public function getDisplayName() {
		return wfMessage( 'oathauth-module-totp-label' );
	}

	/**
	 *
	 * @param array $data
	 * @return IAuthKey
	 * @throws MWException
	 */
	public function newKey( array $data ) {
		if ( !isset( $data['secret'] ) || !isset( $data['scratch_tokens'] ) ) {
			throw new MWException( 'oathauth-invalid-data-format' );
		}
		if ( is_string( $data['scratch_tokens' ] ) ) {
			$data['scratch_tokens'] = explode( ',', $data['scratch_tokens'] );
		}

		return TOTPKey::newFromArray( $data );
	}

	/**
	 * @param OATHUser $user
	 * @return array
	 * @throws MWException
	 */
	public function getDataFromUser( OATHUser $user ) {
		$key = $user->getFirstKey();
		if ( !( $key instanceof TOTPKey ) ) {
			throw new MWException( 'oathauth-invalid-key-type' );
		}
		return [
			'keys' => [ $key->jsonSerialize() ]
		];
	}

	/**
	 * @return SecondaryAuthenticationProvider
	 */
	public function getSecondaryAuthProvider() {
		return new TOTPSecondaryAuthenticationProvider();
	}

	/**
	 * @param OATHUser $user
	 * @param array $data
	 * @return bool|int
	 * @throws MWException
	 */
	public function verify( OATHUser $user, array $data ) {
		if ( !isset( $data['token'] ) ) {
			return false;
		}
		$key = $user->getFirstKey();
		if ( !( $key instanceof TOTPKey ) ) {
			return false;
		}
		return $key->verify( $data, $user );
	}

	/**
	 * Is this module currently enabled for the given user
	 *
	 * @param OATHUser $user
	 * @return bool
	 */
	public function isEnabled( OATHUser $user ) {
		return $user->getFirstKey() instanceof TOTPKey;
	}

	/**
	 * @param string $action
	 * @param OATHUser $user
	 * @param OATHUserRepository $repo
	 * @return IManageForm|null
	 */
	public function getManageForm( $action, OATHUser $user, OATHUserRepository $repo ) {
		$isEnabledForUser = $user->getModule() instanceof self;
		if ( $action === OATHManage::ACTION_ENABLE && !$isEnabledForUser ) {
			return new TOTPEnableForm( $user, $repo, $this );
		}
		if ( $action === OATHManage::ACTION_DISABLE && $isEnabledForUser ) {
			return new TOTPDisableForm( $user, $repo, $this );
		}
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getConfig() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescriptionMessage() {
		return wfMessage( 'oathauth-totp-description' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDisableWarningMessage() {
		return wfMessage( 'oathauth-totp-disable-warning' );
	}
}
