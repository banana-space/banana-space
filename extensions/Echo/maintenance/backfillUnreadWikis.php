<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class BackfillUnreadWikis extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( "Backfill echo_unread_wikis table" );
		$this->addOption( 'rebuild', 'Only recompute already-existing rows' );
		$this->setBatchSize( 300 );
		$this->requireExtension( 'Echo' );
	}

	public function execute() {
		$dbFactory = MWEchoDbFactory::newFromDefault();
		$lookup = CentralIdLookup::factory();

		$rebuild = $this->hasOption( 'rebuild' );
		if ( $rebuild ) {
			$iterator = new BatchRowIterator(
				$dbFactory->getSharedDb( DB_REPLICA ),
				'echo_unread_wikis',
				'euw_user',
				$this->mBatchSize
			);
			$iterator->addConditions( [ 'euw_wiki' => wfWikiID() ] );
		} else {
			$userQuery = User::getQueryInfo();
			$iterator = new BatchRowIterator(
				wfGetDB( DB_REPLICA ), $userQuery['tables'], 'user_id', $this->mBatchSize
			);
			$iterator->setFetchColumns( $userQuery['fields'] );
			$iterator->addJoinConditions( $userQuery['joins'] );
		}

		$processed = 0;
		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				if ( $rebuild ) {
					$user = $lookup->localUserFromCentralId( $row->euw_user, CentralIdLookup::AUDIENCE_RAW );
				} else {
					$user = User::newFromRow( $row );
				}
				if ( !$user ) {
					continue;
				}

				$notifUser = MWEchoNotifUser::newFromUser( $user );
				$uw = EchoUnreadWikis::newFromUser( $user );
				if ( $uw ) {
					$alertCount = $notifUser->getNotificationCount( EchoAttributeManager::ALERT, false );
					$alertUnread = $notifUser->getLastUnreadNotificationTime( EchoAttributeManager::ALERT, false );

					$msgCount = $notifUser->getNotificationCount( EchoAttributeManager::MESSAGE, false );
					$msgUnread = $notifUser->getLastUnreadNotificationTime( EchoAttributeManager::MESSAGE, false );

					if ( ( $alertCount !== 0 && $alertUnread === false ) ||
						( $msgCount !== 0 && $msgUnread === false )
					) {
						// If there are alerts, there should be an alert timestamp (same for messages).

						// Otherwise, there is a race condition between the two values, indicating there's already
						// just been an updateCount call, so we can skip this user.
						continue;
					}

					$uw->updateCount( wfWikiID(), $alertCount, $alertUnread, $msgCount, $msgUnread );
				}
			}

			$processed += count( $batch );
			$this->output( "Updated $processed users.\n" );
			$dbFactory->waitForReplicas();
		}
	}
}

$maintClass = BackfillUnreadWikis::class;
require_once RUN_MAINTENANCE_IF_MAIN;
