<?php

use Flow\Container;
use Flow\Conversion\Utils;
use Flow\Data\ObjectManager;
use Flow\Model\AbstractRevision;
use Flow\Model\UUID;
use Flow\Parsoid\ContentFixer;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/**
 * @ingroup Maintenance
 */
class FlowReserializeRevisionContent extends Maintenance {
	/**
	 * @var ReflectionMethod
	 */
	private $setContentRawMethod;

	/**
	 * @var \Flow\DbFactory
	 */
	private $dbFactory;

	/**
	 * @var \Flow\Data\ManagerGroup
	 */
	private $storage;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Reserializes HTML revision contents to the latest Parsoid version." );
		$this->addOption( 'dry-run', 'Log hypothetical updates but don\'t write them to the database' );
		$this->addOption( 'raw-diff', 'In dry-run mode, show diffs of raw HTML rather than just the <body> (noisy)' );
		$this->setBatchSize( 50 );
		$this->requireExtension( 'Flow' );
	}

	protected function getBodyContent( $html ) {
		$dom = ContentFixer::createDOM( $html );
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		return Utils::getInnerHtml( $body );
	}

	protected function makeContentUpdatesAllowed( ObjectManager $om ) {
		// Do reflection hackery to unblock updates to rev_content
		$omClass = new ReflectionClass( get_class( $om ) );
		$storageProp = $omClass->getProperty( 'storage' );
		$storageProp->setAccessible( true );
		$storageObj = $storageProp->getValue( $om );

		$storageClass = new ReflectionClass( get_class( $storageObj ) );
		$allowedUpdateColumnsProp = $storageClass->getProperty( 'allowedUpdateColumns' );
		$allowedUpdateColumnsProp->setAccessible( true );
		$allowedUpdateColumnsValue = $allowedUpdateColumnsProp->getValue( $storageObj );

		$newAllowedUpdateColumnsValue = array_unique( array_merge( $allowedUpdateColumnsValue, [
			'rev_content',
			'rev_content_length',
			'rev_flags',
			'rev_previous_content_length',
		] ) );
		$allowedUpdateColumnsProp->setValue( $storageObj, $newAllowedUpdateColumnsValue );
	}

	public function execute() {
		// Reflection hackery: make setContentRaw() callable
		$this->setContentRawMethod = new ReflectionMethod( AbstractRevision::class, 'setContentRaw' );
		$this->setContentRawMethod->setAccessible( true );

		$this->dbFactory = Container::get( 'db.factory' );
		$this->storage = Container::get( 'storage' );

		$dbr = $this->dbFactory->getDb( DB_REPLICA );
		$dbw = $this->dbFactory->getDb( DB_MASTER );
		$newVersion = Utils::PARSOID_VERSION;

		$iterator = new BatchRowIterator( $dbw, 'flow_revision', 'rev_id', $this->mBatchSize );
		$iterator->addConditions( [
			'rev_user_wiki' => wfWikiID(),
			'rev_flags' . $dbr->buildLike( $dbr->anyString(), 'html', $dbr->anyString() ),
		] );
		$iterator->setFetchColumns( [ 'rev_id', 'rev_type', 'rev_content', 'rev_flags' ] );

		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				$revId = UUID::create( $row->rev_id );
				$om = $this->storage->getStorage( $row->rev_type );
				$rev = $om->get( $revId );
				$revIdAlpha = $revId->getAlphadecimal();
				if ( !$rev ) {
					$this->error( 'Could not load revision: ' . $revIdAlpha );
					continue;
				}
				if ( $rev->getContentFormat() !== 'html' ) {
					// Paranoia: we check rev_flags LIKE '%html%', protect against that picking up non-HTML
					// revisions that have a flag that contains the substring 'html'
					continue;
				}
				$storedHtml = $rev->getContent();
				$storedVersion = Utils::getParsoidVersion( $storedHtml );
				if ( $storedVersion === $newVersion ) {
					continue;
				}
				if ( $storedHtml === '' || $storedHtml === '<html><head></head><body></body></html>' ) {
					continue;
				}

				$title = $rev->getCollection()->getTitle();
				// Convert from HTML to wikitext then back to HTML
				$wikitext = Utils::convert( 'html', 'wikitext', $storedHtml, $title );
				$convertedHtml = Utils::convert( 'wikitext', 'html', $wikitext, $title );
				if ( $convertedHtml === $storedHtml ) {
					continue;
				}

				if ( $this->hasOption( 'dry-run' ) ) {
					if ( $this->hasOption( 'raw-diff' ) ) {
						$fromDiff = $storedHtml;
						$toDiff = $convertedHtml;
					} else {
						$fromDiff = $this->getBodyContent( $storedHtml );
						$toDiff = $this->getBodyContent( $convertedHtml );
					}
					if ( $fromDiff === $toDiff ) {
						$this->output( "Revision $revIdAlpha version $storedVersion -> $newVersion: no change to body\n" );
					} else {
						$diff = new Diff( explode( "\n", $fromDiff ), explode( "\n", $toDiff ) );
						$format = new UnifiedDiffFormatter();
						$output = $format->format( $diff );
						$this->output( "Revision $revIdAlpha version $storedVersion -> $newVersion: diff\n" );
						$this->output( $output . "\n" );
					}
				} else {
					$this->makeContentUpdatesAllowed( $om );
					$this->setContentRawMethod->invoke( $rev, [ 'html' => $convertedHtml, 'wikitext' => $wikitext ] );
					try {
						$om->put( $rev );
						$this->output( "Updated revision $revIdAlpha\n" );
					} catch ( \Exception $e ) {
						$this->error( "Failed to update revision $revIdAlpha: {$e->getMessage()}\n" );
					}
				}
			}
		}
	}
}

$maintClass = FlowReserializeRevisionContent::class;
require_once RUN_MAINTENANCE_IF_MAIN;
