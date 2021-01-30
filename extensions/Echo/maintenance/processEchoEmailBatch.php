<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * A maintenance script that processes email digest
 */
class ProcessEchoEmailBatch extends Maintenance {

	/**
	 * Max number of records to process at a time
	 * @var int
	 */
	protected $batchSize = 300;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Process email digest" );

		$this->addOption(
			"ignoreConfiguredSchedule",
			"Send all pending notifications immediately even if configured to be weekly or daily.",
			false, false, "i" );

		$this->requireExtension( 'Echo' );
	}

	public function execute() {
		$lbFactory = MWEchoDbFactory::newFromDefault();
		$ignoreConfiguredSchedule = $this->getOption( "ignoreConfiguredSchedule", 0 );

		$this->output( "Started processing... \n" );

		$startUserId = 0;
		$count = $this->batchSize;

		while ( $count === $this->batchSize ) {
			$count = 0;

			$res = MWEchoEmailBatch::getUsersToNotify( $startUserId, $this->batchSize );

			$updated = false;
			foreach ( $res as $row ) {
				$userId = intval( $row->eeb_user_id );
				if ( $userId && $userId > $startUserId ) {
					$emailBatch = MWEchoEmailBatch::newFromUserId( $userId, !$ignoreConfiguredSchedule );
					if ( $emailBatch ) {
						$this->output( "processing user_Id " . $userId . " \n" );
						$emailBatch->process();
					}
					$startUserId = $userId;
					$updated = true;
				}
				$count++;
			}
			$lbFactory->waitForReplicas();

			// double check to make sure that the id is updated
			if ( !$updated ) {
				break;
			}
		}

		$this->output( "Completed \n" );
	}
}

$maintClass = ProcessEchoEmailBatch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
