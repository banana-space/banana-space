<?php

namespace Flow\Data\Index;

use Flow\Data\Compactor\ShallowCompactor;
use Flow\Data\FlowObjectCache;
use Flow\Data\ObjectManager;
use Flow\Data\ObjectMapper;
use Flow\Data\ObjectStorage;
use Flow\Data\Utils\SortArrayByKeys;
use Flow\Exception\DataModelException;
use Flow\Exception\InvalidParameterException;

/**
 * Holds the top k items with matching $indexed columns.  List is sorted and truncated to specified size.
 */
class TopKIndex extends FeatureIndex {
	/**
	 * @var array
	 */
	protected $options = [];

	public function __construct(
		FlowObjectCache $cache,
		ObjectStorage $storage,
		ObjectMapper $mapper,
		$prefix,
		array $indexed,
		array $options = []
	) {
		if ( empty( $options['sort'] ) ) {
			throw new InvalidParameterException( 'TopKIndex must be sorted' );
		}

		parent::__construct( $cache, $storage, $mapper, $prefix, $indexed );

		$this->options = $options + [
			'limit' => 500,
			'order' => 'DESC',
			'create' => function () {
				return false;
			},
			'shallow' => null,
		];
		$this->options['order'] = strtoupper( $this->options['order'] );

		if ( !is_array( $this->options['sort'] ) ) {
			$this->options['sort'] = [ $this->options['sort'] ];
		}
		if ( $this->options['shallow'] ) {
			// TODO: perhaps we shouldn't even get a shallow option, just receive a proper compactor in
			// FeatureIndex::__construct
			$this->rowCompactor = new ShallowCompactor(
				$this->rowCompactor, $this->options['shallow'], $this->options['sort'] );
		}
	}

	public function canAnswer( array $keys, array $options ) {
		if ( !parent::canAnswer( $keys, $options ) ) {
			return false;
		}

		if ( isset( $options['offset-id'] ) ||
			( isset( $options['offset-dir'] ) && $options['offset-dir'] !== 'fwd' )
		) {
			return false;
		}

		if ( isset( $options['sort'] ) && isset( $options['order'] ) ) {
			return ObjectManager::makeArray( $options['sort'] ) === $this->options['sort']
				&& strtoupper( $options['order'] ) === $this->options['order'];
		}
		return true;
	}

	public function getLimit() {
		return $this->options['limit'];
	}

	/**
	 * @param array[] $results
	 * @param array $options
	 *
	 * @return array[]
	 */
	protected function filterResults( array $results, array $options = [] ) {
		foreach ( $results as $i => $result ) {
			list( $offset, $limit ) = $this->getOffsetLimit( $result, $options );
			$results[$i] = array_slice( $result, $offset, $limit, true );
		}

		return $results;
	}

	// TODO: This is only left for now to handle non-ID offsets (e.g. updated
	// timestamps).
	// This has always been broken once you query past the TopKIndex limit.

	/**
	 * @param array $rows
	 * @param array $options
	 * @return array [offset, limit] 0-based index to start with and limit.
	 */
	protected function getOffsetLimit( array $rows, array $options ) {
		$limit = $options['limit'] ?? $this->getLimit();

		$offsetValue = $options['offset-value'] ?? null;

		$dir = 'fwd';
		if (
			isset( $options['offset-dir'] ) &&
			$options['offset-dir'] === 'rev'
		) {
			$dir = 'rev';
		}

		if ( $offsetValue === null ) {
			$offset = $dir === 'fwd' ? 0 : count( $rows ) - $limit;
			return [ $offset, $limit ];
		}

		$offset = $this->getOffsetFromOffsetValue( $rows, $offsetValue );
		$includeOffset = isset( $options['include-offset'] ) && $options['include-offset'];
		if ( $dir === 'fwd' ) {
			if ( $includeOffset ) {
				$startPos = $offset;
			} else {
				$startPos = $offset + 1;
			}
		} elseif ( $dir === 'rev' ) {
			$startPos = $offset - $limit;
			if ( $includeOffset ) {
				$startPos++;
			}

			if ( $startPos < 0 ) {
				if (
					isset( $options['offset-elastic'] ) &&
					$options['offset-elastic'] === false
				) {
					// If non-elastic, then reduce the number of items shown commensurately
					$limit += $startPos;
				}
				$startPos = 0;
			}
		} else {
			$startPos = 0;
		}

		return [ $startPos, $limit ];
	}

	/**
	 * Returns the 0-indexed position of $offsetValue within $rows or throws a
	 * DataModelException if $offsetValue is not contained within $rows
	 *
	 * @todo seems wasteful to pass string offsetValue instead of exploding when it comes in
	 * @param array $rows Current bucket contents
	 * @param string $offsetValue
	 * @return int The position of $offsetValue within $rows
	 * @throws DataModelException When $offsetValue is not found within $rows
	 */
	protected function getOffsetFromOffsetValue( array $rows, $offsetValue ) {
		$rowIndex = 0;
		$nextInOrder = $this->getOrder() === 'DESC' ? -1 : 1;
		foreach ( $rows as $row ) {
			$comparisonValue = $this->compareRowToOffsetValue( $row, $offsetValue );
			if ( $comparisonValue === 0 || $comparisonValue === $nextInOrder ) {
				return $rowIndex;
			}
			$rowIndex++;
		}

		throw new DataModelException( 'Unable to find specified offset in query results', 'process-data' );
	}

	/**
	 * @param array $row Row to compare to
	 * @param string $offsetValue Value to compare to.  For instance, a timestamp if we
	 *  want all rows before/after that timestamp.  This consists of values for each field
	 *  we sort by, delimited by |.
	 *
	 * @return int An integer less than, equal to, or greater than zero
	 *  if $row is considered to be respectively less than, equal to, or
	 *  greater than $offsetValue
	 *
	 * @throws DataModelException When the index does not support offset values due to
	 *  having an undefined sort order.
	 */
	public function compareRowToOffsetValue( array $row, $offsetValue ) {
		$sortFields = $this->getSort();
		$splitOffsetValue = explode( '|', $offsetValue );
		$fieldIndex = 0;

		if ( $sortFields === false ) {
			throw new DataModelException( 'This Index implementation does not support offset values',
				'process-data' );
		}

		foreach ( $sortFields as $field ) {
			$valueInRow = $row[$field];
			$offsetValuePart = $splitOffsetValue[$fieldIndex];

			if ( $valueInRow > $offsetValuePart ) {
				return 1;
			} elseif ( $valueInRow < $offsetValuePart ) {
				return -1;
			}
			++$fieldIndex;
		}

		return 0;
	}

	protected function removeFromIndex( array $indexed, array $row ) {
		$this->cache->delete( $this->cacheKey( $indexed ) );
	}

	/**
	 * In order to be able to reliably find a row in an array of
	 * cached rows, we need to normalize those to make sure the
	 * columns match: they may be outdated.
	 *
	 * @param array $row Array in [column => value] format
	 * @param array $schema Array of column names to be present in $row
	 * @return array
	 */
	// INTERNAL: in 5.4 it can be protected
	public function normalizeCompressed( array $row, array $schema ) {
		$schema = array_fill_keys( $schema, null );

		// add null value for columns currently in cache
		$row = array_merge( $schema, $row );

		// remove unknown columns from the row
		$row = array_intersect_key( $row, $schema );

		return $row;
	}

	// INTERNAL: in 5.4 it can be protected
	public function sortIndex( array $values ) {
		// I don't think this is a valid way to sort a 128bit integer string
		$callback = new SortArrayByKeys( $this->options['sort'], true );
		/** @noinspection PhpParamsInspection */
		usort( $values, $callback );
		if ( $this->options['order'] === 'DESC' ) {
			$values = array_reverse( $values );
		}
		return $values;
	}

	// INTERNAL: in 5.4 it can be protected
	public function limitIndexSize( array $values ) {
		return array_slice( $values, 0, $this->options['limit'] );
	}

	// INTERNAL: in 5.4 it can be protected
	public function queryOptions() {
		$options = [ 'LIMIT' => $this->options['limit'] ];

		$orderBy = [];
		$order = $this->options['order'];
		// @phan-suppress-next-line PhanTypeNoPropertiesForeach
		foreach ( $this->options['sort'] as $key ) {
			$orderBy[] = "$key $order";
		}
		$options['ORDER BY'] = $orderBy;

		return $options;
	}
}
