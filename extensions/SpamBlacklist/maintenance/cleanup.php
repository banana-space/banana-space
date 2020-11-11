<?php
/**
 * An aggressive spam cleanup script.
 * Searches the database for matching pages, and reverts them to
 * the last non-spammed revision.
 * If all revisions contain spam, blanks the page
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class Cleanup extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'SpamBlacklist' );
		$this->addOption( 'dry-run', 'Only do a dry run' );
	}

	public function execute() {
		$user = User::newSystemUser( 'Spam cleanup script', [ 'steal' => true ] );

		$sb = BaseBlacklist::getSpamBlacklist();
		$regexes = $sb->getBlacklists();
		if ( !$regexes ) {
			$this->fatalError( "Invalid regex, can't clean up spam" );
		}
		$dryRun = $this->hasOption( 'dry-run' );

		$dbr = wfGetDB( DB_REPLICA );
		$maxID = (int)$dbr->selectField( 'page', 'MAX(page_id)' );
		$reportingInterval = 100;

		$this->output( "Regexes are " . implode( ', ', array_map( 'count', $regexes ) ) . " bytes\n" );
		$this->output( "Searching for spam in $maxID pages...\n" );
		if ( $dryRun ) {
			$this->output( "Dry run only\n" );
		}

		for ( $id = 1; $id <= $maxID; $id++ ) {
			if ( $id % $reportingInterval == 0 ) {
				printf( "%-8d  %-5.2f%%\r", $id, $id / $maxID * 100 );
			}
			$revision = Revision::loadFromPageId( $dbr, $id );
			if ( $revision ) {
				$text = ContentHandler::getContentText( $revision->getContent() );
				if ( $text ) {
					foreach ( $regexes as $regex ) {
						if ( preg_match( $regex, $text, $matches ) ) {
							$title = $revision->getTitle();
							$titleText = $title->getPrefixedText();
							if ( $dryRun ) {
								$this->output( "Found spam in [[$titleText]]\n" );
							} else {
								$this->output( "Cleaning up links to {$matches[0]} in [[$titleText]]\n" );
								$match = str_replace( 'http://', '', $matches[0] );
								$this->cleanupArticle( $revision, $regexes, $match, $user );
							}
						}
					}
				}
			}
		}
		// Just for satisfaction
		printf( "%-8d  %-5.2f%%\n", $id - 1, ( $id - 1 ) / $maxID * 100 );
	}

	/**
	 * Find the latest revision of the article that does not contain spam and revert to it
	 * @param Revision $rev
	 * @param array $regexes
	 * @param array $match
	 * @param User $user
	 */
	private function cleanupArticle( Revision $rev, $regexes, $match, User $user ) {
		$title = $rev->getTitle();
		while ( $rev ) {
			$matches = false;
			foreach ( $regexes as $regex ) {
				$matches = $matches
					|| preg_match(
						$regex,
						ContentHandler::getContentText( $rev->getContent() )
					);
			}
			if ( !$matches ) {
				// Didn't find any spam
				break;
			}

			$rev = $rev->getPrevious();
		}
		if ( !$rev ) {
			// Didn't find a non-spammy revision, blank the page
			$this->output( "All revisions are spam, blanking...\n" );
			$text = '';
			$comment = "All revisions matched the spam blacklist ($match), blanking";
		} else {
			// Revert to this revision
			$text = ContentHandler::getContentText( $rev->getContent() );
			$comment = "Cleaning up links to $match";
		}
		$wikiPage = new WikiPage( $title );
		$wikiPage->doEditContent(
			ContentHandler::makeContent( $text, $title ), $comment,
			0, false, $user
		);
	}
}

$maintClass = Cleanup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
