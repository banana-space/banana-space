<?php
/**
 * Update the Per User Blocklist from Usernames to User Ids.
 *
 * @ingroup Maintenance
 */

use MediaWiki\MediaWikiServices;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Maintenance script that changes the usernames to ids.
 *
 * @ingroup Maintenance
 */
class EchoUpdatePerUserBlacklist extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Update echo-notifications-blacklist User Preference from Usernames to Ids' );
		$this->setBatchSize( 100 );
		$this->requireExtension( 'Echo' );
	}

	public function getUpdateKey() {
		return __CLASS__;
	}

	public function doDBUpdates() {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$dbw = $this->getDB( DB_MASTER );
		$dbr = $this->getDB( DB_REPLICA );
		$iterator = new BatchRowIterator(
			$dbr,
			'user_properties',
			[ 'up_user', 'up_property' ],
			$this->mBatchSize
		);
		$iterator->setFetchColumns( [
			'up_user',
			'up_value'
		] );
		$iterator->addConditions( [
			'up_property' => 'echo-notifications-blacklist'
		] );

		$this->output( "Updating Echo Notification Blacklist...\n" );

		$lookup = CentralIdLookup::factory();
		$processed = 0;
		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				if ( !$row->up_value ) {
					continue;
				}

				$value = explode( "\n", $row->up_value );
				$names = array_filter( $value, function ( $item ) {
					return !is_numeric( $item );
				} );

				// If all of the values are numeric then the user has already been
				// converted.
				if ( !$names ) {
					continue;
				}

				$user = User::newFromId( $row->up_user );
				$ids = $lookup->centralIdsFromNames( $names, $user );

				$dbw->update(
					'user_properties',
					[
						'up_value'  => implode( "\n", $ids ),
					],
					[
						'up_user' => $row->up_user,
						'up_property' => 'echo-notifications-blacklist',
					],
					__METHOD__
				);
				$processed += $dbw->affectedRows();
				$lbFactory->waitForReplication();
			}

			$this->output( "Updated $processed Users\n" );
		}

		return true;
	}
}

$maintClass = EchoUpdatePerUserBlacklist::class;
require_once RUN_MAINTENANCE_IF_MAIN;
