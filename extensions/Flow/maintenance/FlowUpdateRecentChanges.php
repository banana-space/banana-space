<?php

use Flow\Data\Listener\RecentChangesListener;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Updates recentchanges entries to contain information to build the
 * AbstractBlock objects.
 *
 * @ingroup Maintenance
 */
class FlowUpdateRecentChanges extends LoggedUpdateMaintenance {
	/**
	 * The number of entries completed
	 *
	 * @var int
	 */
	private $completeCount = 0;

	/**
	 * Max number of records to process at a time
	 *
	 * @var int
	 */
	protected $batchSize = 300;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Flow' );
	}

	protected function doDBUpdates() {
		$dbw = wfGetDB( DB_MASTER );

		$continue = 0;

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		while ( $continue !== null ) {
			$continue = $this->refreshBatch( $dbw, $continue );
			$lbFactory->waitForReplication();
		}

		return true;
	}

	/**
	 * Refreshes a batch of recentchanges entries
	 *
	 * @param IDatabase $dbw
	 * @param int|null $continue The next batch starting at rc_id
	 * @return int|null Start id for the next batch
	 */
	public function refreshBatch( IDatabase $dbw, $continue = null ) {
		$rows = $dbw->select(
			/* table */'recentchanges',
			/* select */[ 'rc_id', 'rc_params' ],
			/* conds */[ "rc_id > $continue", 'rc_source' => RecentChangesListener::SRC_FLOW ],
			__METHOD__,
			/* options */[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'rc_id' ]
		);

		$continue = null;

		foreach ( $rows as $row ) {
			$continue = $row->rc_id;

			// build params
			Wikimedia\suppressWarnings();
			$params = unserialize( $row->rc_params );
			Wikimedia\restoreWarnings();
			if ( !$params ) {
				$params = [];
			}

			// Don't fix entries that have been dealt with already
			if ( !isset( $params['flow-workflow-change']['type'] ) ) {
				continue;
			}

			// Set action, based on older 'type' values
			switch ( $params['flow-workflow-change']['type'] ) {
				case 'flow-rev-message-edit-title':
				case 'flow-edit-title':
					$params['flow-workflow-change']['action'] = 'edit-title';
					$params['flow-workflow-change']['block'] = 'topic';
					$params['flow-workflow-change']['revision_type'] = 'PostRevision';
					break;

				case 'flow-rev-message-new-post':
				case 'flow-new-post':
					$params['flow-workflow-change']['action'] = 'new-post';
					$params['flow-workflow-change']['block'] = 'topic';
					$params['flow-workflow-change']['revision_type'] = 'PostRevision';
					break;

				case 'flow-rev-message-edit-post':
				case 'flow-edit-post':
					$params['flow-workflow-change']['action'] = 'edit-post';
					$params['flow-workflow-change']['block'] = 'topic';
					$params['flow-workflow-change']['revision_type'] = 'PostRevision';
					break;

				case 'flow-rev-message-reply':
				case 'flow-reply':
					$params['flow-workflow-change']['action'] = 'reply';
					$params['flow-workflow-change']['block'] = 'topic';
					$params['flow-workflow-change']['revision_type'] = 'PostRevision';
					break;

				case 'flow-rev-message-restored-post':
				case 'flow-post-restored':
					$params['flow-workflow-change']['action'] = 'restore-post';
					$params['flow-workflow-change']['block'] = 'topic';
					$params['flow-workflow-change']['revision_type'] = 'PostRevision';
					break;

				case 'flow-rev-message-hid-post':
				case 'flow-post-hidden':
					$params['flow-workflow-change']['action'] = 'hide-post';
					$params['flow-workflow-change']['block'] = 'topic';
					$params['flow-workflow-change']['revision_type'] = 'PostRevision';
					break;

				case 'flow-rev-message-deleted-post':
				case 'flow-post-deleted':
					$params['flow-workflow-change']['action'] = 'delete-post';
					$params['flow-workflow-change']['block'] = 'topic';
					$params['flow-workflow-change']['revision_type'] = 'PostRevision';
					break;

				case 'flow-rev-message-censored-post':
				case 'flow-post-censored':
					$params['flow-workflow-change']['action'] = 'suppress-post';
					$params['flow-workflow-change']['block'] = 'topic';
					$params['flow-workflow-change']['revision_type'] = 'PostRevision';
					break;

				case 'flow-rev-message-edit-header':
				case 'flow-edit-summary':
					$params['flow-workflow-change']['action'] = 'edit-header';
					$params['flow-workflow-change']['block'] = 'header';
					$params['flow-workflow-change']['revision_type'] = 'Header';
					break;

				case 'flow-rev-message-create-header':
				case 'flow-create-summary':
				case 'flow-create-header':
					$params['flow-workflow-change']['action'] = 'create-header';
					$params['flow-workflow-change']['block'] = 'header';
					$params['flow-workflow-change']['revision_type'] = 'Header';
					break;
			}

			unset( $params['flow-workflow-change']['type'] );

			// update log entry
			$dbw->update(
				'recentchanges',
				[ 'rc_params' => serialize( $params ) ],
				[ 'rc_id' => $row->rc_id ],
				__METHOD__
			);

			$this->completeCount++;
		}

		return $continue;
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'FlowUpdateRecentChanges';
	}
}

$maintClass = FlowUpdateRecentChanges::class; // Tells it to run the class
require_once RUN_MAINTENANCE_IF_MAIN;
