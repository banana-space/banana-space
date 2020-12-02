<?php

namespace MediaWiki\Extension\OATHAuth\Hook\GetPreferences;

use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\MediaWikiServices;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\LabelWidget;
use SpecialPage;
use User;

class AuthModule {
	/**
	 * @var OATHUserRepository
	 */
	protected $userRepo;
	/**
	 * @var \User
	 */
	protected $user;
	/**
	 * @var array
	 */
	protected $preferences;

	/**
	 * @param User $user
	 * @param array &$preferences
	 * @return bool
	 */
	public static function callback( $user, &$preferences ) {
		$userRepo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
		$handler = new static( $userRepo, $user, $preferences );
		return $handler->execute();
	}

	/**
	 * @param OATHUserRepository $userRepo
	 * @param User $user
	 * @param array &$preferences
	 */
	protected function __construct( $userRepo, $user, &$preferences ) {
		$this->userRepo = $userRepo;
		$this->user = $user;
		$this->preferences = &$preferences;
	}

	protected function execute() {
		$oathUser = $this->userRepo->findByUser( $this->user );

		// If there is no existing module in user, and the user is not allowed to enable it,
		// we have nothing to show.
		if ( $oathUser->getModule() === null && !$this->user->isAllowed( 'oathauth-enable' ) ) {
			return true;
		}
		$module = $oathUser->getModule();

		$moduleLabel = $module === null ?
			wfMessage( 'oauthauth-ui-no-module' ) :
			$module->getDisplayName();

		$manageButton = new ButtonWidget( [
			'href' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL(),
			'label' => wfMessage( 'oathauth-ui-manage' )->text()
		] );
		$currentModuleLabel = new LabelWidget( [
			'label' => $moduleLabel->text()
		] );
		$control = new HorizontalLayout( [
			'items' => [
				$currentModuleLabel,
				$manageButton
			]
		] );

		$this->preferences['oathauth-module'] = [
			'type' => 'info',
			'raw' => true,
			'default' => (string)$control,
			'label-message' => 'oathauth-prefs-label',
			'section' => 'personal/info', ];

		return true;
	}
}
