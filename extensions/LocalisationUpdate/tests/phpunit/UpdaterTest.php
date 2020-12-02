<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate;

/**
 * @covers \LocalisationUpdate\Updater
 */
class UpdaterTest extends \PHPUnit\Framework\TestCase {

	public function testIsDirectory() {
		$updater = new Updater();

		$this->assertTrue(
			$updater->isDirectory( '/IP/extensions/Translate/i18n/*.json' ),
			'Extension json files are a file pattern'
		);

		$this->assertFalse(
			$updater->isDirectory( '/IP/extensions/Translate/Translate.i18n.php' ),
			'Extension php file is not a pattern'
		);
	}

	public function testExpandRemotePath() {
		$updater = new Updater();
		$repos = [ 'main' => 'file:///repos/%NAME%/%SOME-VAR%' ];

		$info = [
			'repo' => 'main',
			'name' => 'product',
			'some-var' => 'file',
		];
		$this->assertEquals(
			'file:///repos/product/file',
			$updater->expandRemotePath( $info, $repos ),
			'Variables are expanded correctly'
		);
	}

	public function testReadMessages() {
		$updater = $updater = new Updater();

		$input = [ 'file' => 'Hello World!' ];
		$output = [ 'en' => [ 'key' => $input['file'] ] ];

		$reader = $this->createMock( 'LocalisationUpdate\Reader\Reader' );
		$reader
			->expects( $this->once() )
			->method( 'parse' )
			->will( $this->returnValue( $output ) );

		$factory = $this->createMock( 'LocalisationUpdate\Reader\ReaderFactory' );
		$factory
			->expects( $this->once() )
			->method( 'getReader' )
			->will( $this->returnValue( $reader ) );

		$observed = $updater->readMessages( $factory, $input );
		$this->assertEquals( $output, $observed, 'Tries to parse given file' );
	}

	public function testFindChangedTranslations() {
		$updater = $updater = new Updater();

		$origin = [
			'A' => '1',
			'C' => '3',
			'D' => '4',
		];
		$remote = [
			'A' => '1', // No change key
			'B' => '2', // New key
			'C' => '33', // Changed key
			'D' => '44', // Blacklisted key
		];
		$blacklist = [ 'D' => 0 ];
		$expected = [ 'B' => '2', 'C' => '33' ];
		$observed = $updater->findChangedTranslations( $origin, $remote, $blacklist );
		$this->assertEquals( $expected, $observed, 'Changed and new keys returned' );
	}
}
