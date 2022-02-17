<?php

namespace CirrusSearch;

use CirrusSearch\Search\CompletionResultsCollector;
use SearchSuggestion;

/**
 * @covers \CirrusSearch\Search\CompletionResultsCollector
 */
class CompletionResultsCollectorTest extends CirrusTestCase {
	public function test() {
		$collector = new CompletionResultsCollector( 3 );
		$collected = $collector->collect( new SearchSuggestion( 10, "test", null, 1 ),
			"prof", "index1" );
		$this->assertTrue( $collected );
		$this->assertFalse( $collector->isFull() );
		$this->assertSame( 1, $collector->size() );

		// same title but lower score
		$collected = $collector->collect( new SearchSuggestion( 1, "test", null, 1 ),
			"prof", "index1" );
		$this->assertFalse( $collected );
		$this->assertFalse( $collector->isFull() );
		$this->assertSame( 1, $collector->size() );

		// same title but better score
		$collected = $collector->collect( new SearchSuggestion( 11, "test", null, 1 ),
			"prof", "index1" );
		$this->assertTrue( $collected );
		$this->assertFalse( $collector->isFull() );
		$this->assertSame( 1, $collector->size() );

		// another title but lower score
		$collected = $collector->collect( new SearchSuggestion( 9, "test", null, 2 ),
			"prof", "index1" );
		$this->assertTrue( $collected );
		$this->assertFalse( $collector->isFull() );
		$this->assertEquals( 2, $collector->size() );

		// another title but better score
		$collected = $collector->collect( new SearchSuggestion( 12, "test", null, 3 ),
			"prof", "index1" );
		$this->assertTrue( $collected );
		$this->assertTrue( $collector->isFull() );
		$this->assertEquals( 3, $collector->size() );

		// another title but lower score
		$collected = $collector->collect( new SearchSuggestion( 8, "test", null, 4 ),
			"prof", "index1" );
		$this->assertFalse( $collected );
		$this->assertTrue( $collector->isFull() );
		$this->assertEquals( 3, $collector->size() );

		// same title but lower score
		$collected = $collector->collect( new SearchSuggestion( 11, "test", null, 3 ),
			"prof", "index1" );
		$this->assertFalse( $collected );
		$this->assertTrue( $collector->isFull() );
		$this->assertEquals( 3, $collector->size() );

		// same title but better score
		$collected = $collector->collect( new SearchSuggestion( 13, "test", null, 3 ),
			"another", "index2" );
		$this->assertTrue( $collected );
		$this->assertTrue( $collector->isFull() );
		$this->assertEquals( 3, $collector->size() );

		$log = new CompletionRequestLog( "blah", "comp_suggest" );
		$set = $collector->logAndGetSet( $log );
		$this->assertEquals( [
				[ 'id' => 3, 'score' => 13 ],
				[ 'id' => 1, 'score' => 11 ],
				[ 'id' => 2, 'score' => 9 ],
			],
			array_map( function ( SearchSuggestion $s ) {
				return [ 'id' => $s->getSuggestedTitleID(), 'score' => $s->getScore() ];
			}, $set->getSuggestions() ) );
		$requests = $log->getRequests();
		$this->assertCount( 1, $requests );
		$hits = reset( $requests )['hits'];
		$this->assertEquals( [
			[
				'title' => 'test',
				'index' => 'index2',
				'pageId' => 3,
				'score' => 13,
				'profileName' => 'another'
			],
			[
				'title' => 'test',
				'index' => 'index1',
				'pageId' => 1,
				'score' => 11,
				'profileName' => 'prof'
			],
			[
				'title' => 'test',
				'index' => 'index1',
				'pageId' => 2,
				'score' => 9,
				'profileName' => 'prof'
			],
		], $hits );
	}
}
