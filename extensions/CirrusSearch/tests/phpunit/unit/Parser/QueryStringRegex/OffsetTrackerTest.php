<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\Parser\AST\ParsedNode;

/**
 * @covers \CirrusSearch\Parser\QueryStringRegex\OffsetTracker
 * @group CirrusSearch
 */
class OffsetTrackerTest extends CirrusTestCase {

	public function test() {
		$tracker = new OffsetTracker();
		$this->assertFalse( $tracker->overlap( 2, 5 ) );
		$tracker->append( 2, 4 );
		$this->assertTrue( $tracker->overlap( 2, 4 ) );
		$this->assertTrue( $tracker->overlap( 1, 3 ) );
		$this->assertTrue( $tracker->overlap( 3, 4 ) );
		$this->assertTrue( $tracker->overlap( 3, 5 ) );
		$this->assertTrue( $tracker->overlap( 3, 6 ) );
		$this->assertFalse( $tracker->overlap( 1, 2 ) );
		$this->assertFalse( $tracker->overlap( 5, 6 ) );
		$this->assertSame( 0, $tracker->getMinimalUnconsumedOffset() );
		$this->assertSame( 4, $tracker->getMinimalUnconsumedOffset( 2 ) );
		$this->assertSame( 4, $tracker->getMinimalUnconsumedOffset( 3 ) );
		$this->assertSame( 5, $tracker->getMinimalUnconsumedOffset( 5 ) );
	}

	public function testWithParsedNodeArray() {
		$tracker = new OffsetTracker();
		$this->assertFalse( $tracker->overlap( 2, 5 ) );
		$nodes = [
			$this->newMockNode( 2, 4 ),
			$this->newMockNode( 5, 8 ),
			$this->newMockNode( 8, 10 ),
		];
		$tracker->appendNodes( $nodes );
		$this->assertFalse( $tracker->overlap( 0, 1 ) );
		$this->assertFalse( $tracker->overlap( 0, 2 ) );
		$this->assertFalse( $tracker->overlap( 1, 2 ) );
		$this->assertTrue( $tracker->overlap( 1, 3 ) );
		$this->assertTrue( $tracker->overlap( 2, 3 ) );
		$this->assertFalse( $tracker->overlap( 4, 5 ) );
		$this->assertTrue( $tracker->overlap( 4, 6 ) );
		$this->assertTrue( $tracker->overlap( 8, 9 ) );
		$this->assertTrue( $tracker->overlap( 9, 10 ) );
	}

	public function newMockNode( $start, $end ) {
		return $this->getMockBuilder( ParsedNode::class )
			->setConstructorArgs( [ $start, $end ] )
			->getMockForAbstractClass();
	}
}
