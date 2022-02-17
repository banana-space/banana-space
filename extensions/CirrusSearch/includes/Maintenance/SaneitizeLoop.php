<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\Job\CheckerJob;
use Elastica\Document;
use MWTimestamp;

/**
 * Create saneitize jobs for a single execution of a saneitizer loop
 *
 * Maintains state in the job info pertaining to current position in
 * the loop. The job info must be persisted between runs.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

class SaneitizeLoop {
	/** @var string Name of the saneitizer profile to use in created jobs */
	private $profileName;

	/** @var int The frequency, in seconds, that the saneitize loop is executed */
	private $pushJobFreq;

	/** @var int The number of pages to include per job */
	private $chunkSize;

	/** @var int Minimum number of seconds between loop restarts */
	private $minLoopDuration;

	/** @var callable */
	private $logger;

	/**
	 * @param string $profileName Name of the saneitizer profile to use in created jobs
	 * @param int $pushJobFreq The frequency, in seconds, that the saneitize loop is executed
	 * @param int $chunkSize The number of pages to include per job
	 * @param int $minLoopDuration Minimum number of seconds between loop restarts
	 * @param callable|null $logger Callable accepting 2 arguments, first a log
	 *  message and second either a channel name or null.
	 */
	public function __construct(
		$profileName, $pushJobFreq, $chunkSize, $minLoopDuration, $logger = null
	) {
		$this->profileName = $profileName;
		$this->pushJobFreq = $pushJobFreq;
		$this->chunkSize = $chunkSize;
		$this->minLoopDuration = $minLoopDuration;
		$this->logger = $logger ?? function ( $msg, $channel = null ) {
		};
	}

	/**
	 * Generate jobs for one run of a saneitize loop
	 *
	 * @param Document $jobInfo
	 * @param int $numJobs The number of jobs to create
	 * @param int $minId Minimum page_id on the wiki
	 * @param int $maxId Maximum page_id on the wiki
	 * @return CheckerJob[] The created jobs. May be less than requested.
	 */
	public function run( Document $jobInfo, $numJobs, $minId, $maxId ) {
		// @var int
		$from = $jobInfo->get( 'sanitize_job_id_offset' );
		$lastLoop = $jobInfo->get( 'sanitize_job_last_loop' );
		// ternary is BC for when loop_id didn't exist.
		$loopId = $jobInfo->has( 'sanitize_job_loop_id' ) ? $jobInfo->get( 'sanitize_job_loop_id' ) : 0;
		$jobsSent = $jobInfo->get( 'sanitize_job_jobs_sent' );
		$jobsSentTotal = $jobInfo->get( 'sanitize_job_jobs_sent_total' );
		$idsSent = $jobInfo->get( 'sanitize_job_ids_sent' );
		$idsSentTotal = $jobInfo->get( 'sanitize_job_ids_sent_total' );
		$jobs = [];
		for ( $i = 0; $i < $numJobs; $i++ ) {
			if ( $from <= $minId || $from >= $maxId ) {
				// The previous loop has completed. Wait until that loop
				// has taken the minimum required duration before starting
				// the next one.
				if ( !$this->checkMinLoopDuration( $lastLoop ) ) {
					break;
				}
				$from = $minId;
				$idsSent = 0;
				$jobsSent = 0;
				$lastLoop = MWTimestamp::time();
				$loopId += 1;
			}
			$to = min( $from + $this->chunkSize - 1, $maxId );
			$jobs[] = $this->createCheckerJob( $from, $to, $jobInfo->get( 'sanitize_job_cluster' ), $loopId );
			$jobsSent++;
			$jobsSentTotal++;
			$idsSent += $to - $from;
			$idsSentTotal += $to - $from;
			$from = $to + 1;
		}

		if ( $jobs ) {
			$jobInfo->set( 'sanitize_job_loop_id', $loopId );
			$jobInfo->set( 'sanitize_job_last_loop', $lastLoop );
			$jobInfo->set( 'sanitize_job_id_offset', $from );
			$jobInfo->set( 'sanitize_job_jobs_sent', $jobsSent );
			$jobInfo->set( 'sanitize_job_jobs_sent_total', $jobsSentTotal );
			$jobInfo->set( 'sanitize_job_ids_sent', $idsSent );
			$jobInfo->set( 'sanitize_job_ids_sent_total', $idsSentTotal );
			$this->log( "Created $jobsSent jobs, setting from offset to $from.\n" );
		} else {
			$this->log( "No jobs created.\n" );
		}

		return $jobs;
	}

	/**
	 * @param int $from
	 * @param int $to
	 * @param string|null $cluster
	 * @param int $loopId
	 * @return CheckerJob
	 */
	private function createCheckerJob( $from, $to, $cluster, $loopId ) {
		$delay = mt_rand( 0, $this->pushJobFreq );
		$this->log( "Creating CheckerJob( $from, $to, $delay, {$this->profileName}, $cluster, $loopId )\n" );
		return CheckerJob::build( $from, $to, $delay, $this->profileName, $cluster, $loopId );
	}

	/**
	 * @param int|null $lastLoop last loop start time
	 * @return bool true if minLoopDuration is not reached false otherwize
	 */
	private function checkMinLoopDuration( $lastLoop ) {
		if ( $lastLoop !== null && ( MWTimestamp::time() - $lastLoop ) < $this->minLoopDuration ) {
			$date = date( 'Y-m-d H:i:s', $lastLoop );
			$newLoop = date( 'Y-m-d H:i:s', $lastLoop + $this->minLoopDuration );
			$this->log( "Last loop ended at $date, new jobs will be sent when min_loop_duration is reached at $newLoop\n" );
			return false;
		}
		return true;
	}

	/**
	 * @param string $msg
	 * @param string|null $channel
	 */
	private function log( $msg, $channel = null ) {
		call_user_func( $this->logger, $msg, $channel );
	}
}
