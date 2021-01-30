<?php

use Flow\Container;
use Flow\Content\BoardContent;
use Flow\Exception\UnknownWorkflowIdException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\RevisionRecord;
use MediaWiki\Storage\SlotRecord;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Changes Flow boards and their topics to be associated with their current title, based on the JSON content
 * Fixes inconsistent bugs like T138310.
 *
 * There is a dry run available.
 *
 * @ingroup Maintenance
 */
class FlowFixInconsistentBoards extends Maintenance {
	/**
	 * @var Flow\DbFactory
	 */
	protected $dbFactory;

	/**
	 * @var Flow\WorkflowLoaderFactory
	 */
	protected $workflowLoaderFactory;

	/**
	 * @var Flow\BoardMover
	 */
	protected $boardMover;

	/**
	 * @var Flow\Data\ManagerGroup
	 */
	protected $storage;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Changes Flow boards and their topics to be associated with their ' .
			'current title, based on the JSON content.  Must be run separately for each affected wiki.' );

		$this->addOption( 'dry-run', 'Only prints the board names, without changing anything.' );
		$this->addOption( 'namespaceName', 'Name of namespace to check, otherwise all', false, true );
		$this->addOption( 'limit', 'Limit of inconsistent pages to identify (and fix if not a dry ' .
			'run). Defaults to no limit', false, true );

		$this->setBatchSize( 300 );

		$this->requireExtension( 'Flow' );
	}

	public function execute() {
		global $wgLang;

		$this->dbFactory = Container::get( 'db.factory' );
		$this->workflowLoaderFactory = Container::get( 'factory.loader.workflow' );
		$this->boardMover = Container::get( 'board_mover' );
		$this->storage = Container::get( 'storage' );

		$dryRun = $this->hasOption( 'dry-run' );

		$limit = $this->getOption( 'limit' );

		$wikiDbw = $this->dbFactory->getWikiDB( DB_MASTER );

		$iterator = new BatchRowIterator( $wikiDbw, 'page', 'page_id', $this->mBatchSize );
		$iterator->setFetchColumns( [ 'page_namespace', 'page_title', 'page_latest' ] );
		$iterator->addConditions( [
			'page_content_model' => CONTENT_MODEL_FLOW_BOARD,
		] );

		if ( $this->hasOption( 'namespaceName' ) ) {
			$namespaceName = $this->getOption( 'namespaceName' );
			$namespaceId = $wgLang->getNsIndex( $namespaceName );

			if ( !$namespaceId ) {
				$this->error( "'$namespaceName' is not a valid namespace name" );
				return false;
			}

			if ( $namespaceId == NS_TOPIC ) {
				$this->error( 'This script can not be run on the Flow topic namespace' );
				return false;
			}

			$iterator->addConditions( [
				'page_namespace' => $namespaceId,
			] );
		} else {
			$iterator->addConditions( [
				'page_namespace != ' . NS_TOPIC,
			] );
		}

		$checkedCount = 0;
		$inconsistentCount = 0;

		// Not all of $inconsistentCount are fixable by the current script.
		$fixableInconsistentCount = 0;

		foreach ( $iterator as $rows ) {
			foreach ( $rows as $row ) {
				$checkedCount++;
				$coreTitle = Title::makeTitle( $row->page_namespace, $row->page_title );
				$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $row->page_latest );
				$content = $revision->getContent( SlotRecord::MAIN, RevisionRecord::RAW );
				if ( !$content instanceof BoardContent ) {
					$actualClass = ( is_object( $content ) ? get_class( $content ) : gettype( $content ) );
					$this->error( "ERROR: '$coreTitle' content is a '$actualClass', but should be '"
						. BoardContent::class . "'." );
					continue;
				}
				$workflowId = $content->getWorkflowId();
				if ( $workflowId === null ) {
					// See T153320.  If the workflow exists, it could
					// be looked up by title/page ID and the JSON could
					// be fixed with an edit.
					// Otherwise, the core revision has to be deleted.  This
					// script does not do either of these things.
					$this->error( "ERROR: '$coreTitle' JSON content does not have a valid workflow ID." );
					continue;
				}

				$workflowIdAlphadecimal = $workflowId->getAlphadecimal();

				try {
					$workflow = $this->workflowLoaderFactory->loadWorkflowById( false, $workflowId );
				} catch ( UnknownWorkflowIdException $ex ) {
					// This is a different error (a core page refers to
					// a non-existent workflow), which this script can not fix.
					$this->error( "ERROR: '$coreTitle' refers to workflow ID " .
						"'$workflowIdAlphadecimal', which could not be found." );
					continue;
				}

				if ( !$workflow->matchesTitle( $coreTitle ) ) {
					$pageId = (int)$row->page_id;

					$workflowTitle = $workflow->getOwnerTitle();
					$this->output( "INCONSISTENT: Core title for '$workflowIdAlphadecimal' is " .
						"'$coreTitle', but Flow title is '$workflowTitle'\n" );

					$inconsistentCount++;

					// Sanity check, or this will fail in BoardMover
					$workflowByPageId = $this->storage->find( 'Workflow', [
							'workflow_wiki' => wfWikiID(),
							'workflow_page_id' => $pageId,
						] );

					if ( !$workflowByPageId ) {
						$this->error( "ERROR: '$coreTitle' has page ID '$pageId', but no workflow " .
							"is linked to this page ID" );
						continue;
					}

					if ( !$dryRun ) {
						$this->boardMover->move( $pageId, $coreTitle );
						$this->boardMover->commit();
						$this->output( "FIXED: Updated '$workflowIdAlphadecimal' to match core " .
							"title, '$coreTitle'\n" );
					}

					$fixableInconsistentCount++;

					if ( $limit !== null && $fixableInconsistentCount >= $limit ) {
						break;
					}
				}
			}

			$action = $dryRun ? 'identified as fixable' : 'fixed';
			$this->output( "\nChecked a total of $checkedCount Flow boards.  Of those, " .
				"$inconsistentCount boards had an inconsistent title; $fixableInconsistentCount " .
				"were $action.\n" );
			if ( $limit !== null && $fixableInconsistentCount >= $limit ) {
				break;
			}
		}
	}
}

$maintClass = FlowFixInconsistentBoards::class;
require_once RUN_MAINTENANCE_IF_MAIN;
