<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\SearchContext;

/**
 * @covers \CirrusSearch\Query\LocalFeature
 * @covers \CirrusSearch\Query\SimpleKeywordFeature
 * @group CirrusSearch
 */
class LocalFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function parseProvider() {
		return [
			'simple local' => [
				'foo bar',
				true,
				'local:foo bar'
			],
			'simple local with sep spaces' => [
				' foo bar',
				true,
				'local: foo bar'
			],
			'local can have spaces before' => [
				'foo bar',
				true,
				'  local:foo bar'
			],
			'local must be at the beginning' => [
				'foo local:bar',
				false,
				'foo local:bar',
			],
		];
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParse( $expectedRemaining, $isLocal, $term ) {
		$feature = new LocalFeature();
		if ( $isLocal ) {
			$this->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
		}
		$this->assertRemaining( $feature, $term, $expectedRemaining );
		$context = new SearchContext( new HashSearchConfig( [] ) );
		$feature->apply( $context, $term );
		$this->assertEquals( $isLocal, $context->getLimitSearchToLocalWiki() );
	}
}
