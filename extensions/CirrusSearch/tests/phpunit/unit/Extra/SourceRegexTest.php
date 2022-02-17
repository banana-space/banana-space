<?php

namespace CirrusSearch\Extra\Query;

use CirrusSearch\CirrusTestCase;

class SourceRegexTest extends CirrusTestCase {
	/**
	 * @dataProvider provideTestSetLocale
	 * @covers \CirrusSearch\Extra\Query\SourceRegex::setLocale
	 */
	public function testSetLocale( string $languageCode, string $expectedLocale ) {
		$regex = new SourceRegex();
		$regex->setLocale( $languageCode );
		$this->assertEquals( $expectedLocale, $regex->getParam( 'locale' ) );
	}

	public static function provideTestSetLocale(): array {
		return [
			[ 'be-tarask', 'be' ],
			[ 'sh', 'hbs' ],
			[ 'shy-latn', '' ],
			[ 'fr', 'fr' ],
			[ 'new-unknown', 'new-unknown' ],
		];
	}
}
