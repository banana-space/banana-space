<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusTestCase;

/**
 * @covers \CirrusSearch\Maintenance\Set
 */
class SetTest extends CirrusTestCase {
	public function testAdd() {
		$set = new Set();
		$this->assertCount( 0, $set );
		$this->assertFalse( $set->contains( 'foo' ) );
		$set->add( 'foo' );
		$this->assertEquals( [ 'foo' ], $set->values() );
		$this->assertCount( 1, $set );
		$this->assertTrue( $set->contains( 'foo' ) );
		$set->add( 'foo' );
		$this->assertEquals( [ 'foo' ], $set->values() );
		$this->assertCount( 1, $set );
		$this->assertTrue( $set->contains( 'foo' ) );
	}

	public function testAddAll() {
		$set = new Set();
		$set->addAll( [ 1, 2, 3 ] );
		$this->assertSame( [ 1, 2, 3 ], $set->values() );
		$this->assertCount( 3, $set );
		$this->assertFalse( $set->contains( 0 ) );
		$this->assertTrue( $set->contains( 1 ) );
		$this->assertTrue( $set->contains( 2 ) );
		$this->assertTrue( $set->contains( 3 ) );
		$this->assertFalse( $set->contains( 4 ) );
	}

	public function testUnion() {
		$a = new Set();
		$a->addAll( [ 1, 2, 3 ] );
		$b = new Set();
		$b->addAll( [ 3, 4, 5 ] );

		$this->assertCount( 3, $a );
		$a->union( $b );
		$this->assertCount( 5, $a );
		$this->assertCount( 3, $b );
	}

}
