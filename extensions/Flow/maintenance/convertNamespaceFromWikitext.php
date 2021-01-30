<?php

use Flow\Utils\NamespaceIterator;
use MediaWiki\MediaWikiServices;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Converts a single namespace from wikitext talk pages to flow talk pages.  Does not
 * modify LiquidThreads pages it comes across; use convertLqtPagesWithProp.php for that.  Does not
 * modify sub-pages (except talk subpages with a corresponding subject page).
 */
class ConvertNamespaceFromWikitext extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Converts a single namespace of wikitext talk pages to Flow" );
		$this->addArg( 'namespaceName', 'Name of the namespace to convert' );
		$this->addOption(
			'no-convert-templates',
			'Comma-separated list of templates that indicate a page should not be converted',
			false, // not required
			true, // takes argument
			't'
		);
		$this->addOption(
			'header-suffix',
			'Wikitext to add to the end of the header',
			false, // not required
			true, // takes argument
			'a'
		);
		$this->requireExtension( 'Flow' );
	}

	public function execute() {
		global $wgLang;

		$provided = $this->getArg( 0 );
		$namespace = $wgLang->getNsIndex( $provided );
		if ( !$namespace ) {
			$this->error( "Invalid namespace provided: $provided" );
			return;
		}
		$namespaceName = $wgLang->getNsText( $namespace );
		if ( !MediaWikiServices::getInstance()->getNamespaceInfo()->hasSubpages( $namespace ) ) {
			$this->error( "Subpages are not enabled in the $namespaceName namespace." );
			$this->error( "In order to convert this namespace to Flow, you must enable subpages using:" );
			$this->error( "\$wgNamespacesWithSubpages[$namespace] = true;" );
			return;
		}

		$noConvertTemplates = explode( ',', $this->getOption( 'no-convert-templates', '' ) );
		if ( $noConvertTemplates === [ '' ] ) {
			// explode( ',', '' ) returns array( '' )
			$noConvertTemplates = [];
		}
		// Convert to Title objects
		foreach ( $noConvertTemplates as &$template ) {
			$title = Title::newFromText( $template, NS_TEMPLATE );
			if ( !$title ) {
				$this->error( "Invalid template name: $template" );
				return;
			}
			$template = $title;
		}

		// @todo send to prod logger?
		$logger = new MaintenanceDebugLogger( $this );

		$dbw = wfGetDB( DB_MASTER );
		$talkpageManager = Flow\Hooks::getOccupationController()->getTalkpageManager();
		$converter = new \Flow\Import\Converter(
			$dbw,
			Flow\Container::get( 'importer' ),
			$logger,
			$talkpageManager,

			new Flow\Import\Wikitext\ConversionStrategy(
				MediaWikiServices::getInstance()->getParser(),
				new Flow\Import\SourceStore\NullImportSourceStore(),
				$logger,
				$talkpageManager,
				$noConvertTemplates,
				$this->getOption( 'header-suffix', null )
			)
		);

		$logger->info( "Starting conversion of $namespaceName namespace" );

		// Iterate over all existing pages of the namespace.
		$it = new NamespaceIterator( $dbw, $namespace );
		// NamespaceIterator is an IteratorAggregate. Get an Iterator
		// so we can wrap that.
		$it = $it->getIterator();

		$converter->convertAll( $it );

		$logger->info( "Finished conversion of $namespaceName namespace" );
	}
}

$maintClass = ConvertNamespaceFromWikitext::class;
require_once RUN_MAINTENANCE_IF_MAIN;
