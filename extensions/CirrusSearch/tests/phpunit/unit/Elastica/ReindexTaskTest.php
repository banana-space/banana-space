<?php

namespace CirrusSearch\Elastica;

use CirrusSearch\CirrusTestCase;
use Elastica\Client;
use Elastica\Request;
use Elastica\Response;

/**
 * This class is a bit fragile. Would be much better to build this into
 * Elastica and use their test framework that sets up a live server to talk to.
 * @covers \CirrusSearch\Elastica\ReindexTask
 */
class ReindexTaskTest extends CirrusTestCase {
	// example status response to in-progress task
	private $inProgressTaskResponse = [
		"completed" => false,
		"task" => [
			"node" => "abc",
			"id" => 123,
			"type" => "transport",
			"action" => "indices:data/write/reindex",
			"status" => [
				"total" => 6154,
				"updated" => 3500,
				"created" => 0,
				"deleted" => 0,
				"batches" => 4,
				"version_conflicts" => 0,
				"noops" => 0,
				"retries" => [
					"bulk" => 0,
					"search" => 0,
				],
				"throttled_millis" => 0,
				"requests_per_second" => -1,
				"throttled_until_millis" => 0,
			],
			"description" => "",
			"start_time_in_millis" => 1486084727030,
			"running_time_in_nanos" => 8136443451,
			"cancellable" => true,
		]
	];

	// example status response to in-progress task with slices
	private $inProgressWithSlicesTaskResponse = [
		"completed" => false,
		"task" => [
			"node" => "abc",
			"id" => 123,
			"type" => "transport",
			"action" => "indices:data/write/reindex",
			"status" => [
				"total" => 0,
				"updated" => 0,
				"created" => 0,
				"deleted" => 0,
				"batches" => 4,
				"version_conflicts" => 0,
				"noops" => 0,
				"retries" => [
					"bulk" => 0,
					"search" => 0,
				],
				"throttled_millis" => 0,
				"requests_per_second" => 0.0,
				"throttled_until_millis" => 0,
				"slices" => [ null, null ],
			],
			"description" => "",
			"start_time_in_millis" => 1486084727030,
			"running_time_in_nanos" => 8136443451,
			"cancellable" => true,
		]
	];

	// example detailed status response for child task
	private $inProgressDetailedSliceStatus = [
		"parent_task_id" => "abc:123",
		"cancellable" => true,
		"node" => "abc",
		"id" => 124,
		"type" => "transport",
		"action" => "indices:data/write/reindex",
		"status" => [
			"slice_id" => 0,
			"total" => 6000,
			"updated" => 2000,
			"created" => 0,
			"deleted" => 0,
			"batches" => 3,
			"version_conflicts" => 0,
			"noops" => 0,
			"retries" => [
				"bulk" => 0,
				"search" => 0
			],
			"throttled_millis" => 0,
			"requests_per_second" => -1.0,
			"throttled_until_millis" => 0
		],
		"description" => "",
		"start_time_in_millis" => 1486086232487,
		"running_time_in_nanos" => 987654321,
	];

	public function testUnslicedTaskBasicStatus() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();

		$client->expects( $this->once() )
			->method( 'request' )
			->with( '_tasks/abc:123', Request::GET )
			->will( $this->returnValue( new Response( json_encode(
				$this->inProgressTaskResponse
			), 200 ) ) );

		$task = new ReindexTask( $client, 'abc:123' );
		$this->assertSame( 'abc:123', $task->getId() );
		$status = $task->getStatus();
		$this->assertInstanceOf( ReindexStatus::class, $status );
		$this->assertFalse( $status->isComplete() );
		$this->assertSame( 6154, $status->getTotal() );
		$this->assertSame( 3500, $status->getUpdated() );
	}

	public function testSlicedTaskBasicStatus() {
		$client = $this->getMockBuilder( Client::class )
			->disableOriginalConstructor()
			->getMock();

		$client->expects( $this->exactly( 2 ) )
			->method( 'request' )
			->will( $this->returnValueMap( [
				[
					'_tasks/abc:123',
					Request::GET,
					[],
					[],
					\Elastica\Request::DEFAULT_CONTENT_TYPE,
					new Response( json_encode(
						$this->inProgressWithSlicesTaskResponse
					), 200 )
				],
				[
					'_tasks',
					Request::GET,
					[],
					[ 'parent_task_id' => 'abc:123', 'detailed' => 'true' ],
					\Elastica\Request::DEFAULT_CONTENT_TYPE,
					new Response( json_encode(
						$this->sliceResponse( 2 )
					), 200 )
				],
			] ) );

		$task = new ReindexTask( $client, 'abc:123' );
		$status = $task->getStatus();
		$this->assertInstanceOf( ReindexStatus::class, $status );
		// Per-task values should have been merged in
		$this->assertSame( 12000, $status->getTotal() );
		$this->assertSame( 6, $status->getBatches() );
		$this->assertSame( 0, $status->getSearchRetries() );
		// requests per second should keep -1, which is a stand in for infinity
		$this->assertSame( -1, $status->getRequestsPerSecond() );
	}

	private function sliceResponse( $num ) {
		$tasks = [];
		for ( $i = 0; $i < $num; ++$i ) {
			$task = $this->inProgressDetailedSliceStatus;
			$task['status']['slice_id'] = $i;
			$tasks[] = $task;
		}

		return [
			'nodes' => [
				'abc' => [
					'tasks' => $tasks
				]
			]
		];
	}
}
