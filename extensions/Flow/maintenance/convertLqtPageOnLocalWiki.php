<?php

use Flow\Container;
use Flow\Import\LiquidThreadsApi\ConversionStrategy;
use Flow\Import\LiquidThreadsApi\LocalApiBackend;
use Flow\Import\SourceStore\FileImportSourceStore;
use Psr\Log\LogLevel;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * This is intended for use both in testing and in production.  It converts a single LQT
 * page on the current wiki to a Flow page on the current wiki, handling archiving.
 */
class ConvertLqtPageOnLocalWiki extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Converts LiquidThreads data to Flow data on the current wiki, using a ConversionStrategy" );
		$this->addOption( 'srcpage', 'Page name of the source page to import from.', true, true );
		$this->addOption( 'logfile', 'File to read and store associations between imported items and their sources', true, true );
		$this->addOption( 'debug', 'Include debug information to progress report' );
		$this->requireExtension( 'Flow' );
	}

	public function execute() {
		$talkPageManagerUser = Flow\Hooks::getOccupationController()->getTalkpageManager();

		$api = new LocalApiBackend( $talkPageManagerUser );

		$importer = Container::get( 'importer' );

		$srcPageName = $this->getOption( 'srcpage' );

		$logFilename = $this->getOption( 'logfile' );
		$sourceStore = new FileImportSourceStore( $logFilename );

		$dbw = wfGetDB( DB_MASTER );

		$logger = new MaintenanceDebugLogger( $this );
		if ( $this->getOption( 'debug' ) ) {
			$logger->setMaximumLevel( LogLevel::DEBUG );
		} else {
			$logger->setMaximumLevel( LogLevel::INFO );
		}

		$strategy = new ConversionStrategy(
			$dbw,
			$sourceStore,
			$api,
			Container::get( 'url_generator' ),
			$talkPageManagerUser,
			Container::get( 'controller.notification' )
		);

		$importer->setLogger( $logger );
		$api->setLogger( $logger );

		$converter = new \Flow\Import\Converter(
			$dbw,
			$importer,
			$logger,
			$talkPageManagerUser,
			$strategy
		);

		$logger->info( "Starting LQT conversion of page $srcPageName" );

		$srcTitle = \Title::newFromText( $srcPageName );
		$converter->convertAll( [
			$srcTitle,
		] );

		$logger->info( "Finished LQT conversion of page $srcPageName" );
	}
}

$maintClass = ConvertLqtPageOnLocalWiki::class;
require_once RUN_MAINTENANCE_IF_MAIN;
