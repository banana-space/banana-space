<?php
/**
 * Generator of database load balancing objects.
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
 *
 * @file
 * @ingroup Database
 */

namespace Wikimedia\Rdbms;

use BagOStuff;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\WaitConditionLoop;

/**
 * Helper class for mitigating DB replication lag in order to provide "session consistency"
 *
 * This helps to ensure a consistent ordering of events as seen by an client
 *
 * Kind of like Hawking's [[Chronology Protection Agency]].
 *
 * @internal
 */
class ChronologyProtector implements LoggerAwareInterface {
	/** @var BagOStuff */
	protected $store;
	/** @var LoggerInterface */
	protected $logger;

	/** @var string Storage key name */
	protected $key;
	/** @var string Hash of client parameters */
	protected $clientId;
	/** @var string[] Map of client information fields for logging */
	protected $clientLogInfo;
	/** @var int|null Expected minimum index of the last write to the position store */
	protected $waitForPosIndex;
	/** @var int Max seconds to wait on positions to appear */
	protected $waitForPosStoreTimeout = self::POS_STORE_WAIT_TIMEOUT;
	/** @var bool Whether to no-op all method calls */
	protected $enabled = true;
	/** @var bool Whether to check and wait on positions */
	protected $wait = true;

	/** @var bool Whether the client data was loaded */
	protected $initialized = false;
	/** @var DBMasterPos[] Map of (DB master name => position) */
	protected $startupPositions = [];
	/** @var DBMasterPos[] Map of (DB master name => position) */
	protected $shutdownPositions = [];
	/** @var float[] Map of (DB master name => 1) */
	protected $shutdownTouchDBs = [];

	/** @var int Seconds to store positions */
	public const POSITION_TTL = 60;
	/** @var int Seconds to store position write index cookies (safely less than POSITION_TTL) */
	public const POSITION_COOKIE_TTL = 10;
	/** @var int Max time to wait for positions to appear */
	private const POS_STORE_WAIT_TIMEOUT = 5;

	/**
	 * @param BagOStuff $store
	 * @param array $client Map of (ip: <IP>, agent: <user-agent> [, clientId: <hash>] )
	 * @param int|null $posIndex Write counter index
	 * @param string $secret Secret string for HMAC hashing [optional]
	 * @since 1.27
	 */
	public function __construct( BagOStuff $store, array $client, $posIndex, $secret = '' ) {
		$this->store = $store;
		if ( isset( $client['clientId'] ) ) {
			$this->clientId = $client['clientId'];
		} else {
			$this->clientId = ( $secret != '' )
				? hash_hmac( 'md5', $client['ip'] . "\n" . $client['agent'], $secret )
				: md5( $client['ip'] . "\n" . $client['agent'] );
		}
		$this->key = $store->makeGlobalKey( __CLASS__, $this->clientId, 'v2' );
		$this->waitForPosIndex = $posIndex;

		$this->clientLogInfo = [
			'clientIP' => $client['ip'],
			'clientAgent' => $client['agent'],
			'clientId' => $client['clientId'] ?? null
		];

		$this->logger = new NullLogger();
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return string Client ID hash
	 * @since 1.32
	 */
	public function getClientId() {
		return $this->clientId;
	}

	/**
	 * @param bool $enabled Whether to no-op all method calls
	 * @since 1.27
	 */
	public function setEnabled( $enabled ) {
		$this->enabled = $enabled;
	}

	/**
	 * @param bool $enabled Whether to check and wait on positions
	 * @since 1.27
	 */
	public function setWaitEnabled( $enabled ) {
		$this->wait = $enabled;
	}

	/**
	 * Apply the "session consistency" DB replication position to a new ILoadBalancer
	 *
	 * If the stash has a previous master position recorded, this will try to make
	 * sure that the next query to a replica DB of that master will see changes up
	 * to that position by delaying execution. The delay may timeout and allow stale
	 * data if no non-lagged replica DBs are available.
	 *
	 * This method should only be called from LBFactory.
	 *
	 * @param ILoadBalancer $lb
	 * @return void
	 */
	public function applySessionReplicationPosition( ILoadBalancer $lb ) {
		if ( !$this->enabled ) {
			return; // disabled
		}

		$masterName = $lb->getServerName( $lb->getWriterIndex() );
		$startupPositions = $this->getStartupMasterPositions();

		$pos = $startupPositions[$masterName] ?? null;
		if ( $pos instanceof DBMasterPos ) {
			$this->logger->debug( __METHOD__ . ": pos for DB '$masterName' set to '$pos'" );
			$lb->waitFor( $pos );
		}
	}

	/**
	 * Save the "session consistency" DB replication position for an end-of-life ILoadBalancer
	 *
	 * This saves the replication position of the master DB if this request made writes to it.
	 *
	 * This method should only be called from LBFactory.
	 *
	 * @param ILoadBalancer $lb
	 * @return void
	 */
	public function storeSessionReplicationPosition( ILoadBalancer $lb ) {
		if ( !$this->enabled ) {
			return; // disabled
		} elseif ( !$lb->hasOrMadeRecentMasterChanges( INF ) ) {
			// Only save the position if writes have been done on the connection
			return;
		}

		$masterName = $lb->getServerName( $lb->getWriterIndex() );
		if ( $lb->hasStreamingReplicaServers() ) {
			$pos = $lb->getReplicaResumePos();
			if ( $pos ) {
				$this->logger->debug( __METHOD__ . ": LB for '$masterName' has pos $pos" );
				$this->shutdownPositions[$masterName] = $pos;
			}
		} else {
			$this->logger->debug( __METHOD__ . ": DB '$masterName' touched" );
		}
		$this->shutdownTouchDBs[$masterName] = 1;
	}

	/**
	 * Notify the ChronologyProtector that the LBFactory is done calling shutdownLB() for now.
	 * May commit chronology data to persistent storage.
	 *
	 * @param callable|null $workCallback Work to do instead of waiting on syncing positions
	 * @param string $mode One of (sync, async); whether to wait on remote datacenters
	 * @param int|null &$cpIndex DB position key write counter; incremented on update
	 * @return DBMasterPos[] Empty on success; returns the (db name => position) map on failure
	 */
	public function shutdown( callable $workCallback = null, $mode = 'sync', &$cpIndex = null ) {
		if ( !$this->enabled ) {
			return [];
		}

		$store = $this->store;
		// Some callers might want to know if a user recently touched a DB.
		// These writes do not need to block on all datacenters receiving them.
		foreach ( $this->shutdownTouchDBs as $dbName => $unused ) {
			$store->set(
				$this->getTouchedKey( $this->store, $dbName ),
				microtime( true ),
				$store::TTL_DAY
			);
		}

		if ( $this->shutdownPositions === [] ) {
			$this->logger->debug( __METHOD__ . ": no master positions to save" );

			return []; // nothing to save
		}

		$this->logger->debug(
			__METHOD__ . ": saving master pos for " .
			implode( ', ', array_keys( $this->shutdownPositions ) )
		);

		// CP-protected writes should overwhelmingly go to the master datacenter, so merge the
		// positions with a DC-local lock, a DC-local get(), and an all-DC set() with WRITE_SYNC.
		// If set() returns success, then any get() should be able to see the new positions.
		if ( $store->lock( $this->key, 3 ) ) {
			if ( $workCallback ) {
				// Let the store run the work before blocking on a replication sync barrier.
				// If replication caught up while the work finished, the barrier will be fast.
				$store->addBusyCallback( $workCallback );
			}
			$ok = $store->set(
				$this->key,
				$this->mergePositions(
					$store->get( $this->key ),
					$this->shutdownPositions,
					$cpIndex
				),
				self::POSITION_TTL,
				( $mode === 'sync' ) ? $store::WRITE_SYNC : 0
			);
			$store->unlock( $this->key );
		} else {
			$ok = false;
		}

		if ( !$ok ) {
			$cpIndex = null; // nothing saved
			$bouncedPositions = $this->shutdownPositions;
			// Raced out too many times or stash is down
			$this->logger->warning( __METHOD__ . ": failed to save master pos for " .
				implode( ', ', array_keys( $this->shutdownPositions ) )
			);
		} elseif ( $mode === 'sync' &&
			$store->getQoS( $store::ATTR_SYNCWRITES ) < $store::QOS_SYNCWRITES_BE
		) {
			// Positions may not be in all datacenters, force LBFactory to play it safe
			$this->logger->info( __METHOD__ . ": store may not support synchronous writes." );
			$bouncedPositions = $this->shutdownPositions;
		} else {
			$bouncedPositions = [];
		}

		return $bouncedPositions;
	}

	/**
	 * @param ILoadBalancer $lb The load balancer. Prior to 1.35, the first parameter was the
	 *   master name.
	 * @return float|bool UNIX timestamp when client last touched the DB; false if not on record
	 * @since 1.35
	 */
	public function getTouched( ILoadBalancer $lb ) {
		$masterName = $lb->getServerName( $lb->getWriterIndex() );
		return $this->store->get( $this->getTouchedKey( $this->store, $masterName ) );
	}

	/**
	 * @param BagOStuff $store
	 * @param string $masterName
	 * @return string
	 */
	private function getTouchedKey( BagOStuff $store, $masterName ) {
		return $store->makeGlobalKey( __CLASS__, 'mtime', $this->clientId, $masterName );
	}

	/**
	 * Load in previous master positions for the client
	 * @return DBMasterPos[]
	 */
	protected function getStartupMasterPositions() {
		if ( $this->initialized ) {
			return $this->startupPositions;
		}

		$this->initialized = true;
		$this->logger->debug( __METHOD__ . ": client ID is {$this->clientId} (read)" );

		if ( $this->wait ) {
			// If there is an expectation to see master positions from a certain write
			// index or higher, then block until it appears, or until a timeout is reached.
			// Since the write index restarts each time the key is created, it is possible that
			// a lagged store has a matching key write index. However, in that case, it should
			// already be expired and thus treated as non-existing, maintaining correctness.
			if ( $this->waitForPosIndex > 0 ) {
				$data = null;
				$indexReached = null; // highest index reached in the position store
				$loop = new WaitConditionLoop(
					function () use ( &$data, &$indexReached ) {
						$data = $this->store->get( $this->key );
						if ( !is_array( $data ) ) {
							return WaitConditionLoop::CONDITION_CONTINUE; // not found yet
						} elseif ( !isset( $data['writeIndex'] ) ) {
							return WaitConditionLoop::CONDITION_REACHED; // b/c
						}
						$indexReached = max( $data['writeIndex'], $indexReached );

						return ( $data['writeIndex'] >= $this->waitForPosIndex )
							? WaitConditionLoop::CONDITION_REACHED
							: WaitConditionLoop::CONDITION_CONTINUE;
					},
					$this->waitForPosStoreTimeout
				);
				$result = $loop->invoke();
				$waitedMs = $loop->getLastWaitTime() * 1e3;

				if ( $result == $loop::CONDITION_REACHED ) {
					$this->logger->debug(
						__METHOD__ . ": expected and found position index.",
						[
							'cpPosIndex' => $this->waitForPosIndex,
							'waitTimeMs' => $waitedMs
						] + $this->clientLogInfo
					);
				} else {
					$this->logger->warning(
						__METHOD__ . ": expected but failed to find position index.",
						[
							'cpPosIndex' => $this->waitForPosIndex,
							'indexReached' => $indexReached,
							'waitTimeMs' => $waitedMs
						] + $this->clientLogInfo
					);
				}
			} else {
				$data = $this->store->get( $this->key );
			}

			$this->startupPositions = $data ? $data['positions'] : [];
			$this->logger->debug( __METHOD__ . ": key is {$this->key} (read)" );
		} else {
			$this->startupPositions = [];
			$this->logger->debug( __METHOD__ . ": key is {$this->key} (unread)" );
		}

		return $this->startupPositions;
	}

	/**
	 * @param array|bool $curValue
	 * @param DBMasterPos[] $shutdownPositions
	 * @param int|null &$cpIndex
	 * @return array
	 */
	protected function mergePositions( $curValue, array $shutdownPositions, &$cpIndex = null ) {
		/** @var DBMasterPos[] $curPositions */
		$curPositions = $curValue['positions'] ?? [];
		// Use the newest positions for each DB master
		foreach ( $shutdownPositions as $db => $pos ) {
			if (
				!isset( $curPositions[$db] ) ||
				!( $curPositions[$db] instanceof DBMasterPos ) ||
				$pos->asOfTime() > $curPositions[$db]->asOfTime()
			) {
				$curPositions[$db] = $pos;
			}
		}

		$cpIndex = $curValue['writeIndex'] ?? 0;

		return [
			'positions' => $curPositions,
			'writeIndex' => ++$cpIndex
		];
	}
}
