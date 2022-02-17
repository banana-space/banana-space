<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;

/**
 * @covers \CirrusSearch\Search\RecallCrossProjectBlockScorer
 */
class CrossProjectBlockScorerTest extends CirrusIntegrationTestCase {
	public function testRecallScorer() {
		$retval = [
			'b' => $this->mockRS( 5 ),
			'wikt' => $this->mockRS( 10 ),
			'broken' => [],
			'voy' => $this->mockRS( 15 ),
		];
		$scorer = new RecallCrossProjectBlockScorer( [] );
		$reordered = $scorer->reorder( $retval );
		$this->assertEquals( array_keys( $reordered ), [ 'voy', 'wikt', 'b', 'broken' ] );
	}

	public function testStatic() {
		$retval = [
			'b' => $this->mockRS( 5 ),
			'wikt' => $this->mockRS( 1 ),
			'broken' => [],
			'voy' => $this->mockRS( 2 ),
		];
		$scorer = new StaticCrossProjectBlockScorer( [
			'b' => 0.1,
			'wikt' => 0.2,
			'voy' => 0.3,
			'__default__' => 0.01,
		] );
		$reordered = $scorer->reorder( $retval );
		$this->assertEquals( array_keys( $reordered ), [ 'voy', 'wikt', 'b', 'broken' ] );
	}

	public function testRandom() {
		$retval = [
			'b' => $this->mockRS( 5 ),
			'wikt' => $this->mockRS( 1 ),
			'broken' => [],
			'voy' => $this->mockRS( 2 ),
		];
		$scorer = new RandomCrossProjectBlockScorer( [] );
		$reordered = $scorer->reorder( $retval );
		// not sure how to test randomness...
		// let's just make sure that all keys are here
		foreach ( $retval as $k => $v ) {
			$this->assertArrayHasKey( $k, $reordered );
		}
	}

	public function testComposite() {
		$retval = [
			'b' => $this->mockRS( 5000000 ),
			'wikt' => $this->mockRS( 1 ),
			'broken' => [],
			'voy' => $this->mockRS( 1000500 ),
			's' => $this->mockRS( 1020450 ),
		];
		$scorer = new CompositeCrossProjectBlockScorer( [
			'static' => [
				'weight' => 1,
				'settings' => [
					'b' => 0.01,
					'wikt' => 1,
					'__default__' => 0.1,
				]
			],
			'recall' => [
				'weight' => 0.01,
			],
		] );
		$reordered = $scorer->reorder( $retval );
		$this->assertEquals( array_keys( $reordered ), [ 'wikt', 's', 'voy', 'b', 'broken' ] );
	}

	public function testEnWikiExample() {
		$retval = [
			'b' => $this->mockRS( 5000000 ),
			'wikt' => $this->mockRS( 1 ),
			'broken' => [],
			'voy' => $this->mockRS( 1000500 ),
			's' => $this->mockRS( 1020450 ),
		];
		$hashConfig = new HashSearchConfig(
			[ 'CirrusSearchCrossProjectOrder' => 'wmf_enwiki' ],
			[ HashSearchConfig::FLAG_INHERIT ]
		);
		$scorer = CrossProjectBlockScorerFactory::load( $hashConfig );
		$reordered = $scorer->reorder( $retval );
		$this->assertEquals( array_keys( $reordered ), [ 'wikt', 's', 'voy', 'b', 'broken' ] );
	}

	private function mockRS( $totalHits ) {
		$rs = $this->getMockBuilder( CirrusSearchResultSet::class )
			->disableOriginalConstructor()
			->getMock();
		$rs->expects( $this->any() )
			->method( 'getTotalHits' )
			->will( $this->returnValue( $totalHits ) );
		return $rs;
	}
}
