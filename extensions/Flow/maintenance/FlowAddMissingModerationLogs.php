<?php

use Flow\Container;
use Flow\DbFactory;
use Flow\Model\AbstractRevision;
use Flow\Model\UUID;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Adjusts edit counts for all existing Flow data.
 *
 * @ingroup Maintenance
 */
class FlowAddMissingModerationLogs extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Backfills missing moderation logs from flow_revision.  Must be run separately for each affected wiki.' );

		$this->addOption( 'start', 'rev_id of last moderation revision that was logged correctly before regression.', true, true );
		$this->addOption( 'stop', 'rev_id of first revision that was logged correctly after moderation logging fix.', true, true );

		$this->setBatchSize( 300 );

		$this->requireExtension( 'Flow' );
	}

	protected function getUpdateKey() {
		return 'FlowAddMissingModerationLogs';
	}

	protected function doDBUpdates() {
		$container = Container::getContainer();

		/** @var DbFactory $dbFactory */
		$dbFactory = $container['db.factory'];
		$dbw = $dbFactory->getDB( DB_MASTER );

		$storage = $container['storage'];

		$moderationLoggingListener = $container['storage.post.listeners.moderation_logging'];

		$rowIterator = new BatchRowIterator(
			$dbw,
			/* table = */'flow_revision',
			/* primary key = */'rev_id',
			$this->mBatchSize
		);

		$rowIterator->setFetchColumns( [
			'rev_id',
			'rev_type',
		] );

		// Fetch rows that are a moderation action
		$rowIterator->addConditions( [
			'rev_change_type' => AbstractRevision::getModerationChangeTypes(),
			'rev_user_wiki' => wfWikiID(),
		] );

		$start = $this->getOption( 'start' );
		$startId = UUID::create( $start );
		$rowIterator->addConditions( [
			'rev_id > ' . $dbw->addQuotes( $startId->getBinary() ),
		] );

		$stop = $this->getOption( 'stop' );
		$stopId = UUID::create( $stop );
		$rowIterator->addConditions( [
			'rev_id < ' . $dbw->addQuotes( $stopId->getBinary() ),
		] );

		$total = $fail = 0;
		foreach ( $rowIterator as $batch ) {
			$this->beginTransaction( $dbw, __METHOD__ );
			foreach ( $batch as $row ) {
				$total++;
				$objectManager = $storage->getStorage( $row->rev_type );
				$revId = UUID::create( $row->rev_id );
				$obj = $objectManager->get( $revId );
				if ( !$obj ) {
					$this->error( 'Could not load revision: ' . $revId->getAlphadecimal() );
					$fail++;
					continue;
				}

				$workflow = $obj->getCollection()->getWorkflow();
				$moderationLoggingListener->onAfterInsert( $obj, [], [
					'workflow' => $workflow,
				] );
			}

			$this->commitTransaction( $dbw, __METHOD__ );
			$storage->clear();
			$dbFactory->waitForReplicas();
		}

		$this->output( "Processed a total of $total moderation revisions.\n" );
		if ( $fail !== 0 ) {
			$this->error( "Errors were encountered while processing $fail of them.\n" );
		}

		return true;
	}
}

$maintClass = FlowAddMissingModerationLogs::class;
require_once RUN_MAINTENANCE_IF_MAIN;
