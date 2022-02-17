<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\MetaStore\MetaStoreIndex;
use CirrusSearch\MetaStore\MetaVersionStore;

/**
 * Check that all Cirrus indexes report OK.
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

class CheckIndexes extends Maintenance {
	/**
	 * @var array[] Nested array of arrays containing error strings. Individual
	 *  errors are nested based on the keys in self::$path at the time the error
	 *  occurred.
	 */
	private $errors = [];
	/**
	 * @var string[] Represents each step of current indentation level
	 */
	private $path;
	/**
	 * @var array Result of querying elasticsearch _cluster/state api endpoint
	 */
	private $clusterState;
	/**
	 * @var array[] Version info stored in elasticsearch /mw_cirrus_versions/version
	 */
	private $cirrusInfo;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Check that all Cirrus indexes report OK. This always operates on ' .
			'a single cluster.' );

		$this->addOption( 'nagios', 'Output in nagios format' );
	}

	public function execute() {
		if ( $this->hasOption( 'nagios' ) ) {
			// Force silent running mode so we can match Nagios expected output.
			$this->mQuiet = true;
		}
		$this->ensureClusterStateFetched();
		$this->ensureCirrusInfoFetched();
		// @todo: use MetaStoreIndex
		$aliases = [];
		foreach ( $this->clusterState[ 'metadata' ][ 'indices' ] as $indexName => $data ) {
			foreach ( $data[ 'aliases' ] as $alias ) {
				$aliases[ $alias ][] = $indexName;
			}
		}
		$this->checkMetastore( $aliases );
		foreach ( $this->cirrusInfo as $alias => $data ) {
			foreach ( $aliases[ $alias ] as $indexName ) {
				$this->checkIndex( $indexName, $data[ 'shard_count'] );
			}
		}
		$indexCount = count( $this->cirrusInfo );
		$errCount = count( $this->errors );
		if ( $this->hasOption( 'nagios' ) ) {
			// Exit silent running mode so we can log Nagios style output
			$this->mQuiet = false;
			if ( $errCount > 0 ) {
				$this->output( "CIRRUSSEARCH CRITICAL - $indexCount indexes report $errCount errors\n" );
			} else {
				$this->output( "CIRRUSSEARCH OK - $indexCount indexes report 0 errors\n" );
			}
		}
		$this->printErrorRecursive( '', $this->errors );
		// If there are error use the nagios error codes to signal them
		if ( $errCount > 0 ) {
			die( 2 );
		}

		return true;
	}

	private function checkMetastore( array $aliases ) {
		$this->in( MetaStoreIndex::INDEX_NAME );
		if ( isset( $aliases[ MetaStoreIndex::INDEX_NAME ] ) ) {
			$this->check( 'alias count', 1, count( $aliases[ MetaStoreIndex::INDEX_NAME ] ) );
			foreach ( $aliases[ MetaStoreIndex::INDEX_NAME ] as $indexName ) {
				$this->checkIndex( $indexName, 1 );
			}
		} else {
			$this->err( 'does not exist' );
		}
		$this->out();
	}

	/**
	 * @param string $indexName
	 * @param int $expectedShardCount
	 */
	private function checkIndex( $indexName, $expectedShardCount ) {
		$metadata = $this->getIndexMetadata( $indexName );
		$this->in( $indexName );
		if ( $metadata === null ) {
			$this->err( 'does not exist' );
			$this->out();
			return;
		}
		$this->check( 'state', 'open', $metadata[ 'state' ] );
		// TODO check aliases

		$routingTable = $this->getIndexRoutingTable( $indexName );
		$this->check( 'shard count', $expectedShardCount, count( $routingTable[ 'shards' ] ) );
		foreach ( $routingTable[ 'shards' ] as $shardIndex => $shardRoutingTable ) {
			$this->in( "shard $shardIndex" );
			foreach ( $shardRoutingTable as $replicaIndex => $replica ) {
				$this->in( "replica $replicaIndex" );
				$this->check( 'state', [ 'STARTED', 'RELOCATING' ], $replica[ 'state' ] );
				$this->out();
			}
			$this->out();
		}
		$this->out();
	}

	/**
	 * @param string $header
	 */
	private function in( $header ) {
		$this->path[] = $header;
		$this->output( str_repeat( "\t", count( $this->path ) - 1 ) );
		$this->output( "$header...\n" );
	}

	private function out() {
		array_pop( $this->path );
	}

	/**
	 * @param string $name
	 * @param mixed $expected
	 * @param mixed $actual
	 */
	private function check( $name, $expected, $actual ) {
		$this->output( str_repeat( "\t", count( $this->path ) ) );
		$this->output( "$name..." );
		if ( is_array( $expected ) ) {
			if ( in_array( $actual, $expected ) ) {
				$this->output( "ok\n" );
			} else {
				$expectedStr = implode( ', ', $expected );
				$this->output( "$actual not in [$expectedStr]\n" );
				$this->err( "expected $name to be in [$expectedStr] but was $actual" );
			}
		} else {
			if ( $expected === $actual ) {
				$this->output( "ok\n" );
			} else {
				$this->output( "$expected != $actual\n" );
				$this->err( "expected $name to be '$expected' but was '$actual'" );
			}
		}
	}

	/**
	 * @param string $explanation
	 */
	private function err( $explanation ) {
		$e = &$this->errors;
		foreach ( $this->path as $element ) {
			$e = &$e[ $element ];
		}
		$e[] = $explanation;
	}

	/**
	 * @param string $indent Prefix to attach before each line of output
	 * @param array $array
	 */
	private function printErrorRecursive( $indent, array $array ) {
		foreach ( $array as $key => $value ) {
			$line = $indent;
			if ( !is_numeric( $key ) ) {
				$line .= "$key...";
			}
			if ( is_array( $value ) ) {
				$this->error( $line );
				$this->printErrorRecursive( "$indent\t", $value );
			} else {
				$line .= $value;
				if ( $this->hasOption( 'nagios' ) ) {
					$this->output( "$line\n" );
				} else {
					$this->error( $line );
				}
			}
		}
	}

	/**
	 * @param string $indexName fully qualified name of elasticsearch index
	 * @return array|null Index metadata from elasticsearch cluster state
	 */
	private function getIndexMetadata( $indexName ) {
		return $this->clusterState['metadata']['indices'][$indexName] ?? null;
	}

	/**
	 * @param string $indexName fully qualified name of elasticsearch index
	 * @return array
	 */
	private function getIndexRoutingTable( $indexName ) {
		return $this->clusterState[ 'routing_table' ][ 'indices' ][ $indexName ];
	}

	private function ensureClusterStateFetched() {
		if ( $this->clusterState === null ) {
			$this->clusterState = $this->getConnection()->getClient()
				->request( '_cluster/state' )->getData();
		}
	}

	private function ensureCirrusInfoFetched() {
		if ( $this->cirrusInfo === null ) {
			$store = new MetaVersionStore( $this->getConnection() );
			$this->cirrusInfo = [];
			foreach ( $store->findAll() as $r ) {
				$data = $r->getData();
				$this->cirrusInfo[ $data['index_name'] ] = [
					'shard_count' => $data[ 'shard_count' ],
				];
			}
		}
	}
}

$maintClass = CheckIndexes::class;
require_once RUN_MAINTENANCE_IF_MAIN;
