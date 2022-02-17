<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\CrossSearchStrategy;

/**
 * @covers \CirrusSearch\Query\LinksToFeature
 * @group CirrusSearch
 */
class LinksToFeatureTest extends CirrusIntegrationTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function provideQueries() {
		return [
			'simple' => [
				'linksto:test',
				'test'
			],
			'simple with ns' => [
				'linksto:help:test',
				'help:test'
			],
			'simple empty' => [
				'linksto:""',
				'' // FIXME: we should probably not send a filter at all
			]
		];
	}

	/**
	 * @dataProvider provideQueries()
	 * @param $query
	 * @param $filterValue
	 */
	public function test( $query, $filterValue ) {
		$feature = new LinksToFeature();
		$this->assertParsedValue( $feature, $query, null, [] );
		$this->assertExpandedData( $feature, $query, [], [] );
		$this->assertCrossSearchStrategy( $feature, $query, CrossSearchStrategy::allWikisStrategy() );
		$filter = QueryHelper::matchPage( 'outgoing_link', $filterValue, true );
		$this->assertFilter( $feature, $query, $filter, [] );
	}
}
