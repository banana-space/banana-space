<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/**
 * @ingroup Maintenance
 */
class CleanupArchiveUserText extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Update the archive table where users were ' .
			'previously renamed, but their archive contributions were not';

		$this->requireExtension( 'Renameuser' );
	}

	public function execute() {
		if ( RenameuserSQL::getActorMigrationStage() >= MIGRATION_NEW ) {
			$this->output( "archive.ar_user_text is no longer used.\n" );
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		do {
			$res = $dbw->select(
				[ 'archive', 'user' ],
				[ 'DISTINCT ar_user_text', 'user_name', 'ar_user' ],
				[
					'ar_user_text <> user_name',
					'ar_user = user_id',
				],
				__METHOD__,
				[ 'LIMIT' => 50 ]
			);
			$results = 0;
			foreach ( $res as $row ) {
				$results++;
				$this->output( "User:{$row->ar_user_text} => User:{$row->user_name} " );
				$dbw->update(
					'archive',
					[ 'ar_user_text' => $row->user_name ],
					[
						'ar_user_text' => $row->ar_user_text,
						'ar_user' => $row->ar_user,
					],
					__METHOD__,
					[ 'LIMIT' => 50 ]
				);
				$affected = $dbw->affectedRows();
				$this->output( "$affected rows\n" );
				wfWaitForSlaves();
			}
		} while ( $results === 50 );
	}

	public function getDbType() {
		return Maintenance::DB_ADMIN;
	}
}

$maintClass = 'CleanupArchiveUserText';
require_once RUN_MAINTENANCE_IF_MAIN;
