<?php

use Flow\Container;
use Flow\Model\Workflow;
use MediaWiki\MediaWikiServices;

$installPath = getenv( 'MW_INSTALL_PATH' ) !== false ?
	getenv( 'MW_INSTALL_PATH' ) :
	__DIR__ . '/../../..';

require_once $installPath . '/maintenance/Maintenance.php';
// extending these - autoloader not yet wired up at the point these are interpreted
require_once $installPath . '/includes/utils/BatchRowWriter.php';
require_once $installPath . '/includes/utils/RowUpdateGenerator.php';

/**
 * Fixes Flow References & entries in categorylinks & related tables.
 *
 * @ingroup Maintenance
 */
class FlowFixLinks extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Fixes Flow References & entries in categorylinks & related tables' );

		$this->setBatchSize( 300 );

		$this->requireExtension( 'Flow' );
	}

	protected function getUpdateKey() {
		return __CLASS__ . ':v2';
	}

	protected function doDBUpdates() {
		// disable Echo notifications for this script
		global $wgEchoNotifications;

		$wgEchoNotifications = [];

		$this->removeVirtualPages();
		$this->rebuildCoreTables();

		$this->output( "Completed\n" );

		return true;
	}

	protected function removeVirtualPages() {
		/** @var \Flow\Data\ObjectManager $storage */
		$storage = Container::get( 'storage.wiki_reference' );
		$links = $storage->find( [
			'ref_src_wiki' => wfWikiID(),
			'ref_target_namespace' => [ -1, -2 ],
		] );
		if ( $links ) {
			$storage->multiRemove( $links, [] );
		}

		$this->output( "Removed " . count( $links ) . " links to special pages.\n" );
	}

	protected function rebuildCoreTables() {
		$dbw = wfGetDB( DB_MASTER );
		$dbr = Container::get( 'db.factory' )->getDB( DB_REPLICA );
		/** @var \Flow\LinksTableUpdater $linksTableUpdater */
		$linksTableUpdater = Container::get( 'reference.updater.links-tables' );

		$iterator = new BatchRowIterator( $dbr, 'flow_workflow', 'workflow_id', $this->mBatchSize );
		$iterator->setFetchColumns( [ '*' ] );
		$iterator->addConditions( [ 'workflow_wiki' => wfWikiID() ] );

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		$count = 0;
		foreach ( $iterator as $rows ) {
			$this->beginTransaction( $dbw, __METHOD__ );

			foreach ( $rows as $row ) {
				$workflow = Workflow::fromStorageRow( (array)$row );
				$id = $workflow->getArticleTitle()->getArticleID();

				// delete existing links from DB
				$dbw->delete( 'pagelinks', [ 'pl_from' => $id ], __METHOD__ );
				$dbw->delete( 'imagelinks', [ 'il_from' => $id ], __METHOD__ );
				$dbw->delete( 'categorylinks', [ 'cl_from' => $id ], __METHOD__ );
				$dbw->delete( 'templatelinks', [ 'tl_from' => $id ], __METHOD__ );
				$dbw->delete( 'externallinks', [ 'el_from' => $id ], __METHOD__ );
				$dbw->delete( 'langlinks', [ 'll_from' => $id ], __METHOD__ );
				$dbw->delete( 'iwlinks', [ 'iwl_from' => $id ], __METHOD__ );

				// regenerate & store those links
				$linksTableUpdater->doUpdate( $workflow );
			}

			$this->commitTransaction( $dbw, __METHOD__ );

			$count += count( $rows );
			$this->output( "Rebuilt links for " . $count . " workflows...\n" );
			$lbFactory->waitForReplication();
		}
	}
}

$maintClass = FlowFixLinks::class;
require_once RUN_MAINTENANCE_IF_MAIN;
