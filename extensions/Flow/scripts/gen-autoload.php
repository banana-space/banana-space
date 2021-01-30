<?php

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Generates Flow autoload info
 */

class GenerateFlowAutoload extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Generates Flow autoload data' );
	}

	public function execute() {
		$base = dirname( __DIR__ );
		$generator = new AutoloadGenerator( $base );
		foreach ( [ 'includes', 'tests/phpunit', 'vendor' ] as $dir ) {
			$generator->readDir( $base . '/' . $dir );
		}
		foreach ( glob( $base . '/*.php' ) as $file ) {
			$generator->readFile( $file );
		}
		// read entire maint dir, move helper to includes? to core?
		$generator->readFile( $base . '/maintenance/MaintenanceDebugLogger.php' );

		$target = $generator->getTargetFileInfo();

		file_put_contents(
			$target['filename'],
			$generator->getAutoload( basename( __DIR__ ) . '/' . basename( __FILE__ ) )
		);

		echo "Done.\n\n";
	}
}

$maintClass = GenerateFlowAutoload::class;
require_once RUN_MAINTENANCE_IF_MAIN;
