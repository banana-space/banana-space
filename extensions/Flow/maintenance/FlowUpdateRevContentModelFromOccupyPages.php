<?php

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * This script should be run immediately before dropping the wgFlowOccupyPages
 * configuration variable, to ensure that rev_content_model is set appropriately.
 *
 * See comments at https://gerrit.wikimedia.org/r/#/c/228267/ .
 *
 * It sets rev_content_model to flow-board for the last revision of all occupied pages.
 *
 * @ingroup Maintenance
 */
class FlowUpdateRevContentModelFromOccupyPages extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Sets rev_content_model from wgFlowOccupyPages, in preparation for dropping that config variable.' );

		$this->requireExtension( 'Flow' );

		// Given the number of occupied pages, this probably doesn't need to be
		// batched; just being cautious.
		$this->setBatchSize( 10 );
	}

	public function execute() {
		global $wgFlowOccupyPages;

		$dbw = wfGetDB( DB_MASTER );

		$pageCount = count( $wgFlowOccupyPages );
		$overallInd = 0;
		$updatedCount = 0;
		$skippedCount = 0;

		while ( $overallInd < $pageCount ) {
			$this->beginTransaction( $dbw, __METHOD__ );
			$batchInd = 0;
			while ( $overallInd < $pageCount && $batchInd < $this->mBatchSize ) {
				$pageName = $wgFlowOccupyPages[$overallInd];
				$title = Title::newFromTextThrow( $pageName );
				$revId = $title->getLatestRevID( Title::GAID_FOR_UPDATE );
				if ( $revId !== 0 ) {
					$dbw->update(
						'revision',
						[
							'rev_content_model' =>
							CONTENT_MODEL_FLOW_BOARD
						],
						[ 'rev_id' => $revId ],
						__METHOD__
					);
					$updatedCount++;
					$this->output( "Set content model for \"{$title->getPrefixedDBkey()}\"\n" );
				} else {
					$skippedCount++;
					$this->output( "WARNING: Skipped \"{$title->getPrefixedDBkey()}\" because it does not exist\n" );
				}

				$overallInd++;
				$batchInd++;
			}

			$this->commitTransaction( $dbw, __METHOD__ );
			$this->output( "Completed batch.\n\n" );
		}

		$this->output( "Set content model for $updatedCount pages; skipped $skippedCount pages.\n" );
	}
}

$maintClass = FlowUpdateRevContentModelFromOccupyPages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
