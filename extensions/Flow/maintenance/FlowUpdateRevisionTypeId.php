<?php

use Flow\Container;
use Flow\DbFactory;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Update flow_revision.rev_type_id
 *
 * @ingroup Maintenance
 */
class FlowUpdateRevisionTypeId extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Update flow_revision.rev_type_id" );
		$this->requireExtension( 'Flow' );
		$this->setBatchSize( 300 );
	}

	protected function doDBUpdates() {
		$revId = '';
		$count = $this->mBatchSize;
		/** @var DbFactory $dbFactory */
		$dbFactory = Container::get( 'db.factory' );
		$dbr = $dbFactory->getDB( DB_REPLICA );
		$dbw = $dbFactory->getDB( DB_MASTER );

		// If table flow_header_revision does not exist, that means the wiki
		// has run the data migration before or the wiki starts from scratch,
		// there is no point to run the script against invalid tables
		if ( !$dbr->tableExists( 'flow_header_revision', __METHOD__ ) ) {
			return true;
		}

		while ( $count == $this->mBatchSize ) {
			$count = 0;
			$res = $dbr->select(
				[ 'flow_revision', 'flow_tree_revision', 'flow_header_revision' ],
				[ 'rev_id', 'rev_type', 'tree_rev_descendant_id', 'header_workflow_id' ],
				[ 'rev_id > ' . $dbr->addQuotes( $revId ) ],
				__METHOD__,
				[ 'ORDER BY' => 'rev_id ASC', 'LIMIT' => $this->mBatchSize ],
				[
					'flow_tree_revision' => [ 'LEFT JOIN', 'rev_id=tree_rev_id' ],
					'flow_header_revision' => [ 'LEFT JOIN', 'rev_id=header_rev_id' ]
				]
			);

			if ( $res ) {
				foreach ( $res as $row ) {
					$count++;
					$revId = $row->rev_id;
					switch ( $row->rev_type ) {
						case 'header':
							$this->updateRevision( $dbw, $row->rev_id, $row->header_workflow_id );
						break;
						case 'post':
							$this->updateRevision( $dbw, $row->rev_id, $row->tree_rev_descendant_id );
						break;
					}
				}
			} else {
				throw new MWException( 'SQL error in maintenance script ' . __CLASS__ . '::' . __METHOD__ );
			}
			$dbFactory->waitForReplicas();
		}

		$dbw->dropTable( 'flow_header_revision', __METHOD__ );

		return true;
	}

	private function updateRevision( $dbw, $revId, $revTypeId ) {
		if ( $revTypeId === null ) {
			// this shouldn't actually be happening, but if it is, ignoring it
			// will not make things worse - the revision is lost already
			return;
		}

		$res = $dbw->update(
			'flow_revision',
			[ 'rev_type_id' => $revTypeId ],
			[ 'rev_id' => $revId ],
			__METHOD__
		);
		if ( !$res ) {
			throw new MWException( 'SQL error in maintenance script ' . __CLASS__ . '::' . __METHOD__ );
		}
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'FlowUpdateRevisionTypeId';
	}
}

$maintClass = FlowUpdateRevisionTypeId::class;
require_once RUN_MAINTENANCE_IF_MAIN;
