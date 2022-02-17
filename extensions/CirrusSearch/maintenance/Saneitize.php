<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\Sanity\Checker;
use CirrusSearch\Sanity\NoopRemediator;
use CirrusSearch\Sanity\PrintingRemediator;
use CirrusSearch\Sanity\QueueingRemediator;
use CirrusSearch\Searcher;

/**
 * Make sure the index for the wiki is sane.
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

class Saneitize extends Maintenance {
	/**
	 * @var int mediawiki page id
	 */
	private $fromPageId;

	/**
	 * @var int mediawiki page id
	 */
	private $toPageId;

	/**
	 * @var bool true to enable fast but inconsistent redirect checks
	 */
	private $fastCheck;

	/**
	 * @var Checker Checks is the index is insane, and calls on a Remediator
	 *  instance to do something about it. The remediator may fix the issue,
	 *  log about it, or do a combination.
	 */
	private $checker;

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 10 );
		$this->addDescription( 'Make the index sane. Always operates on a single cluster.' );
		$this->addOption( 'fromId', 'Start sanitizing at a specific page_id.  Default to 0.', false, true );
		$this->addOption( 'toId', 'Stop sanitizing at a specific page_id.  Default to the maximum id in the db + 100.', false, true );
		$this->addOption( 'noop', 'Rather then queue remediation actions do nothing.' );
		$this->addOption( 'logSane', 'Print all sane pages.' );
		$this->addOption( 'fastCheck', 'Do not load page content to check if a page is a redirect, faster but inconsistent.' );
		$this->addOption( 'buildChunks', 'Instead of running the script spit out commands that can be farmed out to ' .
			'different processes or machines to check the index.  If specified as a number then chunks no larger than ' .
			'that size are spat out.  If specified as a number followed by the word "total" without a space between them ' .
			'then that many chunks will be spat out sized to cover the entire wiki.', false, true );
	}

	public function execute() {
		$this->disablePoolCountersAndLogging();

		if ( $this->hasOption( 'batch-size' ) ) {
			$this->setBatchSize( $this->getOption( 'batch-size' ) );
			if ( $this->getBatchSize() > 5000 ) {
				$this->fatalError( "--batch-size too high!" );
			} elseif ( $this->getBatchSize() <= 0 ) {
				$this->fatalError( "--batch-size must be > 0!" );
			}
		}

		$this->fastCheck = $this->getOption( 'fastCheck', false );

		$this->setFromAndTo();
		$buildChunks = $this->getOption( 'buildChunks' );
		if ( $buildChunks ) {
			$builder = new \CirrusSearch\Maintenance\ChunkBuilder();
			$builder->build( $this->mSelf, $this->mOptions, $buildChunks, $this->fromPageId, $this->toPageId );
			return null;
		}
		$this->buildChecker();
		$updated = $this->check();
		$this->output( "Fixed $updated page(s) (" . ( $this->toPageId - $this->fromPageId ) . " checked)\n" );

		return true;
	}

	/**
	 * @return int the number of pages corrected
	 */
	private function check() {
		$updated = 0;
		for ( $pageId = $this->fromPageId;
			$pageId <= $this->toPageId;
			$pageId += $this->getBatchSize()
		) {
			$max = min( $this->toPageId, $pageId + $this->getBatchSize() - 1 );
			$updated += $this->checkChunk( range( $pageId, $max ) );
		}
		return $updated;
	}

	/**
	 * @param int[] $pageIds mediawiki page ids
	 * @return int number of pages corrected
	 */
	private function checkChunk( array $pageIds ) {
		$updated = $this->checker->check( $pageIds );
		$this->output( sprintf( "[%20s]%10d/%d\n", wfWikiID(), end( $pageIds ),
			$this->toPageId ) );
		return $updated;
	}

	private function setFromAndTo() {
		$dbr = $this->getDB( DB_REPLICA );
		$this->fromPageId = $this->getOption( 'fromId' );
		if ( $this->fromPageId === null ) {
			$this->fromPageId = 0;
		}
		$this->toPageId = $this->getOption( 'toId' );
		if ( $this->toPageId === null ) {
			$this->toPageId = $dbr->selectField( 'page', 'MAX(page_id)', [], __METHOD__ );
			if ( $this->toPageId === false ) {
				$this->toPageId = 0;
			} else {
				// Its technically possible for there to be pages in the index with ids greater
				// than the maximum id in the database.  That isn't super likely, but we'll
				// check a bit ahead just in case.  This isn't scientific or super accurate,
				// but its cheap.
				$this->toPageId += 100;
			}
		}
	}

	private function buildChecker() {
		if ( $this->getOption( 'noop' ) ) {
			$remediator = new NoopRemediator();
		} else {
			$remediator = new QueueingRemediator( $this->getConnection()->getClusterName() );
		}
		if ( !$this->isQuiet() ) {
			$remediator = new PrintingRemediator( $remediator );
		}
		// This searcher searches all indexes for the current wiki.
		$searcher = new Searcher( $this->getConnection(), 0, 0, $this->getSearchConfig(), [], null );
		$this->checker = new Checker(
			$this->getSearchConfig(),
			$this->getConnection(),
			$remediator,
			$searcher,
			$this->getOption( 'logSane' ),
			$this->fastCheck
		);
	}
}

$maintClass = Saneitize::class;
require_once RUN_MAINTENANCE_IF_MAIN;
