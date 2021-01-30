<?php

use Flow\Container;
use Flow\DbFactory;
use Wikimedia\Rdbms\IDatabase;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Populate the *_user_ip fields within flow.  This only updates
 * the database and not the cache.  The model loading layer handles
 * cached old values.
 *
 * @ingroup Maintenance
 */
class FlowSetUserIp extends LoggedUpdateMaintenance {
	/**
	 * The number of entries completed
	 *
	 * @var int
	 */
	private $completeCount = 0;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Flow' );
	}

	protected function doDBUpdates() {
		/** @var DbFactory $dbf */
		$dbf = Container::get( 'db.factory' );
		$dbw = $dbf->getDB( DB_MASTER );
		$hasRun = false;

		$runUpdate = function ( $callback ) use ( $dbf, $dbw, &$hasRun ) {
			$hasRun = true;
			$continue = "\0";
			do {
				$continue = $callback( $dbw, $continue );
				$dbf->waitForReplicas();
			} while ( $continue !== null );
		};

		// run updates only if we have the required source data
		if ( $dbw->fieldExists( 'flow_workflow', 'workflow_user_text' ) ) {
			$runUpdate( [ $this, 'updateWorkflow' ] );
		}
		if ( $dbw->fieldExists( 'flow_tree_revision', 'tree_orig_user_text' ) ) {
			$runUpdate( [ $this, 'updateTreeRevision' ] );
		}
		if (
			$dbw->fieldExists( 'flow_revision', 'rev_user_text' ) &&
			$dbw->fieldExists( 'flow_revision', 'rev_mod_user_text' ) &&
			$dbw->fieldExists( 'flow_revision', 'rev_edit_user_text' )
		) {
			$runUpdate( [ $this, 'updateRevision' ] );
		}

		if ( $hasRun ) {
			$dbw->sourceFile( __DIR__ . '/../db_patches/patch-remove_usernames_2.sql' );
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
	public function updateWorkflow( IDatabase $dbw, $continue = null ) {
		$rows = $dbw->select(
			/* table */'flow_workflow',
			/* select */[ 'workflow_id', 'workflow_user_text' ],
			/* conds */[
				'workflow_id > ' . $dbw->addQuotes( $continue ),
				'workflow_user_ip IS NULL',
				'workflow_user_id = 0'
			],
			__METHOD__,
			/* options */[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'workflow_id' ]
		);

		$continue = null;

		foreach ( $rows as $row ) {
			$continue = $row->workflow_id;
			$dbw->update(
				/* table */'flow_workflow',
				/* update */[ 'workflow_user_ip' => $row->workflow_user_text ],
				/* conditions */[ 'workflow_id' => $row->workflow_id ],
				__METHOD__
			);

			$this->completeCount++;
		}

		return $continue;
	}

	public function updateTreeRevision( IDatabase $dbw, $continue = null ) {
		$rows = $dbw->select(
			/* table */'flow_tree_revision',
			/* select */[ 'tree_rev_id', 'tree_orig_user_text' ],
			[
				'tree_rev_id > ' . $dbw->addQuotes( $continue ),
				'tree_orig_user_ip IS NULL',
				'tree_orig_user_id = 0',
			],
			__METHOD__,
			/* options */[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'tree_rev_id' ]
		);

		$continue = null;
		foreach ( $rows as $row ) {
			$continue = $row->tree_rev_id;
			$dbw->update(
				/* table */'flow_tree_revision',
				/* update */[ 'tree_orig_user_ip' => $row->tree_orig_user_text ],
				/* conditions */[ 'tree_rev_id' => $row->tree_rev_id ],
				__METHOD__
			);

			$this->completeCount++;
		}

		return $continue;
	}

	public function updateRevision( IDatabase $dbw, $continue = null ) {
		$rows = $dbw->select(
			/* table */'flow_revision',
			/* select */[ 'rev_id', 'rev_user_id', 'rev_user_text', 'rev_mod_user_id',
				'rev_mod_user_text', 'rev_edit_user_id', 'rev_edit_user_text' ],
			/* conditions */ [
				'rev_id > ' . $dbw->addQuotes( $continue ),
				$dbw->makeList(
					[
						'rev_user_id' => 0,
						'rev_mod_user_id' => 0,
						'rev_edit_user_id' => 0,
					],
					LIST_OR
				),
			],
			__METHOD__,
			/* options */[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'rev_id' ]
		);

		$continue = null;
		foreach ( $rows as $row ) {
			$continue = $row->rev_id;
			$updates = [];

			if ( $row->rev_user_id == 0 ) {
				$updates['rev_user_ip'] = $row->rev_user_text;
			}
			if ( $row->rev_mod_user_id == 0 ) {
				$updates['rev_mod_user_ip'] = $row->rev_mod_user_text;
			}
			if ( $row->rev_edit_user_id == 0 ) {
				$updates['rev_edit_user_ip'] = $row->rev_edit_user_text;
			}
			if ( $updates ) {
				$dbw->update(
					/* table */ 'flow_revision',
					/* update */ $updates,
					/* conditions */ [ 'rev_id' => $row->rev_id ],
					__METHOD__
				);
			}
		}

		return $continue;
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'FlowSetUserIp';
	}
}

$maintClass = FlowSetUserIp::class; // Tells it to run the class
require_once RUN_MAINTENANCE_IF_MAIN;
