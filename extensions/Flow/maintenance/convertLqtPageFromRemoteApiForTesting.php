<?php

use Flow\Import\LiquidThreadsApi\ImportSource;
use Flow\Import\LiquidThreadsApi\RemoteApiBackend;
use Flow\Import\SourceStore\FileImportSourceStore;
use Psr\Log\LogLevel;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * This is *only* for use in testing, not production.  The primary purpose is to exercise
 * the API (production also uses the API, but with FauxRequest) and Parsoid.
 *
 * This also does not test redirects or notification conversion.
 */
class ConvertLqtPageFromRemoteApiForTesting extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Converts LiquidThreads data to Flow data.  Destination page is determined by ConversionStrategy" );
		$this->addOption( 'dstpage', 'Page name of the destination page on the current wiki.  Defaults to same as source', false, true );
		$this->addOption( 'srcpage', 'Page name of the source page to import from.', true, true );
		$this->addOption( 'remoteapi', 'Remote API URL to read from', true, true );
		$this->addOption( 'cacheremoteapidir', 'Cache remote api calls to the specified directory', true, true );
		$this->addOption( 'logfile', 'File to read and store associations between imported items and their sources', true, true );
		$this->addOption( 'debug', 'Include debug information to progress report' );
		$this->requireExtension( 'Flow' );
	}

	public function execute() {
		$cacheDir = $this->getOption( 'cacheremoteapidir' );
		if ( !is_dir( $cacheDir ) ) {
			if ( !mkdir( $cacheDir ) ) {
				throw new Flow\Exception\FlowException( 'Provided dir for caching remote api calls is not creatable.' );
			}
		}
		if ( !is_writable( $cacheDir ) ) {
			throw new Flow\Exception\FlowException( 'Provided dir for caching remote api calls is not writable.' );
		}

		$api = new RemoteApiBackend( $this->getOption( 'remoteapi' ), $cacheDir );

		$importer = Flow\Container::get( 'importer' );
		$importer->setAllowUnknownUsernames( true );

		$talkPageManagerUser = Flow\Hooks::getOccupationController()->getTalkpageManager();

		$srcPageName = $this->getOption( 'srcpage' );
		if ( $this->hasOption( 'dstpage' ) ) {
			$dstPageName = $this->getOption( 'dstpage' );
		} else {
			$dstPageName = $srcPageName;
		}

		$dstTitle = Title::newFromText( $dstPageName );
		$source = new ImportSource(
			$api,
			$srcPageName,
			$talkPageManagerUser
		);

		$logFilename = $this->getOption( 'logfile' );
		$sourceStore = new FileImportSourceStore( $logFilename );

		$logger = new MaintenanceDebugLogger( $this );
		if ( $this->getOption( 'debug' ) ) {
			$logger->setMaximumLevel( LogLevel::DEBUG );
		} else {
			$logger->setMaximumLevel( LogLevel::INFO );
		}

		$importer->setLogger( $logger );
		$api->setLogger( $logger );

		$logger->info( "Starting LQT conversion of page $srcPageName" );

		$importer->import( $source, $dstTitle, $talkPageManagerUser, $sourceStore );

		$logger->info( "Finished LQT conversion of page $srcPageName" );
	}
}

$maintClass = ConvertLqtPageFromRemoteApiForTesting::class;
require_once RUN_MAINTENANCE_IF_MAIN;
