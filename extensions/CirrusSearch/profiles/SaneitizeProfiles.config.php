<?php

/**
 * CirrusSearch - List of sanitization profiles.
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

/**
 * List of sanitization profiles suited for a 2 hours refresh freq.
 * Allows saneitizeJobs to automatically select its settings according to wiki
 * size.
 * The first profile that verifies max(page_id)-min(page_id) < max_wiki_size
 * will be chosen (the array is sorted before applying profile selection)
 */
return [
	// Loop in 9 days for 11k ids, 0.00014 jobs/sec, with 25% ids old/wrong
	// it's 0.0035 updates/sec per cluster
	'XS' => [
		'max_wiki_size' => 12000,
		// Size of the chunk sent per CherckerJob
		'jobs_chunk_size' => 10,
		// number of articles processed in batch by a checker job
		// number of batches is jobs_chunk_size/checker_batch_size
		// A higher value will increase throughput but will also
		// consume more memory on the jobrunners.
		'checker_batch_size' => 10,
		// Max number of update jobs, the checker jobs will hold until the
		// number of pending update jobs decrease below this limit.
		// This value depends on the number of jobrunner availables
		// and the max write throughput you want to put on elastic.
		'max_checker_jobs' => 10,
		// Max number of update jobs, the checker jobs will hold until the
		// number of pending update jobs decrease below this limit.
		// This value depends on the number of jobrunner availables
		// and the max write throughput you want to put on elastic.
		'update_jobs_max_pressure' => 50,
		// Max time in seconds a checker job is allowed to run,
		// the job will reschedule itself at a later time with
		// a new offset it this timeout is reached.
		'checker_job_max_time' => 60,
		// Minimum time to wait between loops in seconds
		// Default: 2 weeks
		// Usefull to not restart a loop too frequently on small wikis
		'min_loop_duration' => 2 * 7 * 24 * 3600,
		// Issue a job to reindex pages every N times it is visited by the
		// checker. This will guarantee the page was indexed with the last
		// actual_loop_duration * reindex_after_loops seconds. A value of 0
		// will disable reindexing old documents. A value of 4 also means
		// that 25% of all documents being checked will be marked old and
		// trigger indexing load (if not noop-ed by elastic).
		// Default: 4 loops, for total of 8 weeks.
		'reindex_after_loops' => 4,
	],
	// Loop in 16 days for 99k ids, 0.006 jobs/sec, with 25% ids old/wrong
	// it's 0.018 updates/sec per cluster
	'S' => [
		'max_wiki_size' => 100000,
		'jobs_chunk_size' => 10,
		'checker_batch_size' => 10,
		'max_checker_jobs' => 50,
		'update_jobs_max_pressure' => 100,
		'checker_job_max_time' => 60,
		'min_loop_duration' => 2 * 7 * 24 * 3600,
		'reindex_after_loops' => 4,
	],
	// Loop in 15 days for 920k ids, 0.06 jobs/sec, with 25% ids old/wrong
	// it's 0.18 updates/sec per cluster
	'M' => [
		'max_wiki_size' => 1000000,
		'jobs_chunk_size' => 10,
		'checker_batch_size' => 10,
		'max_checker_jobs' => 500,
		'update_jobs_max_pressure' => 250,
		'checker_job_max_time' => 60,
		'min_loop_duration' => 2 * 7 * 24 * 3600,
		'reindex_after_loops' => 4,
	],
	// Loop in 17 days for 10m ids, 0.13 jobs/sec, with 25% ids old/wrong
	// it's 1.7 updates/sec per cluster
	'L' => [
		'max_wiki_size' => 12000000,
		'jobs_chunk_size' => 50,
		'checker_batch_size' => 10,
		'max_checker_jobs' => 1000,
		'update_jobs_max_pressure' => 500,
		'checker_job_max_time' => 60,
		'min_loop_duration' => 2 * 7 * 24 * 3600,
		'reindex_after_loops' => 4,
	],
	// Loop in 15 days for 27m ids, 0.20 jobs/sec, with 25% ids old/wrong
	// it's 5.2 updates/sec per cluster
	'XL' => [
		'max_wiki_size' => 30000000,
		'jobs_chunk_size' => 100,
		'checker_batch_size' => 10,
		'max_checker_jobs' => 1500,
		'update_jobs_max_pressure' => 750,
		'checker_job_max_time' => 60,
		'min_loop_duration' => 2 * 7 * 24 * 3600,
		'reindex_after_loops' => 4,
	],
	// Loop in 16 days for 50m ids, 0.34 jobs/sec, with 25% ids old/wrong
	// it's 9 updates/sec per cluster
	'XXL' => [
		'max_wiki_size' => PHP_INT_MAX,
		'jobs_chunk_size' => 100, // ~5sec on terbium
		'checker_batch_size' => 10,
		'max_checker_jobs' => 2500,
		'update_jobs_max_pressure' => 1000,
		'checker_job_max_time' => 60,
		'min_loop_duration' => 2 * 7 * 24 * 3600,
		'reindex_after_loops' => 4,
	],
];
