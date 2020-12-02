<?php

use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\MediaWikiServices;
use MediaWiki\Session\SessionManager;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class DisableOATHAuthForUser extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Remove OATHAuth from a specific user' );
		$this->addArg( 'user', 'The username to remove OATHAuth from.' );
		$this->requireExtension( 'OATHAuth' );
	}

	public function execute() {
		$username = $this->getArg( 0 );

		$user = User::newFromName( $username );
		if ( $user && $user->getId() === 0 ) {
			$this->error( "User $username doesn't exist!", 1 );
		}

		$repo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
		$oathUser = $repo->findByUser( $user );
		$module = $oathUser->getModule();
		if ( !( $module instanceof IModule ) || $module->isEnabled( $oathUser ) === false ) {
			$this->error( "User $username doesn't have OATHAuth enabled!", 1 );
		}

		$repo->remove( $oathUser, 'Maintenance script' );
		// Kill all existing sessions. If this disable was social-engineered by an attacker,
		// the legitimate user will hopefully login again and notice that the second factor
		// is missing or different, and alert the operators.
		SessionManager::singleton()->invalidateSessionsForUser( $user );

		$this->output( "OATHAuth disabled for $username.\n" );
	}
}

$maintClass = DisableOATHAuthForUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
