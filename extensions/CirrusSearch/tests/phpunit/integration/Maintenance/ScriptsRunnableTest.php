<?php

namespace CirrusSearch\Maintenance;

// phpcs:disable MediaWiki.Usage.ForbiddenFunctions.escapeshellarg
// phpcs:disable MediaWiki.Usage.ForbiddenFunctions.exec
use CirrusSearch\CirrusIntegrationTestCase;

/**
 * Asserts that maintenance scripts are loadable independently. These
 * classes are loaded prior to the autoloader and we need an assurance
 * they dont extend/implement something not available.
 * @coversNothing
 */
class ScriptsRunnableTest extends CirrusIntegrationTestCase {
	public function scriptPathProvider() {
		$it = new \DirectoryIterator( __DIR__ . '/../../../../maintenance/' );
		$tests = [];
		/** @var \SplFileInfo $fileInfo */
		foreach ( $it as $fileInfo ) {
			if ( $fileInfo->getExtension() === 'php' ) {
				$tests[] = [ $fileInfo->getPathname() ];
			}
		}
		return $tests;
	}

	/**
	 * @dataProvider scriptPathProvider
	 */
	public function  testScriptCanBeLoaded( $path ) {
		$preload = escapeshellarg( __DIR__ . '/ScriptsRunnablePreload.php' );
		$cmd = implode( ' ', [ PHP_BINARY, $preload, escapeshellarg( $path ) ] );
		exec( $cmd, $output, $retCode );
		// return code isn't useful, getting the help message returns 1
		// just like an error. Instead look for a message we know should
		// be in the help text.
		$this->assertSame( 0, $retCode );
	}
}
