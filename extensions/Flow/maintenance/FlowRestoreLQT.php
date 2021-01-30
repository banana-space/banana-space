<?php

use Flow\Container;
use Flow\DbFactory;
use Flow\Import\ArchiveNameHelper;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\RevisionRecord;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

class FlowRestoreLQT extends Maintenance {
	/**
	 * @var User
	 */
	protected $talkpageManagerUser;

	/**
	 * @var DbFactory
	 */
	protected $dbFactory;

	/**
	 * @var bool
	 */
	protected $dryRun = false;

	/**
	 * @var bool
	 */
	protected $overwrite = false;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Restores LQT boards after a Flow conversion (revert LQT conversion ' .
			'edits & move LQT boards back)' );

		$this->addOption( 'dryrun', 'Simulate script run, without making actual changes' );
		$this->addOption( 'overwrite-flow', 'Removes the Flow board entirely, restoring LQT to ' .
			'its original location' );

		$this->setBatchSize( 1 );

		$this->requireExtension( 'Flow' );
	}

	public function execute() {
		$this->talkpageManagerUser = Flow\Hooks::getOccupationController()->getTalkpageManager();
		$this->dbFactory = Container::get( 'db.factory' );
		$this->dryRun = $this->getOption( 'dryrun', false );
		$this->overwrite = $this->getOption( 'overwrite-flow', false );

		$this->output( "Restoring posts...\n" );
		$this->restoreLQTThreads();

		$this->output( "Restoring boards...\n" );
		$this->restoreLQTBoards();
	}

	/**
	 * During an import, LQT boards are moved out of the way (archived) to make
	 * place for the Flow board.
	 * And after completing an import, LQT boards are disabled with
	 * {{#useliquidthreads:0}}
	 * That's all perfectly fine assuming the conversion goes well, but we'll
	 * want to go back to the original content with this script...
	 */
	protected function restoreLQTBoards() {
		$dbr = $this->dbFactory->getWikiDB( DB_REPLICA );

		$revWhere = ActorMigration::newMigration()
			->getWhere( $dbr, 'rev_user', $this->talkpageManagerUser );
		$logWhere = ActorMigration::newMigration()
			->getWhere( $dbr, 'log_user', $this->talkpageManagerUser );

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		foreach ( $revWhere['orconds'] as $revCond ) {
			foreach ( $logWhere['orconds'] as $logCond ) {
				$startId = 0;
				do {
					// fetch all LQT boards that have been moved out of the way,
					// with their original title & their current title
					$rows = $dbr->select(
						[ 'logging', 'page', 'revision' ] + $revWhere['tables'] + $logWhere['tables'],
						// log_namespace & log_title will be the original location
						// page_namespace & page_title will be the current location
						// rev_id is the first Flow talk page manager edit id
						// log_id is the log entry for when importer moved LQT page
						[ 'log_namespace', 'log_title', 'page_id', 'page_namespace', 'page_title',
							'rev_id' => 'MIN(rev_id)', 'log_id' ],
						[
							$logCond,
							'log_type' => 'move',
							'page_content_model' => 'wikitext',
							'page_id > ' . $dbr->addQuotes( $startId ),
						],
						__METHOD__,
						[
							'GROUP BY' => 'rev_page',
							'LIMIT' => $this->mBatchSize,
							'ORDER BY' => 'log_id ASC',
						],
						[
							'page' => [
								'INNER JOIN',
								[ 'page_id = log_page' ],
							],
							'revision' => [
								'INNER JOIN',
								[ 'rev_page = log_page', $revCond ],
							],
						] + $revWhere['joins'] + $logWhere['joins']
					);

					foreach ( $rows as $row ) {
						$from = Title::newFromText( $row->page_title, $row->page_namespace );
						$to = Title::newFromText( $row->log_title, $row->log_namespace );

						// undo {{#useliquidthreads:0}}
						$this->restorePageRevision( $row->page_id, $row->rev_id );
						// undo page move to archive location
						$this->restoreLQTPage( $from, $to, $row->log_id );

						$startId = $row->page_id;
					}

					$lbFactory->waitForReplication();
				} while ( $rows->numRows() >= $this->mBatchSize );
			}
		}
	}

	/**
	 * After converting an LQT thread to Flow, it's content is altered to
	 * redirect to the new Flow topic.
	 * This finds all last original revisions & restores them.
	 */
	protected function restoreLQTThreads() {
		$dbr = $this->dbFactory->getWikiDB( DB_REPLICA );

		$revWhere = ActorMigration::newMigration()
			->getWhere( $dbr, 'rev_user', $this->talkpageManagerUser );

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		foreach ( $revWhere['orconds'] as $revCond ) {
			$startId = 0;
			do {
				// for every LQT post, find the first edit by Flow talk page manager
				// (to redirect to the new Flow copy)
				$rows = $dbr->select(
					[ 'page', 'revision' ] + $revWhere['tables'],
					[ 'rev_page', 'rev_id' => ' MIN(rev_id)' ],
					[
						'page_namespace' => [ NS_LQT_THREAD, NS_LQT_SUMMARY ],
						$revCond,
						'page_id > ' . $dbr->addQuotes( $startId ),
					],
					__METHOD__,
					[
						'GROUP BY' => 'page_id',
						'LIMIT' => $this->mBatchSize,
						'ORDER BY' => 'page_id ASC',
					],
					[
						'revision' => [
							'INNER JOIN',
							[ 'rev_page = page_id' ],
						],
					] + $revWhere['joins']
				);

				foreach ( $rows as $row ) {
					// undo #REDIRECT edit
					$this->restorePageRevision( $row->rev_page, $row->rev_id );
					$startId = $row->rev_page;
				}

				$lbFactory->waitForReplication();
			} while ( $rows->numRows() >= $this->mBatchSize );
		}
	}

	/**
	 * @param Title $lqt Title of the LQT board
	 * @param Title $flow Title of the Flow board
	 * @param int $logId Log id for when LQT board was moved by import
	 * @return Status
	 * @throws MWException
	 */
	protected function restoreLQTPage( Title $lqt, Title $flow, $logId ) {
		if ( $lqt->equals( $flow ) ) {
			// is at correct location already (probably a rerun of this script)
			return Status::newGood();
		}

		$archiveNameHelper = new ArchiveNameHelper();

		if ( !$flow->exists() ) {
			$this->movePage( $lqt, $flow, '/* Restore LQT board to original location */' );
		} else {
			/*
			 * The importer will query the log table to find the LQT archive
			 * location. It will assume that Flow talk page manager moved the
			 * LQT board to its archive location, and will not recognize the
			 * board if it's been moved by someone else.
			 * Because of that feature (yes, that is intended), we need to make
			 * sure that - in order to enable LQT imports to be picked up again
			 * after this - the move from <original page> to <archive page>
			 * happens in 1 go, by Flow talk page manager.
			 */
			if ( !$this->overwrite ) {
				/*
				 * Before we go moving pages around like crazy, let's see if we
				 * actually need to. While it's certainly possible that the LQT
				 * pages have been moved since the import and we need to fix
				 * them, it's very likely that they haven't. In that case, we
				 * won't have to do the complex moves.
				 */
				$dbr = $this->dbFactory->getWikiDB( DB_REPLICA );
				$count = $dbr->selectRowCount(
					[ 'logging' ],
					'*',
					[
						'log_page' => $lqt->getArticleID(),
						'log_type' => 'move',
						'log_id > ' . $dbr->addQuotes( $logId ),
					],
					__METHOD__
				);

				if ( $count > 0 ) {
					$this->output( "Ensuring LQT board '{$lqt->getPrefixedDBkey()}' is " .
						"recognized as archive of Flow board '{$flow->getPrefixedDBkey()}'.\n" );

					// 1: move Flow board out of the way so we can restore LQT to
					// its original location
					$archive = $archiveNameHelper->decideArchiveTitle( $flow, [ '%s/Flow Archive %d' ] );
					$this->movePage( $flow, $archive, '/* Make place to restore LQT board */' );

					// 2: move LQT board to the original location
					$this->movePage( $lqt, $flow, '/* Restore LQT board to original location */' );

					// 3: move LQT board back to archive location
					$this->movePage( $flow, $lqt, '/* Restore LQT board to archive location */' );

					// 4: move Flow board back to the original location
					$this->movePage( $archive, $flow, '/* Restore Flow board to correct location */' );
				}
			} else {
				$this->output( "Deleting '{$flow->getPrefixedDBkey()}' & moving " .
					"'{$lqt->getPrefixedDBkey()}' there.\n" );

				if ( !$this->dryRun ) {
					$page = WikiPage::factory( $flow );
					$page->doDeleteArticleReal(
						'/* Make place to restore LQT board */',
						$this->talkpageManagerUser,
						false,
						null,
						$error,
						null,
						[],
						'delete',
						true
					);
				}

				$this->movePage( $lqt, $flow, '/* Restore LQT board to original location */' );
			}
		}
	}

	/**
	 * @param Title $from
	 * @param Title $to
	 * @param string $reason
	 * @return Status
	 */
	protected function movePage( Title $from, Title $to, $reason ) {
		$this->output( "	Moving '{$from->getPrefixedDBkey()}' to '{$to->getPrefixedDBkey()}'.\n" );

		$movePage = new MovePage( $from, $to );
		$status = $movePage->isValidMove();
		if ( !$status->isGood() ) {
			return $status;
		}

		if ( $this->dryRun ) {
			return Status::newGood();
		}

		return $movePage->move( $this->talkpageManagerUser, $reason, false );
	}

	/**
	 * @param int $pageId
	 * @param int $nextRevisionId Revision of the first *bad* revision
	 * @return Status
	 * @throws MWException
	 */
	protected function restorePageRevision( $pageId, $nextRevisionId ) {
		global $wgLang;

		$page = WikiPage::newFromID( $pageId );
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$nextRevision = $revisionLookup->getRevisionById( $nextRevisionId );
		$revision = $revisionLookup->getPreviousRevision( $nextRevision );

		if ( $page->getContent()->equals( $revision->getContent( SlotRecord::MAIN ) ) ) {
			// has correct content already (probably a rerun of this script)
			return Status::newGood();
		}

		$content = $revision->getContent( SlotRecord::MAIN )->serialize();
		$content = $wgLang->truncateForVisual( $content, 150 );
		$content = str_replace( "\n", '\n', $content );
		$this->output( "Restoring revision {$revision->getId()} for LQT page {$pageId}: {$content}\n" );

		if ( $this->dryRun ) {
			return Status::newGood();
		} else {
			return $page->doEditContent(
				$revision->getContent( SlotRecord::MAIN, RevisionRecord::RAW ),
				'/* Restore LQT topic content */',
				EDIT_UPDATE | EDIT_MINOR | EDIT_FORCE_BOT,
				$revision->getId(),
				$this->talkpageManagerUser
			);
		}
	}
}

$maintClass = FlowRestoreLQT::class;
require_once RUN_MAINTENANCE_IF_MAIN;
