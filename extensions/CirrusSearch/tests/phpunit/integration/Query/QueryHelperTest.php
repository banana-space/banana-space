<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusIntegrationTestCase;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Query\QueryHelper
 */
class QueryHelperTest extends CirrusIntegrationTestCase {
	/**
	 * @dataProvider provideMatchPage
	 *
	 * @param mixed $expected
	 * @param string $field
	 * @param string $title
	 * @param string $underscores
	 */
	public function testMatchPage( $expected, $field, $title, $underscores ) {
		$match = QueryHelper::matchPage( $field, $title, $underscores );

		$this->assertInstanceOf( \Elastica\Query\MatchQuery::class, $match );

		$expectedArray = [ $field => [ 'query' => $expected ] ];

		$this::assertEquals( json_encode( $expectedArray, JSON_PRETTY_PRINT ),
			json_encode( $match->getParams(), JSON_PRETTY_PRINT )
		);
	}

	public function provideMatchPage() {
		return [
			[ 'Page title', 'foo', 'Page title', false ],
			[ 'Page title', 'foo', 'page_title', false ],
			[ 'Page_title', 'foo', 'Page title', true ],
			[ 'Page_title', 'foo', 'Page_title', true ],
		];
	}
}
