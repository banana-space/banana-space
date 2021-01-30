<?php

namespace Flow\Data;

use Flow\DbFactory;
use Flow\Exception\DataModelException;
use Flow\Exception\FlowException;
use Flow\Model\UUID;
use SplObjectStorage;

/**
 * ObjectManager orchestrates the storage of a single type of objects.
 * Where ObjectLocator handles querying, ObjectManager extends that to
 * add persistence.
 *
 * The ObjectManager has two required constructor dependencies:
 * * An ObjectMapper instance that can convert back and forth from domain
 *   objects to database rows
 * * An ObjectStorage implementation that implements persistence.
 *
 * Additionally there are two optional constructor arguments:
 * * A set of Index objects that listen to life cycle events and maintain
 *   an up-to date cache of all objects. Individual Index objects typically
 *   answer a single set of query arguments.
 * * A set of LifecycleHandler implementations that are notified about
 *   insert, update, remove and load events.
 *
 * A simple ObjectManager instances might be created as such:
 *
 *   $om = new Flow\Data\ObjectManager(
 *        Flow\Data\Mapper\BasicObjectMapper::model( 'MyModelClass' ),
 *        new Flow\Data\Storage\BasicDbStorage(
 *            $dbFactory,
 *            'my_model_table',
 *            array( 'my_primary_key' )
 *        )
 *   );
 *
 * Objects of MyModelClass can be stored:
 *
 *   $om->put( $object );
 *
 * Objects can be retrieved via my_primary_key
 *
 *   $object = $om->get( $pk );
 *
 * The object can be updated by calling ObjectManager:put at
 * any time.  If the object is to be deleted:
 *
 *   $om->remove( $object );
 *
 * The data cached in the indexes about this object can be cleared
 * with:
 *
 *   $om->cachePurge( $object );
 *
 * In addition to the single-use put, get and remove there are also multi
 * variants named multiPut, mulltiGet and multiRemove.  They perform the
 * same operation as their namesake but with fewer network operations when
 * dealing with multiple objects of the same type.
 *
 * @todo Information about Indexes and LifecycleHandlers
 */
class ObjectManager extends ObjectLocator {
	/**
	 * @var SplObjectStorage $loaded Maps from a php object to the database
	 *  row that was used to create it. One use of this is to toggle between
	 *  self::insert and self::update when self::put is called.
	 */
	protected $loaded;

	/**
	 * @param ObjectMapper $mapper Convert to/from database rows/domain objects.
	 * @param ObjectStorage $storage Implements persistence(typically sql)
	 * @param DbFactory $dbFactory
	 * @param Index[] $indexes Specialized listeners that cache rows and can respond
	 *  to queries
	 * @param LifecycleHandler[] $lifecycleHandlers Listeners for insert, update,
	 *  remove and load events.
	 */
	public function __construct(
		ObjectMapper $mapper,
		ObjectStorage $storage,
		DbFactory $dbFactory,
		array $indexes = [],
		array $lifecycleHandlers = []
	) {
		parent::__construct( $mapper, $storage, $dbFactory, $indexes, $lifecycleHandlers );

		// This needs to be SplObjectStorage rather than using spl_object_hash for keys
		// in a normal array because if the object gets GC'd spl_object_hash can reuse
		// the value.  Stuffing the object as well into SplObjectStorage prevents GC.
		$this->loaded = new SplObjectStorage;
	}

	/**
	 * Clear the internal cache of which objects have been loaded so far.
	 *
	 * Objects that were loaded prior to clearing the object manager must
	 * not use self::put until they have been merged via self::merge or
	 * an insert operation will be performed.
	 */
	public function clear() {
		$this->loaded = new SplObjectStorage;
		$this->mapper->clear();
		foreach ( $this->lifecycleHandlers as $handler ) {
			$handler->onAfterClear();
		}
	}

	/**
	 * Merge an object loaded from outside the object manager for update.
	 * Without merge using self::put will trigger an insert operation.
	 *
	 * @param object $object
	 */
	public function merge( $object ) {
		if ( !isset( $this->loaded[$object] ) ) {
			$this->loaded[$object] = $this->mapper->toStorageRow( $object );
		}
	}

	/**
	 * Purge all cached data related to this object.
	 *
	 * @param object $object
	 */
	public function cachePurge( $object ) {
		if ( !isset( $this->loaded[$object] ) ) {
			throw new FlowException( 'Object was not loaded through this object manager, use ObjectManager::merge if necessary' );
		}
		$row = $this->loaded[$object];
		foreach ( $this->indexes as $index ) {
			$index->cachePurge( $object, $row );
		}
	}

	/**
	 * Persist a single object to storage.
	 *
	 * @param object $object
	 * @param array $metadata Additional information about the object for
	 *  listeners to operate on.
	 */
	public function put( $object, array $metadata = [] ) {
		$this->multiPut( [ $object ], $metadata );
	}

	/**
	 * Persist multiple objects to storage.
	 *
	 * @param object[] $objects
	 * @param array $metadata Additional information about the object for
	 *  listeners to operate on.
	 */
	public function multiPut( array $objects, array $metadata = [] ) {
		$updateObjects = [];
		$insertObjects = [];

		foreach ( $objects as $object ) {
			if ( isset( $this->loaded[$object] ) ) {
				$updateObjects[] = $object;
			} else {
				$insertObjects[] = $object;
			}
		}

		if ( count( $updateObjects ) ) {
			$this->update( $updateObjects, $metadata );
		}

		if ( count( $insertObjects ) ) {
			$this->insert( $insertObjects, $metadata );
		}
	}

	/**
	 * Remove an object from persistent storage.
	 *
	 * @param object $object
	 * @param array $metadata Additional information about the object for
	 *  listeners to operate on.
	 */
	public function remove( $object, array $metadata = [] ) {
		if ( !isset( $this->loaded[$object] ) ) {
			throw new FlowException( 'Object was not loaded through this object manager, use ObjectManager::merge if necessary' );
		}
		$old = $this->loaded[$object];
		$old = $this->mapper->normalizeRow( $old );
		$this->storage->remove( $old );
		foreach ( $this->lifecycleHandlers as $handler ) {
			$handler->onAfterRemove( $object, $old, $metadata );
		}
		unset( $this->loaded[$object] );
	}

	/**
	 * Remove multiple objects from persistent storage.
	 *
	 * @param object[] $objects
	 * @param array $metadata
	 */
	public function multiRemove( $objects, array $metadata ) {
		foreach ( $objects as $obj ) {
			$this->remove( $obj, $metadata );
		}
	}

	/**
	 * Return a string value that can be provided to self::find or self::findMulti
	 * as the offset-id option to facilitate pagination.
	 *
	 * @param object $object
	 * @param array $sortFields
	 * @return string
	 */
	public function serializeOffset( $object, array $sortFields ) {
		$offsetFields = [];
		// @todo $row = $this->loaded[$object] ?
		$row = $this->mapper->toStorageRow( $object );
		// @todo Why not self::splitFromRow?
		foreach ( $sortFields as $field ) {
			$value = $row[$field];

			if ( is_string( $value )
				&& strlen( $value ) === UUID::BIN_LEN
				&& substr( $field, -3 ) === '_id'
			) {
				$value = UUID::create( $value );
			}
			if ( $value instanceof UUID ) {
				$value = $value->getAlphadecimal();
			}
			$offsetFields[] = $value;
		}

		return implode( '|', $offsetFields );
	}

	/**
	 * Insert new objects into storage.
	 *
	 * @param object[] $objects
	 * @param array $metadata
	 */
	protected function insert( array $objects, array $metadata ) {
		$rows = array_map( [ $this->mapper, 'toStorageRow' ], $objects );
		$storedRows = $this->storage->insert( $rows );
		if ( !$storedRows ) {
			throw new DataModelException( 'failed insert', 'process-data' );
		}

		$numObjects = count( $objects );
		for ( $i = 0; $i < $numObjects; ++$i ) {
			$object = $objects[$i];
			$stored = $storedRows[$i];

			// Propagate stuff that was added to the row by storage back
			// into the object. Currently intended for storage URLs etc,
			// but may in the future also bring in auto-ids and so on.
			$this->mapper->fromStorageRow( $stored, $object );

			foreach ( $this->lifecycleHandlers as $handler ) {
				$handler->onAfterInsert( $object, $stored, $metadata );
			}

			$this->loaded[$object] = $stored;
		}
	}

	/**
	 * Update the set of objects representation within storage.
	 *
	 * @param object[] $objects
	 * @param array $metadata
	 */
	protected function update( array $objects, array $metadata ) {
		foreach ( $objects as $object ) {
			$this->updateSingle( $object, $metadata );
		}
	}

	/**
	 * Update a single objects representation within storage.
	 *
	 * @param object $object
	 * @param array $metadata
	 */
	protected function updateSingle( $object, array $metadata ) {
		$old = $this->loaded[$object];
		$old = $this->mapper->normalizeRow( $old );
		$new = $this->mapper->toStorageRow( $object );
		if ( self::arrayEquals( $old, $new ) ) {
			return;
		}
		$this->storage->update( $old, $new );
		foreach ( $this->lifecycleHandlers as $handler ) {
			$handler->onAfterUpdate( $object, $old, $new, $metadata );
		}
		$this->loaded[$object] = $new;
	}

	/**
	 * @inheritDoc
	 */
	protected function load( array $row ) {
		$object = parent::load( $row );
		$this->loaded[$object] = $row;
		return $object;
	}

	/**
	 * Compare two arrays for equality.
	 * @todo why not $x === $y ?
	 *
	 * @param array $old
	 * @param array $new
	 * @return bool
	 */
	public static function arrayEquals( array $old, array $new ) {
		return array_diff_assoc( $old, $new ) === []
			&& array_diff_assoc( $new, $old ) === [];
	}

	/**
	 * Convert the input argument into an array. This is preferred
	 * over casting with (array)$value because that will cast an
	 * object to an array rather than wrap it.
	 *
	 * @param mixed $input
	 *
	 * @return array
	 */
	public static function makeArray( $input ) {
		if ( is_array( $input ) ) {
			return $input;
		} else {
			return [ $input ];
		}
	}

	/**
	 * Return an array containing all the top level changes between
	 * $old and $new. Expects $old and $new to be representations of
	 * database rows and contain only strings and numbers.
	 *
	 * It does not validate that it is a legal update (See DbStorage->calcUpdates).
	 *
	 * @param array $old
	 * @param array $new
	 * @return array
	 */
	public static function calcUpdatesWithoutValidation( array $old, array $new ) {
		$updates = [];
		foreach ( array_keys( $new ) as $key ) {
			/*
			 * $old[$key] and $new[$key] could both be the same value going into the same
			 * column, but represented as different data type here: one could be a string
			 * and another an int, of even an object (e.g. Blob)
			 * What we should be comparing is their "value", regardless of the data type
			 * (different between them doesn't matter here, both are for the same database
			 * column), so I'm casting them to string before performing comparison.
			 */
			if ( !array_key_exists( $key, $old ) || (string)$old[$key] !== (string)$new[$key] ) {
				$updates[$key] = $new[$key];
			}
			unset( $old[$key] );
		}
		// These keys don't exist in $new
		foreach ( array_keys( $old ) as $key ) {
			$updates[$key] = null;
		}
		return $updates;
	}

	/**
	 * Separate a set of keys from an array. Returns null if not
	 * all keys are set.
	 *
	 * @param array $row
	 * @param string[] $keys
	 * @return array|null
	 */
	public static function splitFromRow( array $row, array $keys ) {
		$split = [];
		foreach ( $keys as $key ) {
			if ( !isset( $row[$key] ) ) {
				return null;
			}
			$split[$key] = $row[$key];
		}

		return $split;
	}
}
