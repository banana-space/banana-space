<?php

use Flow\Container;
use Flow\DbFactory;
use Flow\Model\UUID;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Currently iterates through all revisions for debugging purposes, the
 * production version will want to only process the most recent revision
 * of each object.
 *
 * @ingroup Maintenance
 */
class FlowPopulateLinksTables extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Populates links tables for wikis deployed before change 110090" );
		$this->requireExtension( 'Flow' );
	}

	public function getUpdateKey() {
		return "FlowPopulateLinksTables";
	}

	public function doDBUpdates() {
		$this->output( "Populating links tables...\n" );
		$recorder = Container::get( 'reference.recorder' );
		$this->processHeaders( $recorder );
		$this->processPosts( $recorder );

		return true;
	}

	protected function processHeaders( $recorder ) {
		$storage = Container::get( 'storage.header' );
		$count = $this->mBatchSize;
		$id = '';
		/** @var DbFactory $dbf */
		$dbf = Container::get( 'db.factory' );
		$dbr = $dbf->getDB( DB_REPLICA );
		while ( $count === $this->mBatchSize ) {
			$count = 0;
			$res = $dbr->select(
				[ 'flow_revision' ],
				[ 'rev_type_id' ],
				[ 'rev_type' => 'header', 'rev_type_id > ' . $dbr->addQuotes( $id ) ],
				__METHOD__,
				[ 'ORDER BY' => 'rev_type_id ASC', 'LIMIT' => $this->mBatchSize ]
			);
			if ( !$res ) {
				throw new \MWException( 'SQL error in maintenance script ' . __METHOD__ );
			}
			foreach ( $res as $row ) {
				$count++;
				$id = $row->rev_type_id;
				$uuid = UUID::create( $id );
				$alpha = $uuid->getAlphadecimal();
				$header = $storage->get( $uuid );
				if ( $header ) {
					echo "Processing header $alpha\n";
					$recorder->onAfterInsert(
						$header, [],
						[
							'workflow' => $header->getCollection()->getWorkflow()
						]
					);
				}
			}
			$dbf->waitForReplicas();
		}
	}

	protected function processPosts( $recorder ) {
		$storage = Container::get( 'storage.post' );
		$count = $this->mBatchSize;
		$id = '';
		$dbr = Container::get( 'db.factory' )->getDB( DB_REPLICA );
		while ( $count === $this->mBatchSize ) {
			$count = 0;
			$res = $dbr->select(
				[ 'flow_tree_revision' ],
				[ 'tree_rev_id' ],
				[
					'tree_parent_id IS NOT NULL',
					'tree_rev_id > ' . $dbr->addQuotes( $id ),
				],
				__METHOD__,
				[ 'ORDER BY' => 'tree_rev_id ASC', 'LIMIT' => $this->mBatchSize ]
			);
			if ( !$res ) {
				throw new \MWException( 'SQL error in maintenance script ' . __METHOD__ );
			}
			foreach ( $res as $row ) {
				$count++;
				$id = $row->tree_rev_id;
				$uuid = UUID::create( $id );
				$alpha = $uuid->getAlphadecimal();
				$post = $storage->get( $uuid );
				if ( $post ) {
					echo "Processing post $alpha\n";
					$recorder->onAfterInsert(
						$post, [],
						[
							'workflow' => $post->getCollection()->getWorkflow()
						]
					);
				}
			}
		}
	}
}

$maintClass = FlowPopulateLinksTables::class;
require_once RUN_MAINTENANCE_IF_MAIN;
