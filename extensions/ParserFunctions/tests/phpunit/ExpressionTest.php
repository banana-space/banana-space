<?php

/**
 * @covers ExprParser
 */
class ExpressionTest extends MediaWikiTestCase {

	/**
	 * @var ExprParser
	 */
	protected $parser;

	protected function setUp() {
		parent::setUp();
		$this->parser = new ExprParser();
	}

	/**
	 * @dataProvider provideExpressions
	 */
	function testExpression( $input, $expected ) {
		$this->assertEquals(
			$expected,
			$this->parser->doExpression( $input )
		);
	}

	function provideExpressions() {
		return [
			[ '1 or 0', '1' ],
			[ 'not (1 and 0)', '1' ],
			[ 'not 0', '1' ],
			[ '4 < 5', '1' ],
			[ '-5 < 2', '1' ],
			[ '-2 <= -2', '1' ],
			[ '4 > 3', '1' ],
			[ '4 > -3', '1' ],
			[ '5 >= 2', '1' ],
			[ '2 >= 2', '1' ],
			[ '1 != 2', '1' ],
			[ '-4 * -4 = 4 * 4', '1' ],
			[ 'not (1 != 1)', '1' ],
			[ '1 + 1', '2' ],
			[ '-1 + 1', '0' ],
			[ '+1 + 1', '2' ],
			[ '4 * 4', '16' ],
			[ '(1/3) * 3', '1' ],
			[ '3 / 1.5', '2' ],
			[ '3 / 0.2', '15' ],
			[ '3 / ( 2.0 * 0.1 )', '15' ],
			[ '3 / ( 2.0 / 10 )', '15' ],
			[ '3 / (- 0.2 )', '-15' ],
			[ '3 / abs( 0.2 )', '15' ],
			[ '3 mod 2', '1' ],
			[ '1e4', '10000' ],
			[ '1e-2', '0.01' ],
			[ '4.0 round 0', '4' ],
			[ 'ceil 4', '4' ],
			[ 'floor 4', '4' ],
			[ '4.5 round 0', '5' ],
			[ '4.2 round 0', '4' ],
			[ '-4.2 round 0', '-4' ],
			[ '-4.5 round 0', '-5' ],
			[ '-2.0 round 0', '-2' ],
			[ 'ceil -3', '-3' ],
			[ 'floor -6.0', '-6' ],
			[ 'ceil 4.2', '5' ],
			[ 'ceil -4.5', '-4' ],
			[ 'floor -4.5', '-5' ],
			[ 'abs(-2)', '2' ],
			[ 'ln(exp(1))', '1' ],
			[ 'trunc(4.5)', '4' ],
			[ 'trunc(-4.5)', '-4' ],
			[ '123 fmod (2^64-1)', '123' ],
			[ '5.7 mod 1.3', '0' ],
			[ '5.7 fmod 1.3', '0.5' ],
		];
	}
}
