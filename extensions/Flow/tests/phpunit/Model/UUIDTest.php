<?php

namespace Flow\Tests\Model;

use Flow\Model\UUID;
use Flow\Model\UUIDBlob;
use Flow\Tests\FlowTestCase;

/**
 * @covers \Flow\Model\UUID
 *
 * @group Flow
 */
class UUIDTest extends FlowTestCase {

	public function testFixesCapitalizedDataWhenUnserializing() {
		$uuid = UUID::create( 'u9pdp74asmm1qa81' );
		$serialized = serialize( $uuid );
		// We are targeting this portion of the serialized string:
		// s:16:"s3xyjucl93jtq2ci"
		$broken = preg_replace_callback(
			'/(s:16:")([a-z0-9])/',
			function ( $matches ) {
				return $matches[1] . strtoupper( $matches[2] );
			},
			$serialized
		);
		$this->assertNotEquals( $broken, $serialized, 'Failed to create a broken uuid to test unserializing' );
		$fixed = unserialize( $broken );
		$this->assertTrue( $uuid->equals( $fixed ) );
		$this->assertEquals( $uuid->getAlphadecimal(), $fixed->getAlphadecimal() );
	}

	public function invalidInputProvider() {
		$valid = 'u9pdkbdvsgz206kh';

		return [
			[ '' ],
			[ strtoupper( $valid ) ],
			[ strtoupper( UUID::alnum2hex( $valid ) ) ],
			[ ucfirst( $valid ) ],
		];
	}

	/**
	 * @dataProvider invalidInputProvider
	 */
	public function testInvalidInputOnCreate( $invalidInput ) {
		$this->expectException( \Flow\Exception\InvalidInputException::class );
		UUID::create( $invalidInput );
	}

	public static function uuidConversionProvider() {
		// sample uuid from UIDGenerator::newTimestampedUID128()
		$numeric_128 = '6709199728898751234959525538795913762';
		$hex_128 = \Wikimedia\base_convert( $numeric_128, 10, 16, 32 );

		// Conversion from 128 bit to 88 bit takes the left
		// most 88 bits.
		$bits_88 = substr( \Wikimedia\base_convert( $numeric_128, 10, 2, 128 ), 0, 88 );
		$numeric_88 = \Wikimedia\base_convert( $bits_88, 2, 10 );
		$hex_88 = \Wikimedia\base_convert( $numeric_88, 10, 16, 22 );
		$bin_88 = new UUIDBlob( pack( 'H*', $hex_88 ) );
		$pretty_88 = \Wikimedia\base_convert( $numeric_88, 10, 36 );

		return [
			[
				'128 bit hex input must be truncated to 88bit output',
				// input
				$hex_128,
				// binary
				$bin_88,
				// hex
				$hex_88,
				// base36 output
				$pretty_88,
			],

			[
				'88 bit binary input',
				// input
				$bin_88,
				// binary
				$bin_88,
				// hex
				$hex_88,
				// pretty
				$pretty_88,
			],

			[
				'88 bit numeric input',
				// input
				$numeric_88,
				// binary
				$bin_88,
				// hex
				$hex_88,
				// pretty
				$pretty_88,
			],

			[
				'88 bit hex input',
				// input
				$hex_88,
				// binary
				$bin_88,
				// hex
				$hex_88,
				// pretty
				$pretty_88,
			],

			[
				'88 bit pretty input',
				// input
				$pretty_88,
				// binary
				$bin_88,
				// hex
				$hex_88,
				// pretty
				$pretty_88,
			],

		];
	}

	/**
	 * @dataProvider uuidConversionProvider
	 */
	public function testUUIDConversion( $msg, $input, $binary, $hex, $pretty ) {
		$uuid = UUID::create( $input );

		$this->assertEquals( $binary, $uuid->getBinary(), "Compare binary: $msg" );
		// $this->assertEquals( $hex, $uuid->getHex(), "Compare hex: $msg" );
		$this->assertEquals( $pretty, $uuid->getAlphadecimal(), "Compare pretty: $msg" );
	}

	public static function prettyProvider() {
		return [
			// maximal base 36 value ( 2^88 )
			[ '12vwzoefjlykjgcnwf' ],
			// current unpadded values from uidgenerator
			[ 'rlnn1941hqtdtn8a' ],
		];
	}

	/**
	 * @dataProvider prettyProvider
	 */
	public function testUnpaddedPrettyUuid( $uuid ) {
		$this->assertEquals( $uuid, UUID::create( $uuid )->getAlphadecimal() );
	}

	public function testConversionToTimestamp() {
		$this->assertSame( '20150303221220', UUID::create( 'scv3pvbt40kcyy4g' )->getTimestamp() );
	}

	public function testCreateLowNumber() {
		$this->assertEquals( UUID::create( 10 )->getAlphadecimal(), '000000000000000a' );
	}

	public static function uuidProvider() {
		return [
			[ UUID::create() ],
			[ UUID::getComparisonUUID( 1 ) ],
		];
	}

	/**
	 * @dataProvider uuidProvider
	 * @param UUID $uuid
	 */
	public function testAlphadecimalRoundtrip( UUID $uuid ) {
		$expect = $uuid->getAlphadecimal();
		$new = UUID::create( $expect );

		$this->assertEquals( $expect, $new->getAlphadecimal() );
	}

	/**
	 * @dataProvider uuidProvider
	 * @param UUID $uuid
	 */
	public function testHexRoundtrip( UUID $uuid ) {
		$expect = $uuid->getHex();
		$new = UUID::create( $expect );

		$this->assertEquals( $expect, $new->getHex() );
	}

	/**
	 * @dataProvider uuidProvider
	 * @param UUID $uuid
	 */
	public function testBinaryRoundtrip( UUID $uuid ) {
		$expect = $uuid->getBinary();
		$new = UUID::create( $expect );

		$this->assertEquals( $expect->fetch(), $new->getBinary()->fetch() );
	}
}
