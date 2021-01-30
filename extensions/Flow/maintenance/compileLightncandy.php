<?php

use Flow\TemplateHelper;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Populate the *_user_ip fields within flow.  This only updates
 * the database and not the cache.  The model loading layer handles
 * cached old values.
 *
 * @ingroup Maintenance
 */
class CompileLightncandy extends Maintenance {
	/** @var TemplateHelper */
	protected $lightncandy;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Flow' );
	}

	public function execute() {
		$dir = __DIR__ . '/../handlebars';
		$this->lightncandy = new TemplateHelper( $dir, /* $forceRecompile = */ true );

		// looking for 664 permissions on the final files
		umask( 0002 );

		// clean out the compiled directory
		foreach ( glob( $dir . '/compiled/*' ) as $file ) {
			if ( !unlink( $file ) ) {
				$this->error( "Failed to unlink previously compiled code: $file" );
			}
		}

		// compile all non-partials
		$skipPrefix = '.partial.handlebars';
		$len = strlen( $skipPrefix );
		foreach ( glob( $dir . '/*.handlebars' ) as $file ) {
			if ( substr( $file, -$len ) !== $skipPrefix ) {
				$this->compile( basename( $file, '.handlebars' ) );
			}
		}
		$this->output( "\n" );
	}

	protected function compile( $templateName ) {
		$filenames = $this->lightncandy->getTemplateFilenames( $templateName );

		if ( !file_exists( $filenames['template'] ) ) {
			$this->error( "Could not find template at: {$filenames['template']}" );
		}

		$this->lightncandy->getTemplate( $templateName );
		if ( !file_exists( $filenames['compiled'] ) ) {
			$this->error( "Template compilation completed, but no compiled code found on disk" );
		} else {
			$this->output( "Successfully compiled $templateName to {$filenames['compiled']}\n" );
		}
	}
}

$maintClass = CompileLightncandy::class; // Tells it to run the class
require_once RUN_MAINTENANCE_IF_MAIN;
