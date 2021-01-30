<?php

namespace Flow\Data;

use Flow\Data\Utils\RawSql;
use Flow\DbFactory;
use Flow\Exception\NoIndexException;
use Flow\Model\UUID;
use FormatJson;

/**
 * Denormalized indexes that are query-only.  The indexes used here must
 * be provided to some ObjectManager as a lifecycleHandler to receive
 * update events.
 */
class ObjectLocator {
	/**
	 * @var ObjectMapper
	 */
	protected $mapper;

	/**
	 * @var ObjectStorage
	 */
	protected $storage;

	/**
	 * @var Index[]
	 */
	protected $indexes;

	/**
	 * Database factory (only for addQuotes)
	 *
	 * @var DbFactory
	 */
	protected $dbFactory;

	/**
	 * @var LifecycleHandler[]
	 */
	protected $lifecycleHandlers;

	/**
	 * @param ObjectMapper $mapper
	 * @param ObjectStorage $storage
	 * @param DbFactory $dbFactory
	 * @param Index[] $indexes
	 * @param LifecycleHandler[] $lifecycleHandlers
	 */
	public function __construct(
		ObjectMapper $mapper,
		ObjectStorage $storage,
		DbFactory $dbFactory,
		array $indexes = [],
		array $lifecycleHandlers = []
	) {
		$this->mapper = $mapper;
		$this->storage = $storage;
		$this->indexes = $indexes;
		$this->dbFactory = $dbFactory;
		$this->lifecycleHandlers = array_merge( $indexes, $lifecycleHandlers );
	}

	public function getMapper() {
		return $this->mapper;
	}

	public function find( array $attributes, array $options = [] ) {
		$result = $this->findMulti( [ $attributes ], $options );
		return $result ? reset( $result ) : [];
	}

	/**
	 * All queries must be against the same index. Results are equivalent to
	 * array_map, maintaining order and key relationship between input $queries
	 * and $result.
	 *
	 * @param array $queries
	 * @param array $options
	 * @return array[]
	 */
	public function findMulti( array $queries, array $options = [] ) {
		if ( !$queries ) {
			return [];
		}

		$keys = array_keys( reset( $queries ) );
		if ( isset( $options['sort'] ) && !is_array( $options['sort'] ) ) {
			$options['sort'] = ObjectManager::makeArray( $options['sort'] );
		}

		try {
			$index = $this->getIndexFor( $keys, $options );
			$res = $index->findMulti( $queries, $options );
		} catch ( NoIndexException $e ) {
			if ( array_search( 'topic_root_id', $keys ) ) {
				wfDebugLog(
					'Flow',
					__METHOD__ . ': '
					. json_encode( $keys ) . ' : '
					. json_encode( $options ) . ' : '
					. json_encode( array_map( 'get_class', $this->indexes ) )
				);
				\MWExceptionHandler::logException( $e );
			} else {
				wfDebugLog( 'FlowDebug', __METHOD__ . ': ' . $e->getMessage() );
			}
			$res = $this->storage->findMulti(
				$this->convertToDbQueries( $queries, $options ),
				$this->convertToDbOptions( $options )
			);
		}

		$output = [];
		foreach ( $res as $index => $queryOutput ) {
			foreach ( $queryOutput as $k => $v ) {
				if ( $v ) {
					$output[$index][$k] = $this->load( $v );
				}
			}
		}

		return $output;
	}

	/**
	 * Returns a boolean true/false if the find()-operation for the given
	 * attributes has already been resolves and doesn't need to query any
	 * outside cache/database.
	 * Determining if a find() has not yet been resolved may be useful so that
	 * additional data may be loaded at once.
	 *
	 * @param array $attributes Attributes to find()
	 * @param array $options Options to find()
	 * @return bool
	 */
	public function found( array $attributes, array $options = [] ) {
		return $this->foundMulti( [ $attributes ], $options );
	}

	/**
	 * Returns a boolean true/false if the findMulti()-operation for the given
	 * attributes has already been resolves and doesn't need to query any
	 * outside cache/database.
	 * Determining if a find() has not yet been resolved may be useful so that
	 * additional data may be loaded at once.
	 *
	 * @param array $queries Queries to findMulti()
	 * @param array $options Options to findMulti()
	 * @return bool
	 */
	public function foundMulti( array $queries, array $options = [] ) {
		if ( !$queries ) {
			return true;
		}

		$keys = array_keys( reset( $queries ) );
		if ( isset( $options['sort'] ) && !is_array( $options['sort'] ) ) {
			$options['sort'] = ObjectManager::makeArray( $options['sort'] );
		}

		foreach ( $queries as $key => $value ) {
			$queries[$key] = UUID::convertUUIDs( $value, 'alphadecimal' );
		}

		try {
			$index = $this->getIndexFor( $keys, $options );
			$res = $index->foundMulti( $queries, $options );
			return $res;
		} catch ( NoIndexException $e ) {
			wfDebugLog( 'FlowDebug', __METHOD__ . ': ' . $e->getMessage() );
		}

		return false;
	}

	public function getPrimaryKeyColumns() {
		return $this->storage->getPrimaryKeyColumns();
	}

	public function get( $id ) {
		$result = $this->getMulti( [ $id ] );
		return $result ? reset( $result ) : null;
	}

	/**
	 * Just a helper to find by primary key
	 * Be careful with regards to order on composite primary keys,
	 * must be in same order as provided to the storage implementation.
	 * @param array $objectIds
	 * @return array
	 */
	public function getMulti( array $objectIds ) {
		if ( !$objectIds ) {
			return [];
		}
		$primaryKey = $this->storage->getPrimaryKeyColumns();
		$queries = [];
		$retval = [];
		foreach ( $objectIds as $id ) {
			// check internal cache
			$query = array_combine( $primaryKey, ObjectManager::makeArray( $id ) );
			$obj = $this->mapper->get( $query );
			if ( $obj === null ) {
				$queries[] = $query;
			} else {
				$retval[] = $obj;
			}
		}
		if ( $queries ) {
			$res = $this->findMulti( $queries );
			if ( $res ) {
				foreach ( $res as $row ) {
					// primary key is unique, but indexes still return their results as array
					// to be consistent. undo that for a flat result array
					$retval[] = reset( $row );
				}
			}
		}

		return $retval;
	}

	/**
	 * Returns a boolean true/false if the get()-operation for the given
	 * attributes has already been resolves and doesn't need to query any
	 * outside cache/database.
	 * Determining if a find() has not yet been resolved may be useful so that
	 * additional data may be loaded at once.
	 *
	 * @param string|int $id Id to get()
	 * @return bool
	 */
	public function got( $id ) {
		return $this->gotMulti( [ $id ] );
	}

	/**
	 * Returns a boolean true/false if the getMulti()-operation for the given
	 * attributes has already been resolves and doesn't need to query any
	 * outside cache/database.
	 * Determining if a find() has not yet been resolved may be useful so that
	 * additional data may be loaded at once.
	 *
	 * @param array $objectIds Ids to getMulti()
	 * @return bool
	 */
	public function gotMulti( array $objectIds ) {
		if ( !$objectIds ) {
			return true;
		}

		$primaryKey = $this->storage->getPrimaryKeyColumns();
		$queries = [];
		foreach ( $objectIds as $id ) {
			$query = array_combine( $primaryKey, ObjectManager::makeArray( $id ) );
			$query = UUID::convertUUIDs( $query, 'alphadecimal' );
			if ( !$this->mapper->get( $query ) ) {
				$queries[] = $query;
			}
		}

		if ( $queries && $this->mapper instanceof Mapper\CachingObjectMapper ) {
			return false;
		}

		return $this->foundMulti( $queries );
	}

	public function clear() {
		// nop, we don't store anything
	}

	/**
	 * @param array $keys
	 * @param array $options
	 * @return Index
	 * @throws NoIndexException
	 */
	public function getIndexFor( array $keys, array $options = [] ) {
		sort( $keys );
		/** @var Index|null $current */
		$current = null;
		foreach ( $this->indexes as $index ) {
			// @var Index $index
			if ( !$index->canAnswer( $keys, $options ) ) {
				continue;
			}

			// make sure at least some index is picked
			if ( $current === null ) {
				$current = $index;

			// Find the smallest matching index
			} elseif ( isset( $options['limit'] ) ) {
				$current = $index->getLimit() < $current->getLimit() ? $index : $current;

			// if no limit specified, find biggest matching index
			} else {
				$current = $index->getLimit() > $current->getLimit() ? $index : $current;
			}
		}
		if ( $current === null ) {
			$count = count( $this->indexes );
			throw new NoIndexException(
				"No index (out of $count) available to answer query for " . implode( ", ", $keys ) .
				' with options ' . FormatJson::encode( $options ), 'no-index'
			);
		}
		return $current;
	}

	protected function load( array $row ) {
		$object = $this->mapper->fromStorageRow( $row );
		foreach ( $this->lifecycleHandlers as $handler ) {
			$handler->onAfterLoad( $object, $row );
		}
		return $object;
	}

	/**
	 * Convert index options to db equivalent options
	 * @param array $options
	 * @return array
	 */
	protected function convertToDbOptions( array $options ) {
		$dbOptions = $orderBy = [];
		$order = '';

		if ( isset( $options['limit'] ) ) {
			$dbOptions['LIMIT'] = (int)$options['limit'];
		}

		if ( isset( $options['order'] ) ) {
			$order = ' ' . $options['order'];
		}

		if ( isset( $options['sort'] ) ) {
			foreach ( $options['sort'] as $val ) {
				$orderBy[] = $val . $order;
			}
		}

		if ( $orderBy ) {
			$dbOptions['ORDER BY'] = $orderBy;
		}

		return $dbOptions;
	}

	/**
	 * Uses options to figure out conditions to add to the DB queries.
	 *
	 * @param array[] $queries Array of queries, with each element an array of attributes
	 * @param array $options Options for queries
	 * @return array Queries for BasicDbStorage class
	 */
	protected function convertToDbQueries( array $queries, array $options ) {
		if ( isset( $options['offset-id'] ) &&
			isset( $options['sort'] ) && count( $options['sort'] ) === 1 &&
			preg_match( '/_id$/', $options['sort'][0] ) ) {
				if ( !$options['offset-id'] instanceof UUID ) {
					$options['offset-id'] = UUID::create( $options['offset-id'] );
				}

				if ( $options['order'] === 'ASC' ) {
					$operator = '>';
				} else {
					$operator = '<';
				}

				if ( isset( $options['offset-include'] ) && $options['offset-include'] ) {
					$operator .= '=';
				}

				$dbr = $this->dbFactory->getDB( DB_REPLICA );
				// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
				$condition = new RawSql( $options['sort'][0] . ' ' . $operator . ' ' .
					$dbr->addQuotes( $options['offset-id']->getBinary() ) );

				foreach ( $queries as &$query ) {
					$query[] = $condition;
				}
		}

		return $queries;
	}
}
