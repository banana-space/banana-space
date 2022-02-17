<?php

namespace CirrusSearch;

/**
 * @covers \CirrusSearch\CrossSearchStrategy
 * @group CirrusSearch
 */
class CrossSearchStrategyTest extends CirrusTestCase {

	public function testHostWikiOnly() {
		$strategy = CrossSearchStrategy::hostWikiOnlyStrategy();

		$this->assertFalse( $strategy->isCrossLanguageSearchSupported() );
		$this->assertFalse( $strategy->isCrossLanguageSearchSupported() );
		$this->assertFalse( $strategy->isExtraIndicesSearchSupported() );

		$this->assertSame( $strategy, CrossSearchStrategy::hostWikiOnlyStrategy() );
	}

	public function testAllWikis() {
		$strategy = CrossSearchStrategy::allWikisStrategy();

		$this->assertTrue( $strategy->isCrossLanguageSearchSupported() );
		$this->assertTrue( $strategy->isCrossLanguageSearchSupported() );
		$this->assertTrue( $strategy->isExtraIndicesSearchSupported() );

		$this->assertSame( $strategy, CrossSearchStrategy::allWikisStrategy() );
	}

	public function testIntersect() {
		$max = 2 ** 3;
		for ( $i = 0; $i < $max; $i++ ) {
			$crossP = ( $i & 1 ) !== 0;
			$crossL = ( $i & 2 ) !== 0;
			$extraI = ( $i & 4 ) !== 0;
			$strategy = new CrossSearchStrategy( $crossP, $crossL, $extraI );
			$this->assertEquals( $strategy, $strategy->intersect( $strategy ) );

			$cancelOne = new CrossSearchStrategy( false, $crossL, $extraI );
			$intersection = $cancelOne->intersect( $strategy );
			$this->assertEquals( $intersection, $strategy->intersect( $cancelOne ) );

			$this->assertFalse( $intersection->isCrossProjectSearchSupported() );
			$this->assertEquals( $strategy->isCrossLanguageSearchSupported(), $intersection->isCrossLanguageSearchSupported() );
			$this->assertEquals( $strategy->isExtraIndicesSearchSupported(), $intersection->isExtraIndicesSearchSupported() );

			$cancelOne = new CrossSearchStrategy( $crossP, false, $extraI );
			$intersection = $cancelOne->intersect( $strategy );
			$this->assertEquals( $intersection, $strategy->intersect( $cancelOne ) );

			$this->assertEquals( $strategy->isCrossProjectSearchSupported(), $intersection->isCrossProjectSearchSupported() );
			$this->assertFalse( $intersection->isCrossLanguageSearchSupported() );
			$this->assertEquals( $strategy->isExtraIndicesSearchSupported(), $intersection->isExtraIndicesSearchSupported() );

			$cancelOne = new CrossSearchStrategy( $crossP, $crossL, false );
			$intersection = $cancelOne->intersect( $strategy );
			$this->assertEquals( $intersection, $strategy->intersect( $cancelOne ) );

			$this->assertEquals( $strategy->isCrossProjectSearchSupported(), $intersection->isCrossProjectSearchSupported() );
			$this->assertEquals( $strategy->isCrossLanguageSearchSupported(), $intersection->isCrossLanguageSearchSupported() );
			$this->assertFalse( $intersection->isExtraIndicesSearchSupported() );
		}

		$this->assertSame( CrossSearchStrategy::allWikisStrategy(),
			CrossSearchStrategy::allWikisStrategy()->intersect( CrossSearchStrategy::allWikisStrategy() ) );

		$this->assertSame( CrossSearchStrategy::hostWikiOnlyStrategy(),
			CrossSearchStrategy::hostWikiOnlyStrategy()->intersect( CrossSearchStrategy::allWikisStrategy() ) );
	}
}
