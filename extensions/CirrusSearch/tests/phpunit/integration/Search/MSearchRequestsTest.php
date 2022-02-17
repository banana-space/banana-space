<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusIntegrationTestCase;
use Elastica\Client;
use Elastica\Index;
use Elastica\Query\MatchQuery;
use Elastica\Response;

/**
 * @covers \CirrusSearch\Search\MSearchRequests
 * @covers \CirrusSearch\Search\MSearchResponses
 */
class MSearchRequestsTest extends CirrusIntegrationTestCase {

	public function testDumpQuery() {
		$search1 = ( new Index( new Client(), "test_one" ) )
			->createSearch( new MatchQuery( 'one', 'test' ), [ 'terminate_after' => 10 ] );
		$search2 = ( new Index( new Client(), "test_two" ) )
			->createSearch( new MatchQuery( 'two', 'test' ), [ 'terminate_after' => 100 ] );
		$requests = MSearchRequests::build( 'first', $search1 );
		$requests->addRequest( 'second', $search2 );
		$dumpQuery = $requests->dumpQuery( 'my description' );
		$this->assertTrue( $dumpQuery->isOK() );
		$this->assertEquals( [
			'first' => [
				'description' => 'my description',
				'path' => 'test_one/_search',
				'options' => [ 'terminate_after' => 10 ],
				'params' => [ 'terminate_after' => 10 ],
				'query' => [ 'query' => ( new MatchQuery( 'one', 'test' ) )->toArray() ],
			],
			'second' => [
				'description' => 'my description',
				'path' => 'test_two/_search',
				'options' => [ 'terminate_after' => 100 ],
				'params' => [ 'terminate_after' => 100 ],
				'query' => [ 'query' => ( new MatchQuery( 'two', 'test' ) )->toArray() ],
			]
		], $dumpQuery->getValue() );
	}

	public function testTimeout() {
		$search1 = ( new Index( new Client(), "test_one" ) )
			->createSearch( new MatchQuery( 'one', 'test' ), [ 'terminate_after' => 10 ] );
		$requests = MSearchRequests::build( 'first', $search1 );
		$rset = new \Elastica\ResultSet( new Response( [ 'timed_out' => true ] ), $search1->getQuery(), [] );
		$timedOut = $requests->toMSearchResponses( [ $rset ] );
		$this->assertFalse( $timedOut->hasFailure() );
		$this->assertTrue( $timedOut->hasTimeout() );
		$this->assertTrue( $timedOut->hasResponses() );
		$this->assertTrue( $timedOut->hasResultsFor( 'first' ) );
	}

	public function testFailed() {
		$search1 = ( new Index( new Client(), "test_one" ) )
			->createSearch( new MatchQuery( 'one', 'test' ), [ 'terminate_after' => 10 ] );
		$requests = MSearchRequests::build( 'first', $search1 );
		$status = \Status::newFatal( 'blow' );
		$failed = $requests->failure( $status );
		$this->assertTrue( $failed->hasFailure() );
		$this->assertFalse( $failed->hasTimeout() );
		$this->assertFalse( $failed->hasResponses() );
		$this->assertFalse( $failed->hasResultsFor( 'first' ) );
		$this->assertSame( $status, $failed->getFailure() );
	}

	public function testSuccess() {
		$search1 = ( new Index( new Client(), "test_one" ) )
			->createSearch( new MatchQuery( 'one', 'test' ), [ 'terminate_after' => 10 ] );
		$search2 = ( new Index( new Client(), "test_two" ) )
			->createSearch( new MatchQuery( 'two', 'test' ), [ 'terminate_after' => 100 ] );
		$requests = MSearchRequests::build( 'first', $search1 );
		$requests->addRequest( 'second', $search2 );

		$firstRS = new \Elastica\ResultSet( new Response( [ 'dump' => 'one' ] ), $search1->getQuery(), [] );
		$secondRS = new \Elastica\ResultSet( new Response( [ 'dump' => 'two' ] ), $search1->getQuery(), [] );
		$responses = $requests->toMSearchResponses( [ $firstRS, $secondRS ] );
		$this->assertTrue( $responses->hasResultsFor( 'first' ) );
		$this->assertTrue( $responses->hasResultsFor( 'second' ) );
		$this->assertSame( $firstRS, $responses->getResultSet( 'first' ) );
		$this->assertSame( $secondRS, $responses->getResultSet( 'second' ) );

		$type = new class() extends BaseResultsType {

			public function getStoredFields() {
				return [];
			}

			public function getHighlightingConfiguration( array $extraHighlightFields = [] ) {
				return [];
			}

			public function transformElasticsearchResult( \Elastica\ResultSet $resultSet ) {
				return $resultSet->getResponse()->getData()['dump'];
			}

			public function createEmptyResult() {
				return [];
			}
		};

		$status = $responses->transformAndGetSingle( $type, 'first' );
		$this->assertTrue( $status->isOK() );
		$this->assertEquals( 'one', $status->getValue() );

		$status = $responses->transformAndGetSingle( $type, 'second' );
		$this->assertTrue( $status->isOK() );
		$this->assertEquals( 'two', $status->getValue() );

		$status = $responses->transformAndGetMulti( $type, [ 'first', 'second', 'third' ] );
		$this->assertTrue( $status->isOK() );
		$this->assertArrayEquals( [ 'first' => 'one', 'second' => 'two' ], $status->getValue(), true );
		$this->assertArrayEquals( [ 'first' => 'one', 'second' => 'two' ], $status->getValue(), false, true );

		$status = $responses->transformAndGetMulti( $type, [ 'first', 'second', 'third' ], 'array_reverse' );
		$this->assertTrue( $status->isOK() );
		$this->assertArrayEquals( [ 'second' => 'two', 'first' => 'one' ], $status->getValue(), true );
		$this->assertArrayEquals( [ 'second' => 'two', 'first' => 'one' ], $status->getValue(), false, true );

		$status = $responses->dumpResults( 'my description' );
		$this->assertTrue( $status->isOK() );
		$this->assertEquals(
			[
				'first' => [
					'description' => 'my description',
					'path' => 'test_one/_search',
					'result' => [ 'dump' => 'one' ],
				],
				'second' => [
					'description' => 'my description',
					'path' => 'test_two/_search',
					'result' => [ 'dump' => 'two' ],
				],
			],
			$status->getValue()
		);
	}
}
