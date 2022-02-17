<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\Job\CheckerJob;

/**
 * @covers \CirrusSearch\Maintenance\SaneitizeLoop
 */
class SaneitizeLoopTest extends CirrusIntegrationTestCase {
	public function loopProvider() {
		return [
			'loop ends, returns nothing, restarts when time passes' => [
				2, // 2 jobs at a time
				[ 'job_info' => [
					'sanitize_job_loop_id' => 0,
				] ],
				[ 'set_time' => 500 ],
				[ 'run' => [
					// loopId, fromPageId, toPageId
					[ 1, 0, 49 ],
					[ 1, 50, 99 ],
				] ],
				[ 'job_info' => [
					'sanitize_job_loop_id' => 1,
				] ],
				[ 'set_time' => 510 ],
				[ 'run' => [
					[ 1, 100, 120 ],
				] ],
				[ 'job_info' => [
					'sanitize_job_loop_id' => 1,
				] ],
				[ 'set_time' => 520 ],
				[ 'run' => [] ],
				[ 'set_time' => 600 ],
				[ 'run' => [
					[ 2, 0, 49 ],
					[ 2, 50, 99 ],
				] ],
				[ 'job_info' => [
					'sanitize_job_loop_id' => 2,
				] ],
			],
			'loop restart time passes while previous loop is running' => [
				2, // 2 jobs at a time
				[ 'set_time' => 500 ],
				[ 'run' => [
					[ 1, 0, 49 ],
					[ 1, 50, 99 ],
				] ],
				[ 'job_info' => [
					'sanitize_job_loop_id' => 1,
				] ],
				[ 'set_time' => 600 ],
				[ 'run' => [
					[ 1, 100, 120 ],
					[ 2, 0, 49 ],
				] ],
				[ 'job_info' => [
					'sanitize_job_loop_id' => 2,
				] ],
			],
			'batches of three with no time overlap' => [
				3, // 3 jobs at a time
				[ 'set_time' => 500 ],
				[ 'run' => [
					[ 1, 0, 49 ],
					[ 1, 50, 99 ],
					[ 1, 100, 120 ],
				] ],
				[ 'job_info' => [
					'sanitize_job_loop_id' => 1,
				] ],
				[ 'set_time' => 510 ],
				[ 'run' => [] ],
				[ 'set_time' => 600 ],
				[ 'run' => [
					[ 2, 0, 49 ],
					[ 2, 50, 99 ],
					[ 2, 100, 120 ],
				] ],
				[ 'job_info' => [
					'sanitize_job_loop_id' => 2,
				] ],
			],
		];
	}

	/**
	 * @dataProvider loopProvider
	 */
	public function testLoop( $numJobs, ...$commands ) {
		$jobInfo = $this->emptyJobInfo();
		$loop = new SaneitizeLoop(
			'phpunit-profile',
			10, // pushJobFreq, 10 seconds (convenient for fake time)
			50, // chunkSize, number of page ids per job
			100 // minLoopDuration, 100 seconds
		);

		$minPageId = 0; // lowest page id on wiki
		$maxPageId = 120; // highest page id on wiki. divisible by lots of things.

		try {
			$oldFakeTime = \MWTimestamp::setFakeTime( 500 );

			foreach ( $commands as $i => $command ) {
				$args = reset( $command );
				try {
					switch ( key( $command ) ) {
					case 'set_time':
						\MWTimestamp::setFakeTime( $args );
						break;
					case 'run':
						$jobs = $loop->run( $jobInfo, $numJobs, $minPageId, $maxPageId );
						$this->assertJobs( $args, $jobs );
						break;
					case 'job_info':
						$this->assertJobInfo( $args, $jobInfo );
						break;
					default:
						throw new \Exception( '...' );
					}
				} catch ( \Exception $e ) {
					$pretty = json_encode( $command, JSON_PRETTY_PRINT );
					throw new \Exception( "In command $i: $pretty", 0, $e );
				}
			}
		} finally {
			\MWTimestamp::setFakeTime( $oldFakeTime );
		}
	}

	private function assertJobs( array $expect, array $jobs ) {
		$this->assertCount( count( $expect ), $jobs, 'number of jobs' );
		// same as python zip(expect, jobs) when same length
		foreach ( array_map( null, $expect, $jobs ) as $pair ) {
			list( $expectJob, $job ) = $pair;
			list( $loopId, $from, $to ) = $expectJob;
			$this->assertInstanceOf( CheckerJob::class, $job );
			$this->assertEquals( 'phpunit-profile', $job->params['profile'], 'profile' );
			$this->assertEquals( 'default', $job->params['cluster'], 'cluster' );
			$this->assertEquals( $loopId, $job->params['loopId'], 'loopId' );
			$this->assertEquals( $from, $job->params['fromPageId'], 'fromPageId' );
			$this->assertEquals( $to, $job->params['toPageId'], 'toPageId' );
		}
	}

	private function assertJobInfo( $expect, \Elastica\Document $jobInfo ) {
		foreach ( $expect as $key => $value ) {
			$this->assertEquals( $value, $jobInfo->get( $key ), "$key=$value" );
		}
	}

	private function emptyJobInfo() {
		return new \Elastica\Document( '', [
			// TODO: Use unique string, but it needs to exist in cluster config
			'sanitize_job_cluster' => 'default',
			'sanitize_job_loop_id' => 0,
			'sanitize_job_last_loop' => 0,
			'sanitize_job_id_offset' => 0,
			'sanitize_job_jobs_sent' => 0,
			'sanitize_job_jobs_sent_total' => 0,
			'sanitize_job_ids_sent' => 0,
			'sanitize_job_ids_sent_total' => 0,
		] );
	}
}
