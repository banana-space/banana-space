<?php

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\Auth\SecondaryAuthenticationProvider;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use Message;

interface IModule {
	/**
	 * Name of the module
	 * @return string
	 */
	public function getName();

	/**
	 * @return Message
	 */
	public function getDisplayName();

	/**
	 *
	 * @param array $data
	 * @return IAuthKey
	 */
	public function newKey( array $data );

	/**
	 * @param OATHUser $user
	 * @return array
	 */
	public function getDataFromUser( OATHUser $user );

	/**
	 * @return SecondaryAuthenticationProvider
	 */
	public function getSecondaryAuthProvider();

	/**
	 * Is this module currently enabled for the given user
	 * Arguably, module is enabled just by the fact its set on user
	 * but it might not be true for all future modules
	 *
	 * @param OATHUser $user
	 * @return boolean
	 */
	public function isEnabled( OATHUser $user );

	/**
	 * Run the validation
	 *
	 * @param OATHUser $user
	 * @param array $data
	 * @return boolean
	 */
	public function verify( OATHUser $user, array $data );

	/**
	 * @param string $action
	 * @param OATHUser $user
	 * @param OATHUserRepository $repo
	 * @return IManageForm|null if no form is available for given action
	 */
	public function getManageForm( $action, OATHUser $user, OATHUserRepository $repo );

	/**
	 * Return Message object for the short text to be displayed as description
	 * @return Message
	 */
	public function getDescriptionMessage();

	/**
	 * Module-specific text that will be shown when user is disabling
	 * the module, to warn of data-loss.
	 * This will be shown alongside generic warning message.
	 *
	 * @return Message|null if no additional text is needed
	 */
	public function getDisableWarningMessage();
}
