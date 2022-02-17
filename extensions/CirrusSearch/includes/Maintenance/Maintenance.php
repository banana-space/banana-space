<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\Connection;
use CirrusSearch\MetaStore\MetaStoreIndex;
use CirrusSearch\SearchConfig;
use CirrusSearch\UserTesting;
use MediaWiki\MediaWikiServices;

// Maintenance class is loaded before autoload, so we need to pull the interface
require_once __DIR__ . '/Printer.php';

/**
 * Cirrus helpful extensions to Maintenance.
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
abstract class Maintenance extends \Maintenance implements Printer {
	/**
	 * @var string The string to indent output with
	 */
	protected static $indent = null;

	/**
	 * @var Connection|null
	 */
	private $connection;

	/**
	 * @var SearchConfig
	 */
	private $searchConfig;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'cluster', 'Perform all actions on the specified elasticsearch cluster',
			false, true );
		$this->addOption( 'userTestTrigger', 'Use config var and profiles set in the user testing ' .
			'framework, e.g. --userTestTrigger=trigger', false, true );
	}

	public function finalSetup() {
		parent::finalSetup();
		if ( $this->hasOption( 'userTestTrigger' ) ) {
			$this->setupUserTest();
		}
	}

	/**
	 * Setup config vars with the UserTest framework
	 */
	private function setupUserTest() {
		// Configure the UserTesting framework
		// Useful in case an index needs to be built with a
		// test config that is not meant to be the default.
		// This is realistically only usefull to test across
		// multiple clusters.
		// Perhaps setting $wgCirrusSearchIndexBaseName to an
		// alternate value would testing on the same cluster
		// but this index would not receive updates.
		$trigger = $this->getOption( 'userTestTrigger' );
		$ut = UserTesting::getInstance( null, $trigger );
		if ( !$ut->getActiveTestNames() ) {
			$this->fatalError( "Unknown user test trigger: $trigger" );
		}
	}

	/**
	 * @param string $maintClass
	 * @param string|null $classFile
	 * @return \Maintenance
	 */
	public function runChild( $maintClass, $classFile = null ) {
		$child = parent::runChild( $maintClass, $classFile );
		if ( $child instanceof self ) {
			$child->searchConfig = $this->searchConfig;
		}

		return $child;
	}

	/**
	 * @param string|null $cluster
	 * @return Connection
	 */
	public function getConnection( $cluster = null ) {
		if ( $cluster ) {
			if ( !$this->getSearchConfig() instanceof SearchConfig ) {
				// We shouldn't ever get here ... but the makeConfig type signature returns the parent
				// class of SearchConfig so just being extra careful...
				throw new \RuntimeException( 'Expected instanceof CirrusSearch\SearchConfig, but received ' .
					get_class( $this->getSearchConfig() ) );
			}
			$connection = Connection::getPool( $this->getSearchConfig(), $cluster );
		} else {
			if ( $this->connection === null ) {
				$cluster = $this->decideCluster();
				$this->connection = Connection::getPool( $this->getSearchConfig(), $cluster );
			}
			$connection = $this->connection;
		}

		$connection->setTimeout( $this->getSearchConfig()->get( 'CirrusSearchMaintenanceTimeout' ) );

		return $connection;
	}

	public function getSearchConfig() {
		if ( $this->searchConfig == null ) {
			$this->searchConfig = MediaWikiServices::getInstance()
				->getConfigFactory()
				->makeConfig( 'CirrusSearch' );
		}
		return $this->searchConfig;
	}

	/**
	 * @return string|null
	 */
	private function decideCluster() {
		$cluster = $this->getOption( 'cluster', null );
		if ( $cluster === null ) {
			return null;
		}
		if ( $this->getSearchConfig()->has( 'CirrusSearchServers' ) ) {
			$this->fatalError( 'Not configured for cluster operations.' );
		}
		return $cluster;
	}

	/**
	 * Execute a callback function at the end of initialisation
	 */
	public function loadSpecialVars() {
		parent::loadSpecialVars();
		if ( self::$indent === null ) {
			// First script gets no indentation
			self::$indent = '';
		} else {
			// Others get one tab beyond the last
			self::$indent .= "\t";
		}
	}

	/**
	 * Call to signal that execution of this maintenance script is complete so
	 * the next one gets the right indentation.
	 */
	public function done() {
		self::$indent = substr( self::$indent, 1 );
	}

	/**
	 * @param string $message
	 * @param string|null $channel
	 */
	public function output( $message, $channel = null ) {
		parent::output( $message );
	}

	public function outputIndented( $message ) {
		$this->output( self::$indent . $message );
	}

	/**
	 * @param string $err
	 * @param int $die deprecated, do not use
	 */
	public function error( $err, $die = 0 ) {
		parent::error( $err, $die );
	}

	/**
	 * Disable all pool counters and cirrus query logs.
	 * Only useful for maint scripts
	 *
	 * Ideally this method could be run in the constructor
	 * but apparently globals are reset just before the
	 * call to execute()
	 */
	protected function disablePoolCountersAndLogging() {
		global $wgPoolCounterConf, $wgCirrusSearchLogElasticRequests;

		// Make sure we don't flood the pool counter
		unset( $wgPoolCounterConf['CirrusSearch-Search'] );

		// Don't skew the dashboards by logging these requests to
		// the global request log.
		$wgCirrusSearchLogElasticRequests = false;
		// Disable statsd data collection.
		$stats = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$stats->setEnabled( false );
	}

	/**
	 * Create metastore only if the alias does not already exist
	 * @return MetaStoreIndex
	 */
	protected function maybeCreateMetastore() {
		$metastore = new MetaStoreIndex(
			$this->getConnection(),
			$this,
			$this->getSearchConfig() );
		$metastore->createIfNecessary();
		return $metastore;
	}
}
