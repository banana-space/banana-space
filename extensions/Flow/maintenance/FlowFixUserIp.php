<?php

use Flow\Container;
use Flow\Data\ManagerGroup;
use Flow\DbFactory;
use Flow\Model\UUID;
use Wikimedia\Rdbms\IDatabase;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Sets *_user_ip to null when *_user_id is > 0
 *
 * @ingroup Maintenance
 */
class FlowFixUserIp extends LoggedUpdateMaintenance {
	/**
	 * The number of entries completed
	 *
	 * @var int
	 */
	private $completeCount = 0;

	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	private static $types = [
		'post' => \Flow\Model\PostRevision::class,
		'header' => \Flow\Model\Header::class,
		'post-summary' => \Flow\Model\PostSummary::class,
	];

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Flow' );
	}

	protected function doDBUpdates() {
		$this->storage = $storage = Container::get( 'storage' );
		/** @var DbFactory $dbf */
		$dbf = Container::get( 'db.factory' );
		$dbw = $dbf->getDB( DB_MASTER );
		$fname = __METHOD__;

		$runUpdate = function ( $callback ) use ( $dbf, $dbw, $storage, $fname ) {
			$continue = "\0";
			do {
				$dbw->begin( $fname );
				$continue = $callback( $dbw, $continue );
				$dbw->commit( $fname );
				$dbf->waitForReplicas();
				$storage->clear();
			} while ( $continue !== null );
		};

		$runUpdate( [ $this, 'updateTreeRevision' ] );
		$self = $this;
		foreach ( [ 'rev_user', 'rev_mod_user', 'rev_edit_user' ] as $prefix ) {
			$runUpdate( function ( $dbw, $continue ) use ( $self, $prefix ) {
				return $self->updateRevision( $prefix, $dbw, $continue );
			} );
		}

		return true;
	}

	public function updateTreeRevision( IDatabase $dbw, $continue = null ) {
		$rows = $dbw->select(
			/* table */'flow_tree_revision',
			/* select */[ 'tree_rev_id' ],
			[
				'tree_rev_id > ' . $dbw->addQuotes( $continue ),
				'tree_orig_user_ip IS NOT NULL',
				'tree_orig_user_id > 0',
			],
			__METHOD__,
			/* options */[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'tree_rev_id' ]
		);

		$om = Container::get( 'storage' )->getStorage( 'PostRevision' );
		$objs = $ids = [];
		foreach ( $rows as $row ) {
			$id = UUID::create( $row->tree_rev_id );
			$found = $om->get( $id );
			if ( $found ) {
				$ids[] = $row->tree_rev_id;
				$objs[] = $found;
			} else {
				$this->error( __METHOD__ . ': Failed loading Flow\Model\PostRevision: ' . $id->getAlphadecimal() );
			}
		}
		if ( !$ids ) {
			return null;
		}
		$dbw->update(
			/* table */'flow_tree_revision',
			/* update */[ 'tree_orig_user_ip' => null ],
			/* conditions */[ 'tree_rev_id' => $ids ],
			__METHOD__
		);
		foreach ( $objs as $obj ) {
			$om->cachePurge( $obj );
		}

		$this->completeCount += count( $ids );

		return end( $ids );
	}

	public function updateRevision( $columnPrefix, IDatabase $dbw, $continue = null ) {
		$rows = $dbw->select(
			/* table */'flow_revision',
			/* select */[ 'rev_id', 'rev_type' ],
			/* conditions */ [
				'rev_id > ' . $dbw->addQuotes( $continue ),
				"{$columnPrefix}_id > 0",
				"{$columnPrefix}_ip IS NOT NULL",
			],
			__METHOD__,
			/* options */[ 'LIMIT' => $this->mBatchSize, 'ORDER BY' => 'rev_id' ]
		);

		$ids = $objs = [];
		foreach ( $rows as $row ) {
			$id = UUID::create( $row->rev_id );
			$type = self::$types[$row->rev_type];
			$om = $this->storage->getStorage( $type );
			$obj = $om->get( $id );
			if ( $obj ) {
				$om->merge( $obj );
				$ids[] = $row->rev_id;
				$objs[] = $obj;
			} else {
				$this->error( __METHOD__ . ": Failed loading $type: " . $id->getAlphadecimal() );
			}
		}
		if ( !$ids ) {
			return null;
		}

		$dbw->update(
			/* table */ 'flow_revision',
			/* update */ [ "{$columnPrefix}_ip" => null ],
			/* conditions */ [ 'rev_id' => $ids ],
			__METHOD__
		);

		foreach ( $objs as $obj ) {
			$this->storage->cachePurge( $obj );
		}

		$this->completeCount += count( $ids );

		return end( $ids );
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'FlowFixUserIp';
	}
}

$maintClass = FlowFixUserIp::class; // Tells it to run the class
require_once RUN_MAINTENANCE_IF_MAIN;
