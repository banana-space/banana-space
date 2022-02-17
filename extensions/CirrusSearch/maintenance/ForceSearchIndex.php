<?php

namespace CirrusSearch\Maintenance;

use BatchRowIterator;
use CirrusSearch\BuildDocument\BuildDocument;
use CirrusSearch\Iterator\CallbackIterator;
use CirrusSearch\Job;
use CirrusSearch\SearchConfig;
use CirrusSearch\Updater;
use JobQueueGroup;
use MediaWiki\Logger\LoggerFactory;
use MWException;
use MWTimestamp;
use Title;
use Wikimedia\Rdbms\IDatabase;
use WikiPage;

/**
 * Force reindexing change to the wiki.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . '/../includes/Maintenance/Maintenance.php';

class ForceSearchIndex extends Maintenance {
	const SECONDS_BETWEEN_JOB_QUEUE_LENGTH_CHECKS = 3;
	/** @var MWTimestamp|null */
	public $fromDate = null;
	/** @var MWTimestamp|null */
	public $toDate = null;
	public $toId = null;
	public $indexUpdates;
	public $archive;
	public $limit;
	public $queue;
	public $maxJobs;
	public $pauseForJobs;
	public $namespace;
	public $excludeContentTypes;
	public $lastJobQueueCheckTime = 0;

	/**
	 * @var boolean true if the script is run with --ids
	 */
	private $runWithIds;

	/**
	 * @var int[] list of page ids to reindex when --ids is used
	 */
	private $pageIds;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Force indexing some pages.  Setting --from or --to will switch "
			. "from page id based indexing to "
			. "date based indexing which uses less efficient queries and follows redirects.\n\n"
			. "Note: All froms are _exclusive_ and all tos are _inclusive_.\n"
			. "Note 2: Setting fromId and toId use the efficient query so those are ok.\n"
			. "Note 3: Operates on all clusters unless --cluster is provided.\n"
		);
		$this->setBatchSize( 10 );
		$this->addOption( 'from', 'Start date of reindex in YYYY-mm-ddTHH:mm:ssZ (exc.  Defaults ' .
			'to 0 epoch.', false, true );
		$this->addOption( 'to', 'Stop date of reindex in YYYY-mm-ddTHH:mm:ssZ.  Defaults to now.',
			false, true );
		$this->addOption( 'fromId', 'Start indexing at a specific page_id.  ' .
			'Not useful with --deletes.', false, true );
		$this->addOption( 'toId', 'Stop indexing at a specific page_id.  ' .
			'Not useful with --deletes or --from or --to.', false, true );
		$this->addOption( 'ids', 'List of page ids (comma separated) to reindex. ' .
			'Not allowed with deletes/from/to/fromId/toId/limit.', false, true );
		$this->addOption( 'deletes',
			'If this is set then just index deletes, not updates or creates.', false );
		$this->addOption( 'archive',
			'Don\'t delete pages, only index them into the archive.', false, false );
		$this->addOption( 'limit',
			'Maximum number of pages to process before exiting the script. Default to unlimited.',
			false, true );
		$this->addOption( 'buildChunks', 'Instead of running the script spit out commands that ' .
			'can be farmed out to different processes or machines to rebuild the index.  Works ' .
			'with fromId and toId, not from and to.  If specified as a number then chunks no ' .
			'larger than that size are spat out.  If specified as a number followed by the word ' .
			'"total" without a space between them then that many chunks will be spat out sized ' .
			'to cover the entire wiki.', false, true );
		$this->addOption( 'queue', 'Rather than perform the indexes in process add them to the ' .
			'job queue.  Ignored for delete.' );
		$this->addOption( 'maxJobs', 'If there are more than this many index jobs in the queue ' .
			'then pause before adding more.  This is only checked every ' .
			self::SECONDS_BETWEEN_JOB_QUEUE_LENGTH_CHECKS .
			' seconds.  Not meaningful without --queue.', false, true );
		$this->addOption( 'pauseForJobs', 'If paused adding jobs then wait for there to be less ' .
			'than this many before starting again.  Defaults to the value specified for ' .
			'--maxJobs.  Not meaningful without --queue.', false, true );
		$this->addOption( 'indexOnSkip', 'When skipping either parsing or links send the document' .
			' as an index.  This replaces the contents of the index for that entry with the entry' .
			' built from a skipped process. Without this if the entry does not exist then it will' .
			' be skipped entirely.  Only set this when running the first pass of building the' .
			' index.  Otherwise, don\'t tempt fate by indexing half complete documents.' );
		$this->addOption( 'forceParse',
			'Bypass ParserCache and do a fresh parse of pages from the Content.' );
		$this->addOption( 'skipParse',
			'Skip parsing the page.  This is really only good for running the second half ' .
			'of the two phase index build.  If this is specified then the default batch size ' .
			'is actually 50.' );
		$this->addOption( 'skipLinks',
			'Skip looking for links to the page (counting and finding redirects).  Use ' .
			'this with --indexOnSkip for the first half of the two phase index build.' );
		$this->addOption( 'namespace', 'Only index pages in this given namespace', false, true );
		$this->addOption( 'excludeContentTypes', 'Exclude pages of the specified content types. ' .
			'These must be a comma separated list of strings such as "wikitext" or "json" ' .
			'matching the CONTENT_MODEL_* constants.', false, true, false );
		$this->addOption( 'useDbIndex',
			'Use specific index when fetching IDs from the database.', false, true, false );
	}

	public function execute() {
		$this->disablePoolCountersAndLogging();
		$wiki = sprintf( "[%20s]", wfWikiID() );

		// Make sure we've actually got indices to populate
		if ( !$this->simpleCheckIndexes() ) {
			$this->fatalError(
				"$wiki index(es) do not exist. Did you forget to run updateSearchIndexConfig?"
			);
		}

		$this->indexUpdates = !$this->getOption( 'deletes', false );
		// We need to check ids options early otherwise hasOption may return
		// true even if the user did not set the option on the commandline
		if ( $this->hasOption( 'ids' ) ) {
			$this->runWithIds = true;
			$this->pageIds = $this->buildPageIdBatches();
		}

		if ( $this->getOption( 'from' ) !== null || $this->getOption( 'to' ) !== null ) {
			// 0 is falsy so MWTimestamp makes that `now`.  '00' is epoch 0.
			$this->fromDate = new MWTimestamp( $this->getOption( 'from', '00' ) );
			$this->toDate = new MWTimestamp( $this->getOption( 'to', false ) );
		}
		$this->toId = $this->getOption( 'toId' );
		$this->archive = (bool)$this->getOption( 'archive', false );
		if ( $this->archive ) {
			// If we're indexing only for archive, this implies deletes
			$this->indexUpdates = false;
		}
		$this->limit = $this->getOption( 'limit' );
		$buildChunks = $this->getOption( 'buildChunks' );
		if ( $buildChunks !== null ) {
			$this->buildChunks( $buildChunks );
			return null;
		}
		$this->queue = $this->getOption( 'queue' );
		$this->maxJobs = $this->getOption( 'maxJobs' )
			? intval( $this->getOption( 'maxJobs' ) )
			: null;
		$this->pauseForJobs = $this->getOption( 'pauseForJobs' ) ?
			intval( $this->getOption( 'pauseForJobs' ) ) : $this->maxJobs;
		$updateFlags = $this->buildUpdateFlags();

		if ( !$this->getOption( 'batch-size' ) &&
			( $this->getOption( 'queue' ) || !$this->indexUpdates )
		) {
			$this->setBatchSize( 100 );
		}

		$this->namespace = $this->hasOption( 'namespace' ) ?
			intval( $this->getOption( 'namespace' ) ) : null;

		$this->excludeContentTypes = array_filter( array_map(
			'trim',
			explode( ',', $this->getOption( 'excludeContentTypes', '' ) )
		) );

		$operationName = $this->indexUpdates
			? ( $this->queue ? 'Queued' : 'Indexed' )
			: ( $this->archive ? 'Archived' : 'Deleted' );

		$operationStartTime = microtime( true );
		$completed = 0;
		$rate = 0;

		if ( $this->runWithIds ) {
			$it = $this->getIdsIterator();
			// @phan-suppress-next-line PhanImpossibleTypeComparison
		} elseif ( $this->indexUpdates && $this->fromDate === null ) {
			$it = $this->getUpdatesByIdIterator();
		} elseif ( $this->indexUpdates ) {
			$it = $this->getUpdatesByDateIterator();
		} else {
			$it = $this->getDeletesIterator();
		}

		foreach ( $it as $batch ) {
			if ( $this->indexUpdates ) {
				$size = count( $batch['updates'] );
				$updates = array_filter( $batch['updates'] );
				if ( $this->queue ) {
					$this->waitForQueueToShrink( $wiki );
					JobQueueGroup::singleton()->push( Job\MassIndex::build(
						$updates, $updateFlags, $this->getOption( 'cluster' )
					) );
				} else {
					// Update size with the actual number of updated documents.
					$updater = $this->createUpdater();
					$size = $updater->updatePages( $updates, $updateFlags );
				}
			} else {
				$size = count( $batch['titlesToDelete'] );
				$updater = $this->createUpdater();
				$updater->archivePages( $batch['archive'] );
				if ( !$this->archive ) {
					$updater->deletePages( $batch['titlesToDelete'], $batch['docIdsToDelete'] );
				}
			}

			$completed += $size;
			$rate = $this->calculateIndexingRate( $completed, $operationStartTime );

			$this->output(
				"$wiki $operationName $size pages ending at {$batch['endingAt']} at $rate/second\n"
			);
			if ( $this->limit !== null && $completed > $this->limit ) {
				break;
			}
		}
		$this->output( "$operationName a total of {$completed} pages at $rate/second\n" );
		$this->waitForQueueToDrain( $wiki );

		return true;
	}

	private function buildPageIdBatches() {
		if ( !$this->indexUpdates || $this->hasOption( 'limit' )
			|| $this->hasOption( 'from' ) || $this->hasOption( 'to' )
			|| $this->hasOption( 'fromId' ) || $this->hasOption( 'toId' )
		) {
			$this->fatalError(
				'--ids cannot be used with deletes/archive/from/to/fromId/toId/limit'
			);
		}

		$pageIds = array_map(
			function ( $pageId ) {
				$pageId = trim( $pageId );
				if ( !ctype_digit( $pageId ) ) {
					$this->fatalError( "Invalid page id provided in --ids, got '$pageId', " .
						"expected a positive integer" );
				}
				return intval( $pageId );
			},
			explode( ',', $this->getOption( 'ids' ) )
		);
		return array_unique( $pageIds, SORT_REGULAR );
	}

	private function buildUpdateFlags() {
		$updateFlags = 0;
		if ( $this->getOption( 'indexOnSkip' ) ) {
			$updateFlags |= BuildDocument::INDEX_ON_SKIP;
		}
		if ( $this->getOption( 'skipParse' ) ) {
			$updateFlags |= BuildDocument::SKIP_PARSE;
			if ( !$this->getOption( 'batch-size' ) ) {
				$this->setBatchSize( 50 );
			}
		}
		if ( $this->getOption( 'skipLinks' ) ) {
			$updateFlags |= BuildDocument::SKIP_LINKS;
		}

		if ( $this->getOption( 'forceParse' ) ) {
			$updateFlags |= BuildDocument::FORCE_PARSE;
		}

		return $updateFlags;
	}

	private function waitForQueueToShrink( $wiki ) {
		$now = microtime( true );
		if ( $now - $this->lastJobQueueCheckTime <=
			self::SECONDS_BETWEEN_JOB_QUEUE_LENGTH_CHECKS
		) {
			return;
		}

		$this->lastJobQueueCheckTime = $now;
		$queueSize = $this->getUpdatesInQueue();
		if ( $this->maxJobs === null || $this->maxJobs >= $queueSize ) {
			return;
		}

		do {
			$this->output(
				"$wiki Waiting while job queue shrinks: $this->pauseForJobs > $queueSize\n"
			);
			usleep( self::SECONDS_BETWEEN_JOB_QUEUE_LENGTH_CHECKS * 1000000 );
			$queueSize = $this->getUpdatesInQueue();
		} while ( $this->pauseForJobs < $queueSize );
	}

	private function waitForQueueToDrain( $wiki ) {
		if ( !$this->queue ) {
			return;
		}

		$lastQueueSizeForOurJob = PHP_INT_MAX;
		$waitStartTime = microtime( true );
		$this->output( "Waiting for jobs to drain from the queue\n" );
		while ( true ) {
			$queueSizeForOurJob = $this->getUpdatesInQueue();
			if ( $queueSizeForOurJob === 0 ) {
				return;
			}
			// We subtract 5 because we some jobs may be added by deletes
			if ( $queueSizeForOurJob > $lastQueueSizeForOurJob ) {
				$this->output( "Queue size went up.  Another script is likely adding jobs " .
					"and it'll wait for them to empty.\n" );
				return;
			}
			if ( microtime( true ) - $waitStartTime > 120 ) {
				// Wait at least two full minutes before we check if the job count went down.
				// Less then that and we might be seeing lag from redis's counts.
				$lastQueueSizeForOurJob = $queueSizeForOurJob;
			}
			$this->output( "$wiki $queueSizeForOurJob jobs left on the queue.\n" );
			usleep( self::SECONDS_BETWEEN_JOB_QUEUE_LENGTH_CHECKS * 1000000 );
		}
	}

	/**
	 * @param int $completed
	 * @param double $operationStartTime
	 *
	 * @return double
	 */
	private function calculateIndexingRate( $completed, $operationStartTime ) {
		$rate = $completed / ( microtime( true ) - $operationStartTime );

		if ( $rate < 1 ) {
			return round( $rate, 1 );
		}

		return round( $rate );
	}

	/**
	 * Do some simple sanity checking to make sure we've got indexes to populate.
	 * Note this isn't nearly as robust as updateSearchIndexConfig is, but it's
	 * not designed to be.
	 *
	 * @return bool
	 */
	private function simpleCheckIndexes() {
		$indexBaseName = $this->getSearchConfig()->get( SearchConfig::INDEX_BASE_NAME );

		// Top-level alias needs to exist
		if ( !$this->getConnection()->getIndex( $indexBaseName )->exists() ) {
			return false;
		}

		// Now check all index types to see if they exist
		foreach ( $this->getConnection()->getAllIndexTypes() as $indexType ) {
			// If the alias for this type doesn't exist, fail
			if ( !$this->getConnection()->getIndex( $indexBaseName, $indexType )->exists() ) {
				return false;
			}
		}

		return true;
	}

	protected function getDeletesIterator() {
		$dbr = $this->getDB( DB_REPLICA, [ 'vslow' ] );
		$it = new BatchRowIterator(
			$dbr,
			'logging',
			[ 'log_timestamp' ],
			$this->getBatchSize()
		);

		$this->attachPageConditions( $dbr, $it, 'log' );
		$this->attachTimestampConditions( $dbr, $it, 'log' );
		$it->addConditions( [
			'log_type' => 'delete',
			'log_action' => 'delete',
			'EXISTS(select * from archive where ar_title = log_title and ar_namespace = log_namespace)',
			// Prior to 2010 the logging table contains nulls. As the docs in elasticsearch use the page id
			// as the document id we cannot handle these old rows.
			'log_page IS NOT NULL',
		] );

		$it->setFetchColumns( [ 'log_timestamp', 'log_namespace', 'log_title', 'log_page' ] );

		return new CallbackIterator( $it, function ( $batch ) {
			$titlesToDelete = [];
			$docIdsToDelete = [];
			$archive = [];
			foreach ( $batch as $row ) {
				$title = Title::makeTitle( $row->log_namespace, $row->log_title );
				$id = $this->getSearchConfig()->makeId( $row->log_page );
				$titlesToDelete[] = $title;
				$docIdsToDelete[] = $id;
				$archive[] = [
					'title' => $title,
					'page' => $id,
				];
			}

			return [
				'titlesToDelete' => $titlesToDelete,
				'docIdsToDelete' => $docIdsToDelete,
				'archive' => $archive,
				'endingAt' => isset( $row )
					? ( new MWTimestamp( $row->log_timestamp ) )->getTimestamp( TS_ISO_8601 )
					: 'unknown',
			];
		} );
	}

	protected function getIdsIterator() {
		$dbr = $this->getDB( DB_REPLICA, [ 'vslow' ] );
		$pageQuery = WikiPage::getQueryInfo();
		$it = new BatchRowIterator( $dbr, $pageQuery['tables'], 'page_id', $this->getBatchSize() );
		$it->setFetchColumns( $pageQuery['fields'] );
		$it->addJoinConditions( $pageQuery['joins'] );
		$it->addConditions( [
			'page_id in (' . $dbr->makeList( $this->pageIds, LIST_COMMA ) . ')',
		] );
		$this->attachPageConditions( $dbr, $it, 'page' );

		return $this->wrapDecodeResults( $it, 'page_id' );
	}

	protected function getUpdatesByDateIterator() {
		$dbr = $this->getDB( DB_REPLICA, [ 'vslow' ] );
		$pageQuery = WikiPage::getQueryInfo();
		$it = new BatchRowIterator(
			$dbr,
			array_merge( $pageQuery['tables'], [ 'revision' ] ),
			[ 'rev_timestamp', 'page_id' ],
			$this->getBatchSize()
		);
		$it->setFetchColumns( $pageQuery['fields'] );
		$it->addJoinConditions( $pageQuery['joins'] );
		$it->addJoinConditions( [
			'revision' => [ 'JOIN', [ 'rev_page = page_id', 'rev_id = page_latest' ] ]
		] );

		$this->attachTimestampConditions( $dbr, $it, 'rev' );
		$this->attachPageConditions( $dbr, $it, 'page' );

		return $this->wrapDecodeResults( $it, 'rev_timestamp' );
	}

	protected function getUpdatesByIdIterator() {
		$dbr = $this->getDB( DB_REPLICA, [ 'vslow' ] );
		$pageQuery = WikiPage::getQueryInfo();
		$it = new BatchRowIterator( $dbr,  $pageQuery['tables'], 'page_id', $this->getBatchSize() );
		$it->setFetchColumns( $pageQuery['fields'] );
		$it->addJoinConditions( $pageQuery['joins'] );
		$fromId = $this->getOption( 'fromId', 0 );
		if ( $fromId > 0 ) {
			$it->addConditions( [
				'page_id >= ' . $dbr->addQuotes( $fromId ),
			] );
		}
		if ( $this->toId ) {
			$it->addConditions( [
				'page_id <= ' . $dbr->addQuotes( $this->toId ),
			] );
		}

		$this->attachPageConditions( $dbr, $it, 'page' );

		return $this->wrapDecodeResults( $it, 'page_id' );
	}

	private function attachTimestampConditions(
		IDatabase $dbr, BatchRowIterator $it, $columnPrefix
	) {
		// When initializing we guarantee that if either fromDate or toDate are provided
		// the other has a sane default value.
		if ( $this->fromDate !== null ) {
			$it->addConditions( [
				"{$columnPrefix}_timestamp >= " .
					$dbr->addQuotes( $dbr->timestamp( $this->fromDate ) ),
				"{$columnPrefix}_timestamp <= " .
					$dbr->addQuotes( $dbr->timestamp( $this->toDate ) ),
			] );
		}
	}

	private function attachPageConditions( IDatabase $dbr, BatchRowIterator $it, $columnPrefix ) {
		if ( $this->namespace ) {
			$it->addConditions( [
				"{$columnPrefix}_namespace" => $this->namespace,
			] );
		}
		if ( $this->excludeContentTypes ) {
			$list = $dbr->makeList( $this->excludeContentTypes, LIST_COMMA );
			$it->addConditions( [
				"{$columnPrefix}_content_model NOT IN ($list)",
			] );
		}
		if ( $this->hasOption( 'useDbIndex' ) ) {
			$index = $this->getOption( 'useDbIndex' );
			$it->addOptions( [ 'USE INDEX' => $index ] );
		}
	}

	/**
	 * @param BatchRowIterator $it
	 * @param string $endingAtColumn
	 * @return CallbackIterator
	 */
	private function wrapDecodeResults( BatchRowIterator $it, $endingAtColumn ) {
		return new CallbackIterator( $it, function ( $batch ) use ( $endingAtColumn ) {
			// Build the updater outside the loop because it stores the redirects it hits.
			// Don't build it at the top level so those are stored when it is freed.
			$updater = $this->createUpdater();

			$pages = [];
			foreach ( $batch as $row ) {
				// No need to call Updater::traceRedirects here because we know this is a valid page
				// because it is in the database.
				$page = WikiPage::newFromRow( $row, WikiPage::READ_LATEST );

				// null pages still get attached to keep the counts the same. They will be filtered
				// later on.
				$pages[] = $this->decidePage( $updater, $page );
			}

			if ( isset( $row ) ) {
				if ( $endingAtColumn === 'rev_timestamp' ) {
					$ts = new MWTimestamp( $row->rev_timestamp );
					$endingAt = $ts->getTimestamp( TS_ISO_8601 );
				} elseif ( $endingAtColumn === 'page_id' ) {
					$endingAt = $row->page_id;
				} else {
					throw new \MWException( 'Unknown $endingAtColumn: ' . $endingAtColumn );
				}
			} else {
				$endingAt = 'unknown';
			}

			return [
				'updates' => $pages,
				'endingAt' => $endingAt,
			];
		} );
	}

	/**
	 * Determine the actual page in the index that needs to be updated, based on a
	 * source page.
	 *
	 * @param Updater $updater
	 * @param WikiPage $page
	 * @return WikiPage|null WikiPage to be updated, or null if none.
	 */
	private function decidePage( Updater $updater, WikiPage $page ) {
		try {
			$content = $page->getContent();
		} catch ( MWException $ex ) {
			LoggerFactory::getInstance( 'CirrusSearch' )->warning(
				"Error deserializing content, skipping page: {pageId}",
				[ 'pageId' => $page->getTitle()->getArticleID() ]
			);
			return null;
		}

		if ( $content === null ) {
			// Skip pages without content.  Pages have no content because their latest revision
			// as loaded by the query above doesn't exist.
			$this->output(
				'Skipping page with no content: ' . $page->getTitle()->getArticleID() . "\n"
			);
			return null;
		}

		if ( !$content->isRedirect() ) {
			return $page;
		}

		if ( $this->toDate === null ) {
			// Looks like we accidentally picked up a redirect when we were indexing by id and thus
			// trying to ignore redirects!  Just ignore it!  We would filter them out at the db
			// level but that is slow for large wikis.
			return null;
		}

		// We found a redirect.  Great.  Since we can't index special pages and redirects to special
		// pages are totally possible, as well as fun stuff like redirect loops, we need to use
		// Updater's redirect tracing logic which is very complete.  Also, it returns null on
		// self redirects.  Great!
		list( $page, ) = $updater->traceRedirects( $page->getTitle() );

		if ( $page != null &&
			Title::makeTitleSafe( $page->getTitle()->getNamespace(), $page->getTitle()->getText() ) === null
		) {
			// The title cannot be rebuilt from its ns_prefix + text.
			// It happens if an invalid title is present in the DB
			// We may prefer to not index them as they are hardly viewable
			$this->output( 'Skipping page with invalid title: ' . $page->getTitle()->getPrefixedText() );
			return null;
		}

		return $page;
	}

	/**
	 * @param string|int $buildChunks If specified as a number then chunks no
	 *  larger than that size are spat out.  If specified as a number followed
	 *  by the word "total" without a space between them then that many chunks
	 *  will be spat out sized to cover the entire wiki.
	 */
	private function buildChunks( $buildChunks ) {
		$dbr = $this->getDB( DB_REPLICA, [ 'vslow' ] );
		if ( $this->toId === null ) {
			$this->toId = $dbr->selectField( 'page', 'MAX(page_id)', [], __METHOD__ );
			if ( $this->toId === false ) {
				$this->fatalError( "Couldn't find any pages to index." );
			}
		}
		$fromId = $this->getOption( 'fromId' );
		if ( $fromId === null ) {
			$fromId = $dbr->selectField( 'page', 'MIN(page_id) - 1', [], __METHOD__ );
			if ( $fromId === false ) {
				$this->fatalError( "Couldn't find any pages to index." );
			}
		}
		if ( $fromId === $this->toId ) {
			$this->fatalError(
				"Couldn't find any pages to index.  fromId = $fromId = $this->toId = toId."
			);
		}
		$builder = new \CirrusSearch\Maintenance\ChunkBuilder();
		$builder->build( $this->mSelf, $this->mOptions, $buildChunks, $fromId, $this->toId );
	}

	/**
	 * Get the number of cirrusSearchMassIndex jobs in the queue.
	 * @return int length
	 */
	private function getUpdatesInQueue() {
		return JobQueueGroup::singleton()->get( 'cirrusSearchMassIndex' )->getSize();
	}

	/**
	 * @return Updater
	 */
	private function createUpdater() {
		return Updater::build( $this->getSearchConfig(), $this->getOption( 'cluster', null ) );
	}
}

$maintClass = ForceSearchIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
