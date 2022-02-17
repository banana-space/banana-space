<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Search\Rescore\ByKeywordTemplateBoostFunction;

/**
 * @covers \CirrusSearch\Query\BoostTemplatesFeature
 * @covers \CirrusSearch\Search\Rescore\ByKeywordTemplateBoostFunction
 *
 * @group CirrusSearch
 */
class BoostTemplatesFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function parseProvider() {
		return [
			'single template parse' => [
				[ 'Main article' => 2.5 ],
				'boost-templates:"Main article|250%"',
			],
			'multiple template parse' => [
				[ 'Featured article' => 1.75, 'Main article' => 1.50 ],
				'boost-templates:"Featured article|175% Main article|150%"',
			],
			'converts underscores to match indexing' => [
				[ 'Main article' => 1.23 ],
				'boost-templates:Main_article|123%',
			],
			'deboost' => [
				[ 'Thing' => 0.01 ],
				'boost-templates:Thing|1%'
			],
			'invalid' => [
				[],
				'boost-templates:Thing-1%'
			],
		];
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParse( $expect, $term ) {
		$feature = new BoostTemplatesFeature();
		$this->assertParsedValue( $feature, $term, [ 'boost-templates' => $expect ], [] );
		$this->assertExpandedData( $feature, $term, [], [] );
		$this->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::allWikisStrategy() );
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testBoost( $expected, $term ) {
		$this->assertBoost( new BoostTemplatesFeature(), $term, new ByKeywordTemplateBoostFunction( $expected ), [] );
	}
}
