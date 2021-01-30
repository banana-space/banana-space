<?php

namespace Flow\Tests\Model;

use Flow\Model\UserTuple;

/**
 * @covers \Flow\Model\UserTuple
 *
 * @group Flow
 */
class UserTupleTest extends \MediaWikiUnitTestCase {

	public function invalidInputProvider() {
		return [
			[ 'foo', 0, '' ],
			[ 'foo', 1234, '127.0.0.1' ],
			[ '', 0, '127.0.0.1' ],
			[ 'foo', -25, '' ],
			[ 'foo', null, '127.0.0.1' ],
			[ null, 55, '' ],
			[ 'foo', 0, null ],
		];
	}

	/**
	 * @dataProvider invalidInputProvider
	 */
	public function testInvalidInput( $wiki, $id, $ip ) {
		$this->expectException( \Flow\Exception\InvalidDataException::class );
		new UserTuple( $wiki, $id, $ip );
	}

	public function validInputProvider() {
		return [
			[ 'foo', 42, null ],
			[ 'foo', 42, '' ],
			[ 'foo', 0, '127.0.0.1' ],
			[ 'foo', '0', '10.1.2.3' ],
		];
	}

	/**
	 * @dataProvider validInputProvider
	 */
	public function testValidInput( $wiki, $id, $ip ) {
		new UserTuple( $wiki, $id, $ip );
		// no error thrown from constructor
		$this->assertTrue( true );
	}
}
