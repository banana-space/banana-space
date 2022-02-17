<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\Connection;
use CirrusSearch\Job\CheckerJob;
use CirrusSearch\MetaStore\MetaSaneitizeJobStore;
use CirrusSearch\MetaStore\MetaStoreIndex;
use CirrusSearch\Profile\SearchProfileService;
use JobQueueGroup;

/**
 * Push some sanitize jobs to the JobQueue
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

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . '/../includes/Maintenance/Maintenance.php';

class SaneitizeJobs extends Maintenance {
	/**
	 * @var MetaSaneitizeJobStore[] all metastores for write clusters
	 */
	private $metaStores;

	/**
	 * @var int min page id (from db)
	 */
	private $minId;

	/**
	 * @var int max page id (from db)
	 */
	private $maxId;

	/**
	 * @var string profile name
	 */
	private $profileName;

	/**
	 * @var string[] list of clusters to check
	 */
	private $clusters;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Manage sanitize jobs (CheckerJob). This ' .
			'script operates on all writable clusters by default. ' .
			'Add --cluster to work on a single cluster. Note that ' .
			'once a job has been pushed to a particular cluster the ' .
			'script will fail if you try to run the same job with ' .
			'different cluster options.'
		);
		$this->addOption( 'push', 'Push some jobs to the job queue.' );
		$this->addOption( 'show', 'Display job info.' );
		$this->addOption( 'delete-job', 'Delete the job.' );
		$this->addOption( 'refresh-freq', 'Refresh rate in seconds this ' .
			'script is run from your crontab. This will be ' .
			'used to spread jobs over time. Defaults to 7200 (2 ' .
			'hours).', false, true );
		$this->addOption( 'job-name', 'Tells the script the name of the ' .
			'sanitize job only useful to run multiple sanitize jobs. ' .
			'Defaults to "default".', false, true );
	}

	public function execute() {
		$this->init();
		if ( $this->hasOption( 'show' ) ) {
			$this->showJobDetail();
		} elseif ( $this->hasOption( 'push' ) ) {
			$this->pushJobs();
		} elseif ( $this->hasOption( 'delete-job' ) ) {
			$this->deleteJob();
		} else {
			$this->maybeHelp( true );
		}

		return true;
	}

	private function init() {
		$this->initClusters();
		$this->initMetaStores();
		$this->initProfile();
	}

	/**
	 * Basically we support two modes:
	 *   - all writable cluster, cluster = null
	 *   - single cluster, cluster = 'clusterName'
	 * If we detect a mismatch here we fail.
	 * @param \Elastica\Document $jobInfo check if the stored job match
	 * cluster config used by this script, will die if clusters mismatch
	 */
	private function checkJobClusterMismatch( \Elastica\Document $jobInfo ) {
		$jobCluster = $jobInfo->get( 'sanitize_job_cluster' );
		$scriptCluster = $this->getOption( 'cluster' );
		if ( $jobCluster != $scriptCluster ) {
			$jobCluster = $jobCluster != null ? $jobCluster : "all writable clusters";
			$scriptCluster = $scriptCluster != null ? $scriptCluster : "all writable clusters";
			$this->fatalError( "Job cluster mismatch, stored job is configured to work on $jobCluster " .
				"but the script is configured to run on $scriptCluster.\n" );
		}
	}

	private function showJobDetail() {
		$profile = $this->getSearchConfig()
			->getProfileService()
			->loadProfileByName( SearchProfileService::SANEITIZER, $this->profileName );
		'@phan-var array $profile';
		$minLoopDuration = $profile['min_loop_duration'];
		$maxJobs = $profile['max_checker_jobs'];
		$maxUpdates = $profile['update_jobs_max_pressure'];

		$jobName = $this->getOption( 'job-name', 'default' );
		$jobInfo = $this->getJobInfo( $jobName );
		if ( $jobInfo === null ) {
			$this->fatalError( "Unknown job $jobName, push some jobs first.\n" );
		}
		$fmt = 'Y-m-d H:i:s';
		$cluster = $jobInfo->get( 'sanitize_job_cluster' ) ?: 'All writable clusters';

		$created = date( $fmt, $jobInfo->get( 'sanitize_job_created' ) );
		$updated = date( $fmt, $jobInfo->get( 'sanitize_job_updated' ) );
		$loopStart = date( $fmt, $jobInfo->get( 'sanitize_job_last_loop' ) );

		$idsSent = $jobInfo->get( 'sanitize_job_ids_sent' );
		$idsSentTotal = $jobInfo->get( 'sanitize_job_ids_sent_total' );

		$jobsSent = $jobInfo->get( 'sanitize_job_jobs_sent' );
		$jobsSentTotal = $jobInfo->get( 'sanitize_job_jobs_sent_total' );

		$updatePressure = CheckerJob::getPressure();

		$loopTime = time() - $jobInfo->get( 'sanitize_job_last_loop' );
		$totalTime = time() - $jobInfo->get( 'sanitize_job_created' );

		$jobsRate = $jobInfo->get( 'sanitize_job_jobs_sent' ) / $loopTime;
		$jobsPerHour = round( $jobsRate * 3600, 2 );
		$jobsPerDay = round( $jobsRate * 3600 * 24, 2 );
		$jobsRateTotal = $jobInfo->get( 'sanitize_job_jobs_sent_total' ) / $totalTime;
		$jobsTotalPerHour = round( $jobsRateTotal * 3600, 2 );
		$jobsTotalPerDay = round( $jobsRateTotal * 3600 * 24, 2 );

		$idsRate = $jobInfo->get( 'sanitize_job_ids_sent' ) / $loopTime;
		$idsPerHour = round( $idsRate * 3600, 2 );
		$idsPerDay = round( $idsRate * 3600 * 24, 2 );
		$idsRateTotal = $jobInfo->get( 'sanitize_job_ids_sent_total' ) / $totalTime;
		$idsTotalPerHour = round( $idsRateTotal * 3600, 2 );
		$idsTotalPerDay = round( $idsRateTotal * 3600 * 24, 2 );

		$loopId = $jobInfo->has( 'sanitize_job_loop_id' ) ? $jobInfo->get( 'sanitize_job_loop_id' ) : 0;
		$idsTodo = $this->maxId - $jobInfo->get( 'sanitize_job_id_offset' );
		$loopEta = date( $fmt, time() + ( $idsTodo * $jobsRate ) );
		$loopRestartMinTime = date( $fmt, $jobInfo->get( 'sanitize_job_last_loop' ) + $minLoopDuration );

		$this->output( <<<EOD
JobDetail for {$jobName}
	Target Wiki: 	{$jobInfo->get( 'sanitize_job_wiki' )}
	Cluster: 	{$cluster}
	Created: 	{$created}
	Updated: 	{$updated}
	Loop start:	{$loopStart}
	Current id:	{$jobInfo->get( 'sanitize_job_id_offset' )}
	Ids sent:	{$idsSent} ({$idsSentTotal} total)
	Jobs sent:	{$jobsSent} ({$jobsSentTotal} total)
	Pressure (CheckerJobs):
		Cur:	{$this->getPressure()} jobs
		Max:	{$maxJobs} jobs
	Pressure (Updates):
		Cur:	{$updatePressure} jobs
		Max:	{$maxUpdates} jobs
	Jobs rate:
		Loop:	{$jobsPerHour} jobs/hour, {$jobsPerDay} jobs/day
		Total:	{$jobsTotalPerHour} jobs/hour, {$jobsTotalPerDay} jobs/day
	Ids rate :
		Loop:	{$idsPerHour} ids/hour, {$idsPerDay} ids/day
		Total:	{$idsTotalPerHour} ids/hour, {$idsTotalPerDay} ids/day
	Loop:
		Loop:	{$loopId}
		Todo:	{$idsTodo} ids
		ETA:	{$loopEta}
	Loop restart min time: {$loopRestartMinTime}

EOD
		);
	}

	private function pushJobs() {
		$profile = $this->getSearchConfig()
			->getProfileService()
			->loadProfileByName( SearchProfileService::SANEITIZER, $this->profileName );
		'@phan-var array $profile';
		$maxJobs = $profile['max_checker_jobs'];
		if ( !$maxJobs || $maxJobs <= 0 ) {
			$this->fatalError( "max_checker_jobs invalid abandonning.\n" );
		}

		$pressure = $this->getPressure();
		if ( $pressure >= $maxJobs ) {
			$this->fatalError( "Too many CheckerJob: $pressure in the queue, $maxJobs allowed.\n" );
		}
		$this->log( "$pressure checker job(s) in the queue.\n" );

		$this->disablePoolCountersAndLogging();
		$this->initMetaStores();

		$jobName = $this->getOption( 'job-name', 'default' );
		$jobInfo = $this->getJobInfo( $jobName );
		if ( $jobInfo === null ) {
			$jobInfo = $this->createNewJob( $jobName );
		}

		$pushJobFreq = $this->getOption( 'refresh-freq', 2 * 3600 );
		$loop = new SaneitizeLoop(
			$this->profileName,
			$pushJobFreq,
			$profile['jobs_chunk_size'],
			$profile['min_loop_duration'],
			function ( $msg, $channel ) {
				$this->log( $msg, $channel );
			} );
		$jobs = $loop->run( $jobInfo, $maxJobs, $this->minId, $this->maxId );
		if ( $jobs ) {
			// Some job queues implementations ignore the timestamps and
			// instead run these jobs with concurrency limits to keep them
			// spread over time. Insert jobs in the order we asked for them
			// to be run to have some semblance of sanity.
			usort( $jobs, function ( CheckerJob $job1, CheckerJob $job2 ) {
				return $job1->getReadyTimestamp() - $job2->getReleaseTimestamp();
			} );
			JobQueueGroup::singleton()->push( $jobs );
			$this->updateJob( $jobInfo );
		}
	}

	private function initClusters() {
		$sanityCheckSetup = $this->getSearchConfig()->get( 'CirrusSearchSanityCheck' );
		if ( !$sanityCheckSetup ) {
			$this->fatalError( "Sanity check disabled, abandonning...\n" );
		}
		$assignment = $this->getSearchConfig()->getClusterAssignment();
		if ( $this->hasOption( 'cluster' ) ) {
			$cluster = $this->getOption( 'cluster' );
			if ( $assignment->canWriteToCluster( $cluster ) ) {
				$this->fatalError( "$cluster is not in the set of writable clusters\n" );
			}
			$this->clusters = [ $this->getOption( 'cluster' ) ];
		}
		$this->clusters = $assignment->getWritableClusters();
		if ( count( $this->clusters ) === 0 ) {
			$this->fatalError( 'No clusters are writable...' );
		}
	}

	private function initMetaStores() {
		$connections = Connection::getClusterConnections( $this->clusters, $this->getSearchConfig() );

		if ( empty( $connections ) ) {
			$this->fatalError( "No writable cluster found." );
		}

		$this->metaStores = [];
		foreach ( $connections as $cluster => $connection ) {
			if ( !MetaStoreIndex::cirrusReady( $connection ) ) {
				$this->fatalError( "No metastore found in cluster $cluster" );
			}
			$store = new MetaStoreIndex( $connection, $this, $this->getSearchConfig() );
			if ( !$store->versionIsAtLeast( [ 1, 0 ] ) ) {
				$this->fatalError( 'Metastore version is too old, expected at least 1.0' );
			}
			$this->metaStores[$cluster] = $store->saneitizeJobStore();
		}
	}

	private function initProfile() {
		$res =
			$this->getDB( DB_REPLICA )
				->select( 'page', [ 'MIN(page_id) as min_id', 'MAX(page_id) as max_id' ], [], __METHOD__ );
		$row = $res->next();
		$this->minId = $row->min_id;
		$this->maxId = $row->max_id;
		$profiles =
			$this->getSearchConfig()
				->getProfileService()
				->listExposedProfiles( SearchProfileService::SANEITIZER );
		uasort( $profiles, function ( $a, $b ) {
			return $a['max_wiki_size'] <=> $b['max_wiki_size'];
		} );
		$wikiSize = $this->maxId - $this->minId;
		foreach ( $profiles as $name => $settings ) {
			'@phan-var array $settings';
			if ( $settings['max_wiki_size'] > $wikiSize ) {
				$this->profileName = $name;
				$this->log( "Detected $wikiSize ids to check, selecting profile $name\n" );
				break;
			}
		}
		if ( !$this->profileName ) {
			$this->fatalError( "No profile found for $wikiSize ids, please check sanitization profiles" );
		}
	}

	/**
	 * @param string $jobName job name.
	 * @return \Elastica\Document|null
	 */
	private function getJobInfo( $jobName ) {
		$latest = null;
		// Fetch the lastest jobInfo from the metastore. Ideally all
		// jobInfo should be the same but in the case a cluster has
		// been decommissioned and re-added its job info may be outdated
		foreach ( $this->metaStores as $store ) {
			$current = $store->get( $jobName );
			if ( $current === null ) {
				continue;
			}
			$this->checkJobClusterMismatch( $current );
			if ( $latest == null ) {
				$latest = $current;
			} elseif ( $current->get( 'sanitize_job_updated' ) > $latest->get( 'sanitize_job_updated' ) ) {
				$latest = $current;
			}
		}
		return $latest;
	}

	/**
	 * @param \Elastica\Document $jobInfo
	 */
	private function updateJob( \Elastica\Document $jobInfo ) {
		foreach ( $this->metaStores as $store ) {
			$store->update( $jobInfo );
		}
	}

	/**
	 * @param string $jobName
	 * @return \Elastica\Document
	 */
	private function createNewJob( $jobName ) {
		$job = null;
		$scriptCluster = $this->getOption( 'cluster' );
		foreach ( $this->metaStores as $store ) {
			// TODO: It's a little awkward to let each cluster make
			// it's own job, but it also seems sane to put all
			// the doc building in the store?
			$job = $store->create( $jobName, $this->minId, $scriptCluster );
		}
		if ( $job === null ) {
			$this->fatalError( "No job created, metastores failed to create?" );
		}
		// @phan-suppress-next-line PhanTypeMismatchReturnNullable T240141
		return $job;
	}

	private function deleteJob() {
		$jobName = $this->getOption( 'job-name', 'default' );
		$jobInfo = $this->getJobInfo( $jobName );
		if ( $jobInfo === null ) {
			$this->fatalError( "Unknown job $jobName" );
		}
		foreach ( $this->metaStores as $cluster => $store ) {
			$store->delete( $jobName );
			$this->log( "Deleted job $jobName from $cluster.\n" );
		}
	}

	/**
	 * @return int the number of jobs in the CheckerJob queue
	 */
	private function getPressure() {
		$queue = JobQueueGroup::singleton()->get( 'cirrusSearchCheckerJob' );
		return $queue->getSize() + $queue->getDelayedCount();
	}

	private function log( $msg, $channel = null ) {
		$date = new \DateTime();
		$this->output( $date->format( 'Y-m-d H:i:s' ) . " " . $msg, $channel );
	}

	/**
	 * @param string $msg The error to display
	 * @param int $die deprecated do not use
	 */
	public function error( $msg, $die = 0 ) {
		$date = new \DateTime();
		parent::error( $date->format( 'Y-m-d H:i:s' ) . " " . $msg );
	}

	/**
	 * @param string $msg The error to display
	 * @param int $exitCode die out using this int as the code
	 */
	public function fatalError( $msg, $exitCode = 1 ) {
		$date = new \DateTime();
		parent::fatalError( $date->format( 'Y-m-d H:i:s' ) . " " . $msg, $exitCode );
	}
}

$maintClass = SaneitizeJobs::class;
require_once RUN_MAINTENANCE_IF_MAIN;
