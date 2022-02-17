<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusTestCase;

/**
 * @covers \CirrusSearch\Search\TeamDraftInterleaver::interleaveResults
 */
class TeamDraftInterleaverTest extends CirrusTestCase {
	public function testInterleaveResults() {
		// Construct some pointless array with all overlapping values
		$limit = 20;
		$a = range( 5, 5 + $limit - 1 );
		$b = range( 1, 1 + $limit - 1 );
		$a = array_combine( $a, $a );
		$b = array_combine( $b, $b );

		for ( $i = 0; $i < 10; ++$i ) {
			// Use a constant seed to allow determinism
			mt_srand( 12345 * $i );
			list( $interleave, $teamA, $teamB, $aOffset ) = TeamDraftInterleaver::interleaveResults( $a, $b, $limit );
			// Very basic assertions about the shape of results
			$this->assertCount( 10, $teamA );
			$this->assertCount( 10, $teamB );
			$this->assertCount( 0, array_intersect( $teamA, $teamB ) );
			$this->assertCount( $limit, $interleave );

			// Verify offset is last used iteam in a. Note that this isn't
			// perfect, there could be items in a after this that were
			// used by B, but it's good enough.
			// Remember that offset is < 0, because its a number where
			// the next page starts at $offset + $limit (0 indexed).
			$nextIdx = $aOffset + $limit;
			$last = array_slice( $a, $nextIdx - 1, 1 );
			$this->assertCount( 1, array_intersect( $interleave, $last ) );
			$next = array_slice( $a, $nextIdx, 1 );
			$this->assertCount( 0, array_intersect( $interleave, $next ) );
		}
	}

	/**
	 * Test that the InterleavedResultSet class is properly delegating to teamA
	 * all the methods declared in CirrusSearchResultSet except the few that
	 * are decorated
	 */
	public function testInterleavedResultSetDelegates() {
		$interleavedRset = new \ReflectionClass( InterleavedResultSet::class );
		$csrs = new \ReflectionClass( CirrusSearchResultSet::class );
		$interleavedRsetMethods = $interleavedRset->getMethods( \ReflectionMethod::IS_PUBLIC );
		$csrsmethods = array_map(
			function ( \ReflectionMethod $method ) {
				return $method->getName();
			},
			$csrs->getMethods( \ReflectionMethod::IS_PUBLIC ) );

		$delegatedMethods = array_diff(
			$csrsmethods,
			[ 'getOffset', 'extractResults', 'numRows', 'count', 'setAugmentedData',
				'augmentResult', 'getIterator' ]
		);
		$interleavedRsetMethods = array_filter(
			$interleavedRsetMethods,
			function ( \ReflectionMethod $method ) use ( $delegatedMethods ) {
				return in_array(
					$method->getName(),
					$delegatedMethods
				);
			}
		);
		$cscrMockTeamA = $this->getMockBuilder( CirrusSearchResultSet::class )
			->getMock();
		/** @var \ReflectionMethod $method */
		$allParams = [];
		foreach ( $interleavedRsetMethods as $method ) {
			$params = [];
			foreach ( $method->getParameters() as $param ) {
				$paramId = $method->getName() . '-' . $param->getPosition();
				if ( $param->hasType() ) {
					if ( method_exists( $param->getType(), "getName" ) ) {
						$typeName = $param->getType()->getName();
					} else {
						$typeName = $param->getType()->__toString();
					}
					switch ( $typeName ) {
						case 'int':
							$params[] = mt_rand();
							break;
						case 'float':
							$params[] = mt_rand() / mt_rand( 1 );
							break;
						case 'string':
							$params[] = $paramId;
							break;
						case 'array':
							$params[] = [ $paramId ];
							break;
						default:
							if ( !$param->getClass() ) {
								$this->fail( "Invalid param type " . $param->getName() );
							}
							$params[] = $this->getMockBuilder( $param->getClass()->getName() )
								->disableOriginalConstructor()
								->getMock();
					}
				} else {
					$params[] = $paramId;
				}
			}
			$cscrMockTeamA->expects( $this->once() )
				->method( $method->getName() )
				->willReturnCallback( function () use ( $params ) {
					$this->assertEquals( $params, func_get_args() );
				} );
			$allParams[$method->getName()] = $params;
		}
		$interleaved = new InterleavedResultSet( $cscrMockTeamA, [], [], [], 1 );
		/** @var \ReflectionMethod $method */
		foreach ( $interleavedRsetMethods as $method ) {
			$method->invokeArgs( $interleaved, $allParams[$method->getName()] );
		}
	}

	public function testTeamAExhausted() {
		$a = range( 100, 104 );
		$b = range( 0, 20 );
		$a = array_combine( $a, $a );
		$b = array_combine( $b, $b );

		list( $interleave, $teamA, $teamB, ) = TeamDraftInterleaver::interleaveResults( $a, $b, 15 );
		$this->assertCount( 15, $interleave );
		$this->assertCount( 5, $teamA );
		$this->assertCount( 10, $teamB );
	}

	public function testTeamBExhausted() {
		$a = range( 100, 120 );
		$b = range( 0, 4 );
		$a = array_combine( $a, $a );
		$b = array_combine( $b, $b );

		list( $interleave, $teamA, $teamB, ) = TeamDraftInterleaver::interleaveResults( $a, $b, 11 );
		$this->assertCount( 11, $interleave );
		$this->assertCount( 6, $teamA );
		$this->assertCount( 5, $teamB );
	}

	public function testNotEnoughResults() {
		$a = range( 100, 102 );
		$b = range( 0, 4 );
		$a = array_combine( $a, $a );
		$b = array_combine( $b, $b );

		list( $interleave, $teamA, $teamB, ) = TeamDraftInterleaver::interleaveResults( $a, $b, 20 );
		$this->assertCount( 8, $interleave );
		$this->assertCount( 3, $teamA );
		$this->assertCount( 5, $teamB );
	}

	public function testOverlap() {
		$a = range( 0, 9 );
		$b = range( 0, 9 );
		$a = array_combine( $a, $a );
		$b = array_combine( $b, $b );

		list( $interleave, $teamA, $teamB, ) = TeamDraftInterleaver::interleaveResults( $a, $b, 20 );
		$this->assertCount( 10, $interleave );
		$this->assertCount( 5, $teamA );
		$this->assertCount( 5, $teamB );
	}

}
