<?php

namespace Flow\Data\Utils;

use Flow\Data\ObjectManager;
use Flow\Exception\InvalidParameterException;

// Better name?
// Add query arrays with a multi-dimensional position
// Merge results with their query value
// Get back result array with same positions as the original query
// Maintains merge ordering
class ResultDuplicator {
	/**
	 * Maps from the query array to its position in the query array
	 *
	 * @var string[]
	 */
	protected $queryKeys;

	/**
	 * @var int
	 */
	protected $dimensions;

	/**
	 * @var MultiDimArray
	 */
	protected $desiredOrder;

	/**
	 * @var MultiDimArray
	 */
	protected $queryMap;

	/**
	 * @var MultiDimArray
	 */
	protected $result;

	/**
	 * @var array
	 */
	protected $queries = [];

	/**
	 * @param string[] $queryKeys
	 * @param int $dimensions
	 */
	public function __construct( array $queryKeys, $dimensions ) {
		$this->queryKeys = $queryKeys;
		$this->dimensions = $dimensions;
		$this->desiredOrder = new MultiDimArray;
		$this->queryMap = new MultiDimArray;
		$this->result = new MultiDimArray;
	}

	/**
	 * Add a query and its position.  Positions must be unique.
	 * @param array $query
	 * @param array $position
	 * @throws InvalidParameterException
	 */
	public function add( $query, $position ) {
		$dim = count( (array)$position );
		if ( $dim !== $this->dimensions ) {
			throw new InvalidParameterException( "Expected position with {$this->dimensions} dimensions, received $dim" );
		}
		$query = ObjectManager::splitFromRow( $query, $this->queryKeys );
		if ( $query === null ) {
			// the queryKeys are either unset or null, and not indexable
			// TODO: what should happen here?
			return;
		}
		$this->desiredOrder[$position] = $query;
		if ( !isset( $this->queryMap[$query] ) ) {
			$this->queries[] = $query;
			$this->queryMap[$query] = true;
		}
	}

	/**
	 * merge a query into the result set
	 * @param array $query
	 * @param array $result
	 */
	public function merge( array $query, array $result ) {
		$query = ObjectManager::splitFromRow( $query, $this->queryKeys );
		if ( $query === null ) {
			// the queryKeys are either unset or null, and not indexable
			// TODO: what should happen here?
			return;
		}
		$this->result[$query] = $result;
	}

	public function getUniqueQueries() {
		return $this->queries;
	}

	public function getResult() {
		return self::sortResult( $this->desiredOrder->all(), $this->result, $this->dimensions );
	}

	/**
	 * merge() wasn't necessarily called in the same order as add(),  this walks back through
	 * the results to put them in the desired order with the correct keys.
	 * @param array $order
	 * @param MultiDimArray $result
	 * @param int $dimensions
	 * @return array
	 */
	public static function sortResult( array $order, MultiDimArray $result, $dimensions ) {
		$final = [];
		foreach ( $order as $position => $query ) {
			if ( $dimensions > 1 ) {
				$final[$position] = self::sortResult( $query, $result, $dimensions - 1 );
			} elseif ( isset( $result[$query] ) ) {
				$final[$position] = $result[$query];
			} else {
				$final[$position] = null;
			}
		}
		return $final;
	}
}
