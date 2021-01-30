<?php

namespace Flow\Data\Compactor;

use Flow\Data\Compactor;
use Flow\Data\Index\UniqueFeatureIndex;
use Flow\Data\Utils\ResultDuplicator;

/**
 * Backs an index with a UniqueFeatureIndex.  This index will store only the primary key
 * values from the unique index, and on retrieval from cache will materialize the primary key
 * values into full rows from the unique index.
 */
class ShallowCompactor implements Compactor {
	/**
	 * @var Compactor
	 */
	protected $inner;

	/**
	 * @var UniqueFeatureIndex
	 */
	protected $shallow;

	/**
	 * @var string[]
	 */
	protected $sort;

	/**
	 * @param Compactor $inner
	 * @param UniqueFeatureIndex $shallow
	 * @param string[] $sortedColumns
	 */
	public function __construct( Compactor $inner, UniqueFeatureIndex $shallow, array $sortedColumns ) {
		$this->inner = $inner;
		$this->shallow = $shallow;
		$this->sort = $sortedColumns;
	}

	/**
	 * @param array $row
	 * @return array
	 */
	public function compactRow( array $row ) {
		$keys = array_merge( $this->shallow->getPrimaryKeyColumns(), $this->sort );
		$extra = array_diff( array_keys( $row ), $keys );
		foreach ( $extra as $key ) {
			unset( $row[$key] );
		}
		return $this->inner->compactRow( $row );
	}

	/**
	 * @param array $rows
	 * @return array
	 */
	public function compactRows( array $rows ) {
		return array_map( [ $this, 'compactRow' ], $rows );
	}

	/**
	 * @return UniqueFeatureIndex
	 */
	public function getShallow() {
		return $this->shallow;
	}

	/**
	 * @param array $cached
	 * @param array $keyToQuery
	 * @return ResultDuplicator
	 */
	public function getResultDuplicator( array $cached, array $keyToQuery ) {
		$results = $this->inner->expandCacheResult( $cached, $keyToQuery );
		// Allows us to flatten $results into a single $query array, then
		// rebuild final return value in same structure and order as $results.
		$duplicator = new ResultDuplicator( $this->shallow->getPrimaryKeyColumns(), 2 );
		foreach ( $results as $i => $rows ) {
			foreach ( $rows as $j => $row ) {
				$duplicator->add( $row, [ $i, $j ] );
			}
		}

		return $duplicator;
	}

	/**
	 * @param array $cached
	 * @param array $keyToQuery
	 * @return array
	 */
	public function expandCacheResult( array $cached, array $keyToQuery ) {
		$duplicator = $this->getResultDuplicator( $cached, $keyToQuery );
		$queries = $duplicator->getUniqueQueries();
		$innerResult = $this->shallow->findMulti( $queries );

		foreach ( $innerResult as $rows ) {
			// __construct guaranteed the shallow backing index is a unique, so $first is only result
			$first = reset( $rows );
			$duplicator->merge( $first, $first );
		}

		return $duplicator->getResult();
	}
}
