<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate;

use LocalisationUpdate\Reader\ReaderFactory;

/**
 * @covers \LocalisationUpdate\Reader\ReaderFactory
 */
class ReaderFactoryTest extends \PHPUnit\Framework\TestCase {
	/**
	 * @dataProvider getReaderProvider
	 */
	public function testGetReader( $input, $expected, $comment ) {
		$factory = new ReaderFactory();
		$reader = $factory->getReader( $input );
		$observed = get_class( $reader );
		$this->assertEquals( $expected, $observed, $comment );
	}

	public function getReaderProvider() {
		return [
			[
				'languages/i18n/fi.json',
				'LocalisationUpdate\Reader\JSONReader',
				'core json file',
			],
			[
				'extension/Translate/i18n/core/de.json',
				'LocalisationUpdate\Reader\JSONReader',
				'extension json file',
			],
		];
	}
}
