<?php

namespace Test\Parsoid\Utils;

use PHPUnit\Framework\TestCase;
use Wikimedia\Parsoid\Utils\PHPUtils;

class PHPUtilsTest extends TestCase {
	/**
	 * @covers \Wikimedia\Parsoid\Utils\PHPUtils::reStrip
	 * @dataProvider provideReStrip
	 */
	public function testReStrip( $re, $delimiter, $expected ) {
		$actual = PHPUtils::reStrip( $re, $delimiter );
		$this->assertEquals( $expected, $actual );
	}

	public function provideReStrip() {
		return [
			[ '/abc/', null, 'abc' ], // easy case
			[ ' /abc/mA', null, 'abc' ], // flags and leading whitespace
			[ '~ab/de~x', '/', 'ab\\/de' ], // new delimiter
			[ '~ab\/de~x', '/', 'ab\/de' ], // already escaped
			[ "~\\\u{1F4A9}~u", '/', "\\\u{1F4A9}" ], // unicode safe
		];
	}

	/**
	 * @covers \Wikimedia\Parsoid\Utils\PHPUtils::coalesce
	 * @dataProvider provideCoalesce
	 */
	public function testCoalesce( array $args, $expectedResult ) {
		$this->assertSame( $expectedResult, PHPUtils::coalesce( ...$args ) );
	}

	public function provideCoalesce() {
		return [
			[ [ 1, 2 ], 1 ],
			[ [ 1, null ], 1 ],
			[ [ null, 1 ], 1 ],
			[ [ false, 1 ], 1 ],
			[ [ 0, 1 ], 1 ],
			[ [ 0.0, 1 ], 1 ],
			[ [ '', 1 ], 1 ],
			[ [ '0', 1 ], '0' ],
			[ [ ' ', 1 ], ' ' ],
			[ [ [], 1 ], [] ],
			[ [ 0 ], 0 ],
			[ [ 0, 0, 1, 0 ], 1 ],
		];
	}

	/**
	 * @covers \Wikimedia\Parsoid\Utils\PHPUtils::safeSubstr
	 * @covers \Wikimedia\Parsoid\Utils\PHPUtils::assertValidUTF8
	 * @dataProvider provideSafeSubstr
	 */
	public function testSafeSubstr( $s, $start, $end, $expectedOk ) {
		// The full string needs to be valid UTF-8
		PHPUtils::assertValidUTF8( $s );
		PHPUtils::safeSubstr( $s, 0, null, true );

		// Fast verification
		$s1 = false;
		try {
			$s1 = PHPUtils::safeSubstr( $s, $start, $end - $start );
			$ok = true;
		} catch ( \Exception $e ) {
			$ok = false;
		}
		$this->assertEquals( $expectedOk, $ok );

		// Now try full string verification
		$s2 = substr( $s, $start, $end - $start );
		try {
			PHPUtils::assertValidUTF8( $s2 );
			$ok = true;
		} catch ( \Exception $e ) {
			$ok = false;
		}
		$this->assertEquals( $expectedOk, $ok );
		if ( $ok ) {
			$this->assertEquals( $s1, $s2 );
		}

		// Full string verification, take 2
		$s3 = false;
		try {
			$s3 = PHPUtils::safeSubstr( $s, $start, $end - $start, true );
			$ok = true;
		} catch ( \Exception $e ) {
			$ok = false;
		}
		$this->assertEquals( $expectedOk, $ok );
		$this->assertEquals( $s1, $s3 );
	}

	public function provideSafeSubstr() {
		$cases = [
			// Simple case
			[ 'input' => 'abc', 'indices' => [
				[ 0, 3, true ], [ 1, 3, true ], [ 2, 3, true ],
				[ 0, 2, true ], [ 0, 1, true ], [ 0, 0, true ],
			] ],
			// 1-byte codepoint, then 2-byte codepoint, then 3-byte codepoint,
			// then 4-byte codepoint = 1+2+3+4 = 10 bytes long
			[ 'input' => "A\u{0531}\u{4EBA}\u{1F4A9}", 'indices' => [
				// truncating the head
				[ 0, 10, true ], [ 1, 10, true ], [ 2, 10, false ],
				[ 3, 10, true ], [ 4, 10, false ], [ 5, 10, false ],
				[ 6, 10, true ], [ 7, 10, false ], [ 8, 10, false ],
				[ 9, 10, false ], [ 10, 10, true ],
				// truncating the tail
				[ 0, 0, true ], [ 0, 1, true ], [ 0, 2, false ],
				[ 0, 3, true ], [ 0, 4, false ], [ 0, 5, false ],
				[ 0, 6, true ], [ 0, 7, false ], [ 0, 8, false ],
				[ 0, 9, false ], [ 0, 10, true ],
				// three examples from the middle
				[ 1, 6, true ],
				[ 2, 6, false ],
				[ 1, 7, false ],
			] ],
		];
		// expand these by duplicating the input string for better
		// diagnostics if things fail
		foreach ( $cases as $c ) {
			foreach ( $c['indices'] as $idx ) {
				yield [ $c['input'], $idx[0], $idx[1], $idx[2] ];
			}
		}
	}
}
