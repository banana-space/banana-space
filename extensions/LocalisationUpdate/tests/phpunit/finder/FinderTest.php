<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate;

/**
 * @covers \LocalisationUpdate\Finder
 */
class FinderTest extends \PHPUnit\Framework\TestCase {
	public function testGetComponents() {
		$finder = new Finder(
			[
				'TranslateSearch' => '/IP/extensions/Translate/TranslateSearch.i18n.php',
				'Babel' => '/IP/extensions/Babel/Babel.i18n.php',
			],
			[
				'Babel' => '/IP/extensions/Babel/i18n',
				'Door' => [
					'core' => '/IP/extensions/Door/i18n/core',
					'extra' => '/IP/extensions/Door/i18n/extra',
				],
				'Vector' => '/IP/skins/Vector/i18n',
			],
			'/IP'
		);
		$observed = $finder->getComponents();

		$expected = [
			'repo' => 'mediawiki',
			'orig' => "file:///IP/languages/messages/Messages*.php",
			'path' => 'languages/messages/i18n/*.json',
		];

		$this->assertArrayHasKey( 'core', $observed );
		$this->assertEquals( $expected, $observed['core'], 'Core php file' );

		$expected = [
			'repo' => 'extension',
			'name' => 'Translate',
			'orig' => 'file:///IP/extensions/Translate/TranslateSearch.i18n.php',
			'path' => 'TranslateSearch.i18n.php'
		];
		$this->assertArrayHasKey( 'TranslateSearch', $observed );
		$this->assertEquals( $expected, $observed['TranslateSearch'], 'PHP only extension' );

		$expected = [
			'repo' => 'extension',
			'name' => 'Babel',
			'orig' => 'file:///IP/extensions/Babel/i18n/*.json',
			'path' => 'i18n/*.json'
		];
		$this->assertArrayHasKey( 'Babel-0', $observed );
		$this->assertEquals( $expected, $observed['Babel-0'], 'PHP&JSON extension' );

		$expected = [
			'repo' => 'extension',
			'name' => 'Door',
			'orig' => 'file:///IP/extensions/Door/i18n/core/*.json',
			'path' => 'i18n/core/*.json'
		];
		$this->assertArrayHasKey( 'Door-core', $observed );
		$this->assertEquals( $expected, $observed['Door-core'], 'Multidir json extension' );

		$expected = [
			'repo' => 'extension',
			'name' => 'Door',
			'orig' => 'file:///IP/extensions/Door/i18n/extra/*.json',
			'path' => 'i18n/extra/*.json'
		];
		$this->assertArrayHasKey( 'Door-extra', $observed );
		$this->assertEquals( $expected, $observed['Door-extra'], 'Multidir json extension' );

		$expected = [
			'repo' => 'skin',
			'name' => 'Vector',
			'orig' => 'file:///IP/skins/Vector/i18n/*.json',
			'path' => 'i18n/*.json'
		];
		$this->assertArrayHasKey( 'Vector-0', $observed );
		$this->assertEquals( $expected, $observed['Vector-0'], 'Json skin' );
	}
}
