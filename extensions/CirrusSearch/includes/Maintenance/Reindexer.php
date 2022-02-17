<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\Connection;
use CirrusSearch\Elastica\ReindexRequest;
use CirrusSearch\Elastica\ReindexResponse;
use CirrusSearch\Elastica\ReindexTask;
use CirrusSearch\SearchConfig;
use Elastica\Exception\Connection\HttpException;
use Elastica\Index;
use Elastica\Request;
use Elastica\Transport\Http;
use Elastica\Transport\Https;
use Elastica\Type;

/**
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
class Reindexer {
	const MAX_CONSECUTIVE_ERRORS = 5;
	const MONITOR_SLEEP_SECONDS = 30;
	const MAX_WAIT_FOR_COUNT_SEC = 600;

	/**
	 * @var SearchConfig
	 */
	private $searchConfig;

	/* "From" portion */
	/**
	 * @var Index
	 */
	private $oldIndex;

	/**
	 * @var Connection
	 */
	private $oldConnection;

	/* "To" portion */

	/**
	 * @var Index
	 */
	private $index;

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * @var Type
	 */
	private $type;

	/**
	 * @var Type
	 */
	private $oldType;

	/**
	 * @var int
	 */
	private $shardCount;

	/**
	 * @var string
	 */
	private $replicaCount;

	/**
	 * @var array
	 */
	private $mergeSettings;

	/**
	 * @var Printer
	 */
	private $out;

	/**
	 * @var string[] list of fields to delete
	 */
	private $fieldsToDelete;

	/**
	 * @param SearchConfig $searchConfig
	 * @param Connection $source
	 * @param Connection $target
	 * @param Type $type
	 * @param Type $oldType
	 * @param int $shardCount
	 * @param string $replicaCount
	 * @param array $mergeSettings
	 * @param Printer|null $out
	 * @param string[] $fieldsToDelete
	 * @throws \Exception
	 */
	public function __construct(
		SearchConfig $searchConfig,
		Connection $source,
		Connection $target,
		Type $type,
		Type $oldType,
		$shardCount,
		$replicaCount,
		array $mergeSettings,
		Printer $out = null,
		$fieldsToDelete = []
	) {
		// @todo: this constructor has too many arguments - refactor!
		$this->searchConfig = $searchConfig;
		$this->oldConnection = $source;
		$this->connection = $target;
		$this->type = $type;
		$this->oldType = $oldType;
		$this->shardCount = $shardCount;
		$this->replicaCount = $replicaCount;
		$this->mergeSettings = $mergeSettings;
		$this->out = $out;
		$this->fieldsToDelete = $fieldsToDelete;
		$this->index = $type->getIndex();
		$this->oldIndex = $oldType->getIndex();
	}

	/**
	 * Dump everything from the live index into the one being worked on.
	 *
	 * @param int|null $slices The number of slices to use, or null to use
	 *  the number of shards
	 * @param int $refreshInterval
	 * @param int $chunkSize
	 * @param float $acceptableCountDeviation
	 */
	public function reindex(
		$slices = null,
		$refreshInterval = 1,
		$chunkSize = 100,
		$acceptableCountDeviation = 0.05
	) {
		// Set some settings that should help io load during bulk indexing.  We'll have to
		// optimize after this to consolidate down to a proper number of segments but that is
		// is worth the price.  total_shards_per_node will help to make sure that each shard
		// has as few neighbors as possible.
		$this->setConnectionTimeout();
		$settings = $this->index->getSettings();
		$maxShardsPerNode = $this->decideMaxShardsPerNodeForReindex();
		$settings->set( [
			'refresh_interval' => -1,
			'routing.allocation.total_shards_per_node' => $maxShardsPerNode,
		] );

		$request = new ReindexRequest( $this->oldType, $this->type, $chunkSize );
		if ( $slices === null ) {
			$request->setSlices( $this->getNumberOfShards( $this->oldType->getIndex() ) );
		} else {
			$request->setSlices( $slices );
		}
		$remote = self::makeRemoteReindexInfo( $this->oldConnection, $this->connection );
		if ( $remote !== null ) {
			$request->setRemoteInfo( $remote );
		}
		$script = $this->makeDeleteFieldsScript();
		if ( $script !== null ) {
			$request->setScript( $script );
		}

		try {
			$task = $request->reindexTask();
		} catch ( \Exception $e ) {
			$this->fatalError( $e->getMessage() );
		}

		$this->out->outputIndented( "Started reindex task: " . $task->getId() . "\n" );
		$response = $this->monitorReindexTask( $task, $this->type );
		$task->delete();
		if ( !$response->isSuccessful() ) {
			$this->fatalError(
				"Reindex task was not successfull: " . $response->getUnsuccessfulReason()
			);
		}

		$this->outputIndented( "Verifying counts..." );
		// We can't verify counts are exactly equal because they won't be - we still push updates
		// into the old index while reindexing the new one.
		$this->waitForCounts( $acceptableCountDeviation );
		$this->output( "done\n" );

		// Revert settings changed just for reindexing
		$newSettings = [ 'refresh_interval' => $refreshInterval . 's' ];
		if ( $this->mergeSettings ) {
			$newSettings['merge.policy'] = $this->mergeSettings;
		}
		$settings->set( $newSettings );
	}

	/**
	 * @param float $acceptableCountDeviation
	 */
	private function waitForCounts( float $acceptableCountDeviation ) {
		$oldCount = (float)$this->oldType->count();
		$this->index->refresh();
		// While elasticsearch should be ready immediately after a refresh, we have seen this return
		// exceptionally low values in 2% of reindex attempts. Wait around a bit and hope the refresh
		// becomes available
		$start = microtime( true );
		$timeoutAfter = $start + self::MAX_WAIT_FOR_COUNT_SEC;
		while ( true ) {
			$newCount = (float)$this->type->count();
			$difference = $oldCount > 0 ? abs( $oldCount - $newCount ) / $oldCount : 0;
			if ( $difference <= $acceptableCountDeviation ) {
				break;
			}
			$this->output(
				"Not close enough!  old=$oldCount new=$newCount difference=$difference\n"
			);
			if ( microtime( true ) > $timeoutAfter ) {
				$this->fatalError( 'Failed to load index - counts not close enough.  ' .
					"old=$oldCount new=$newCount difference=$difference.  " .
					'Check for warnings above.' );
			} else {
				$this->output( "Waiting to re-check counts..." );
				sleep( 30 );
			}
		}
	}

	public function waitForShards() {
		if ( !$this->replicaCount || $this->replicaCount === "false" ) {
			$this->outputIndented( "\tNo replicas, skipping.\n" );
			return;
		}
		$this->outputIndented( "\tWaiting for all shards to start...\n" );
		list( $lower, $upper ) = explode( '-', $this->replicaCount );
		$each = 0;
		while ( true ) {
			$health = $this->getHealth();
			$active = $health[ 'active_shards' ];
			$relocating = $health[ 'relocating_shards' ];
			$initializing = $health[ 'initializing_shards' ];
			$unassigned = $health[ 'unassigned_shards' ];
			$nodes = $health[ 'number_of_nodes' ];
			if ( $nodes < $lower ) {
				$this->fatalError( "Require $lower replicas but only have $nodes nodes. "
					. "This is almost always due to misconfiguration, aborting." );
			}
			// If the upper range is all, expect the upper bound to be the number of nodes
			if ( $upper === 'all' ) {
				$upper = $nodes - 1;
			}
			$expectedReplicas = min( max( $nodes - 1, $lower ), $upper );
			$expectedActive = $this->shardCount * ( 1 + $expectedReplicas );
			if ( $each === 0 || $active === $expectedActive ) {
				$this->outputIndented( "\t\tactive:$active/$expectedActive relocating:$relocating" .
					" initializing:$initializing unassigned:$unassigned\n" );
				if ( $active === $expectedActive ) {
					break;
				}
			}
			$each = ( $each + 1 ) % 20;
			sleep( 1 );
		}
	}

	/**
	 * Get health information about the index
	 *
	 * @return array Response data array
	 */
	private function getHealth() {
		while ( true ) {
			$indexName = $this->index->getName();
			$path = "_cluster/health/$indexName";
			$response = $this->index->getClient()->request( $path );
			if ( $response->hasError() ) {
				$this->error( 'Error fetching index health but going to retry.  Message: ' .
					$response->getError() );
				sleep( 1 );
				continue;
			}
			return $response->getData();
		}
	}

	/**
	 * @return int
	 */
	private function decideMaxShardsPerNodeForReindex() {
		$health = $this->getHealth();
		$totalNodes = $health[ 'number_of_nodes' ];
		$totalShards = $this->shardCount * ( $this->getMaxReplicaCount() + 1 );
		return (int)ceil( 1.0 * $totalShards / $totalNodes );
	}

	/**
	 * @return int
	 */
	private function getMaxReplicaCount() {
		$replica = explode( '-', $this->replicaCount );
		return (int)end( $replica );
	}

	/**
	 * Set the maintenance timeout to the connection we will issue the reindex request
	 * to, so that it does not timeout will the reindex is running.
	 */
	private function setConnectionTimeout() {
		$timeout = $this->searchConfig->get( 'CirrusSearchMaintenanceTimeout' );
		$this->connection->setTimeout( $timeout );
	}

	/**
	 * Destroy client connections
	 */
	private function destroyClients() {
		$this->connection->destroyClient();
		$this->oldConnection->destroyClient();
		// Destroying connections resets timeouts, so we have to reinstate them
		$this->setConnectionTimeout();
	}

	/**
	 * @param string $message
	 * @param mixed|null $channel
	 */
	protected function output( $message, $channel = null ) {
		if ( $this->out ) {
			$this->out->output( $message, $channel );
		}
	}

	/**
	 * @param string $message
	 */
	private function outputIndented( $message ) {
		if ( $this->out ) {
			$this->out->outputIndented( $message );
		}
	}

	/**
	 * @param string $message
	 */
	private function error( $message ) {
		if ( $this->out ) {
			$this->out->error( $message );
		}
	}

	/**
	 * @param string $message
	 * @param int $exitCode
	 */
	private function fatalError( $message, $exitCode = 1 ) {
		$this->error( $message );
		exit( $exitCode );
	}

	/**
	 * @return array|null Returns an array suitable for use as
	 *  the _reindex api script parameter to delete fields from
	 *  the copied documents, or null if no script is needed.
	 */
	private function makeDeleteFieldsScript() {
		if ( !$this->fieldsToDelete ) {
			return null;
		}

		$script = [
			'source' => '',
			'lang' => 'painless',
		];
		foreach ( $this->fieldsToDelete as $field ) {
			$field = trim( $field );
			if ( strlen( $field ) ) {
				$script['source'] .= "ctx._source.remove('$field');";
			}
		}
		if ( $script['source'] === '' ) {
			return null;
		}

		return $script;
	}

	/**
	 * Creates an array suitable for use as the _reindex api source.remote
	 * parameter to read from $oldConnection.
	 *
	 * This is very fragile, but the transports don't expose enough to do more really
	 *
	 * @param Connection $source Connection to read data from
	 * @param Connection $dest Connection to reindex data into
	 * @return array|null
	 */
	public static function makeRemoteReindexInfo( Connection $source, Connection $dest ) {
		if ( $source->getClusterName() === $dest->getClusterName() ) {
			return null;
		}

		$innerConnection = $source->getClient()->getConnection();
		$transport = $innerConnection->getTransportObject();
		if ( !$transport instanceof Http ) {
			throw new \RuntimeException(
				'Remote reindex not implemented for transport: ' . get_class( $transport )
			);
		}

		// We make some pretty bold assumptions that classes extending from \Elastica\Transport\Http
		// don't change how any of this works.
		$url = $innerConnection->hasConfig( 'url' )
			? $innerConnection->getConfig( 'url' )
			: '';
		if ( empty( $url ) ) {
			$scheme = ( $transport instanceof Https )
				? 'https'
				: 'http';
			$url = $scheme . '://' . $innerConnection->getHost() . ':' .
				$innerConnection->getPort() . '/' . $innerConnection->getPath();
		}

		if ( $innerConnection->getUsername() && $innerConnection->getPassword() ) {
			return [
				'host' => $url,
				'username' => $innerConnection->getUsername(),
				'password' => $innerConnection->getPassword(),
			];
		} else {
			return [ 'host' => $url ];
		}
	}

	/**
	 * @param ReindexTask $task
	 * @param Type $target
	 * @return ReindexResponse
	 */
	private function monitorReindexTask( ReindexTask $task, Type $target ) {
		$consecutiveErrors = 0;
		$sleepSeconds = self::monitorSleepSeconds( 1, 2, self::MONITOR_SLEEP_SECONDS );
		while ( !$task->isComplete() ) {
			try {
				$status = $task->getStatus();
			} catch ( \Exception $e ) {
				if ( ++$consecutiveErrors > self::MAX_CONSECUTIVE_ERRORS ) {
					$this->out->outputIndented( "\n" );
					$this->fatalError(
						"$e\n\n" .
						"Lost connection to elasticsearch cluster. The reindex task "
						. "{$task->getId()} is still running.\nThe task should be manually "
						. "canceled, and the index {$target->getIndex()->getName()}\n"
						. "should be removed.\n" .
						$e->getMessage()
					);
				}
				if ( $e instanceof HttpException ) {
					// Allow through potentially intermittent network problems:
					// * couldn't connect,
					// * 28: timeout out
					// * 52: connected, closed with no response
					if ( !in_array( $e->getError(), [ CURLE_COULDNT_CONNECT, 28, 52 ] ) ) {
						// Wrap exception to include info about task id?
						throw $e;
					}
				}
				$this->out->outputIndented( "Error: {$e->getMessage()}\n" );
				usleep( 500000 );
				continue;
			}

			$consecutiveErrors = 0;

			// What is worth reporting here?
			$this->out->outputIndented(
				"Task: {$task->getId()} "
				. "Search Retries: {$status->getSearchRetries()} "
				. "Bulk Retries: {$status->getBulkRetries()} "
				. "Indexed: {$status->getCreated()} / {$status->getTotal()}\n"
			);
			if ( !$status->isComplete() ) {
				sleep( $sleepSeconds->current() );
				$sleepSeconds->next();
			}
		}

		return $task->getResponse();
	}

	private static function monitorSleepSeconds( $base, $ratio, $max ) {
		$val = $base;
		// @phan-suppress-next-line PhanInfiniteLoop https://github.com/phan/phan/issues/3545
		while ( true ) {
			yield $val;
			$val = min( $max, $val * $ratio );
		}
	}

	private function getNumberOfShards( Index $index ) {
		$response = $index->request( '_settings/index.number_of_shards', Request::GET );
		$data = $response->getData();
		// Can't use $index->getName() because that is probably an alias
		$realIndexName = array_keys( $data )[0];
		// In theory this should never happen, we will get a ResponseException if the index doesn't
		// exist and every index must have a number_of_shards settings. But better safe than sorry.
		if ( !isset( $data[$realIndexName]['settings']['index']['number_of_shards'] ) ) {
			throw new \RuntimeException(
				"Couldn't detect number of shards in {$index->getName()}"
			);
		}
		return $data[$realIndexName]['settings']['index']['number_of_shards'];
	}
}
