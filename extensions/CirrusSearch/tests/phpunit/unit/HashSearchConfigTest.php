<?php

namespace CirrusSearch;

use InvalidArgumentException;

/**
 * @covers \CirrusSearch\HashSearchConfig
 */
class HashSearchConfigTest extends CirrusTestCase {
	public function testKnownFlags() {
		$config = new HashSearchConfig( [], [] );
		$config = new HashSearchConfig( [], [ HashSearchConfig::FLAG_INHERIT ] );
		$config = new HashSearchConfig( [], [ HashSearchConfig::FLAG_LOAD_CONT_LANG ] );
		$config = new HashSearchConfig( [], [ HashSearchConfig::FLAG_LOAD_CONT_LANG, HashSearchConfig::FLAG_INHERIT ] );
		// No exceptions thrown. Assert true to avoid 'risky test'
		$this->assertTrue( true );
	}

	public function provideUnknownFlags() {
		return [
			[ [ 'unknown' ] ],
			[ [ HashSearchConfig::FLAG_INHERIT, 'unknown' ] ],
			[ [ 'other', HashSearchConfig::FLAG_INHERIT ] ],
			[ [ HashSearchConfig::FLAG_INHERIT, HashSearchConfig::FLAG_LOAD_CONT_LANG, 'foo' ] ],
		];
	}

	/**
	 * @dataProvider provideUnknownFlags
	 */
	public function testUnknownFlags( $flags ) {
		$this->expectException( InvalidArgumentException::class );
		new HashSearchConfig( [], $flags );
	}
}
