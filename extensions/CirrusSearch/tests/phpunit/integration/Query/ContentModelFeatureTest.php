<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\CrossSearchStrategy;

/**
 * @covers \CirrusSearch\Query\ContentModelFeature
 * @group CirrusSearch
 */
class ContentModelFeatureTest extends CirrusIntegrationTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function provideQueries() {
		return [
			'simple' => [
				'contentmodel:test',
				[ 'match' => [
					'content_model' => [
						'query' => 'test'
					]
				] ],
			],
			'simple quoted' => [
				'contentmodel:"simple test"',
				[ 'match' => [
					'content_model' => [
						'query' => 'simple test'
					]
				] ],
			]
		];
	}

	/**
	 * @dataProvider provideQueries()
	 * @param string $query
	 * @param array $expectedFilter
	 */
	public function test( $query, $expectedFilter ) {
		$feature = new ContentModelFeature();
		$this->assertParsedValue( $feature, $query, null, [] );
		$this->assertCrossSearchStrategy( $feature, $query, CrossSearchStrategy::allWikisStrategy() );
		$this->assertExpandedData( $feature, $query, [], [] );
		$this->assertFilter( $feature, $query, $expectedFilter, [] );
	}
}
