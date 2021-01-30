<?php

namespace Flow\Data\Pager;

use Flow\Data\Index;
use Flow\Data\ObjectManager;
use Flow\Exception\InvalidInputException;

/**
 * Fetches paginated results from the OM provided in constructor
 */
class Pager {
	private static $VALID_DIRECTIONS = [ 'fwd', 'rev' ];
	private const DEFAULT_DIRECTION = 'fwd';
	private const DEFAULT_LIMIT = 1;
	private const MAX_LIMIT = 500;
	private const MAX_QUERIES = 4;

	/**
	 * @var ObjectManager
	 */
	protected $storage;

	/**
	 * @var Index
	 */
	protected $index;

	/**
	 * @var array Results sorted by the values in this array
	 */
	protected $sort;

	/**
	 * @var array Map of column name to column value for equality query
	 */
	protected $query;

	/**
	 * @var array Options effecting the result such as `sort`, `order`, and `pager-limit`
	 */
	protected $options;

	/**
	 * @var string
	 */
	protected $offsetKey;

	/**
	 * @var bool Whether this pager uses ID fields
	 */
	protected $useId;

	public function __construct( ObjectManager $storage, array $query, array $options ) {
		// not sure i like this
		$this->storage = $storage;
		$this->query = $query;
		$this->options = $options + [
			'pager-include-offset' => null,
			'pager-offset' => null,
			'pager-limit' => self::DEFAULT_LIMIT,
			'pager-dir' => self::DEFAULT_DIRECTION,
		];

		$this->options['pager-limit'] = intval( $this->options['pager-limit'] );
		if ( !( $this->options['pager-limit'] > 0 && $this->options['pager-limit'] < self::MAX_LIMIT ) ) {
			$this->options['pager-limit'] = self::DEFAULT_LIMIT;
		}

		if ( !in_array( $this->options['pager-dir'], self::$VALID_DIRECTIONS ) ) {
			$this->options['pager-dir'] = self::DEFAULT_DIRECTION;
		}

		$indexOptions = [
			'limit' => $this->options['pager-limit']
		];
		if ( isset( $this->options['sort'] ) && isset( $this->options['order'] ) ) {
			$indexOptions += [
				'sort' => [ $this->options['sort'] ],
				'order' => $this->options['order'],
			];
		}
		$this->sort = $storage->getIndexFor(
			array_keys( $query ),
			$indexOptions
		)->getSort();

		$useId = false;
		foreach ( $this->sort as $val ) {
			if ( substr( $val, -3 ) === '_id' ) {
				$useId = true;
			}
			break;
		}
		$this->useId = $useId;

		$this->offsetKey = $useId ? 'offset-id' : 'offset';
	}

	/**
	 * @param callable|null $filter Accepts an array of objects found in a single query
	 *  as its only argument and returns an array of accepted objects.
	 * @return PagerPage
	 */
	public function getPage( $filter = null ) {
		$numNeeded = $this->options['pager-limit'] + 1;
		$storageOffsetKey = $this->useId ? 'offset-id' : 'offset-value';

		$options = $this->options + [
			// We need one item of leeway to determine if there are more items
			'limit' => $numNeeded,
			'offset-dir' => $this->options['pager-dir'],
			$storageOffsetKey => $this->options['pager-offset'],
			'include-offset' => $this->options['pager-include-offset'],
		];
		$offset = $this->options['pager-offset'];
		$results = [];
		$queries = 0;

		do {
			if ( $queries === 2 ) {
				// if we hit a third query ask for more items
				$options['limit'] = min( self::MAX_LIMIT, $this->options['pager-limit'] * 5 );
			}

			// Retrieve results
			$options = [
				$storageOffsetKey => $offset,
			] + $options;
			$found = $this->storage->find( $this->query, $options );

			if ( !$found ) {
				// nothing found
				break;
			}
			$filtered = $filter ? $filter( $found ) : $found;
			if ( $this->options['pager-dir'] === 'rev' ) {
				// Paging A-Z with pager-offset F, pager-dir rev, pager-limit 2 gives
				// DE on first query, BC on second, and A on third.  The output
				// needs to be ABCDE
				$results = array_merge( $filtered, $results );
			} else {
				$results = array_merge( $results, $filtered );
			}

			if ( count( $found ) !== $options['limit'] ) {
				// last page
				break;
			}

			// setup offset for next query
			if ( $this->options['pager-dir'] === 'rev' ) {
				$last = reset( $found );
			} else {
				$last = end( $found );
			}
			$offset = $this->storage->serializeOffset( $last, $this->sort );

		} while ( count( $results ) < $numNeeded && ++$queries < self::MAX_QUERIES );

		if ( $queries >= self::MAX_QUERIES ) {
			$count = count( $results );
			$limit = $this->options['pager-limit'];
			wfDebugLog(
				'Flow',
				__METHOD__ . "Reached maximum of $queries queries with $count results of $limit " .
					"requested with query of " . json_encode( $this->query ) . ' and options ' .
					json_encode( $options )
			);
		}

		if ( $results ) {
			return $this->processPage( $results );
		} else {
			return new PagerPage( [], [], $this );
		}
	}

	/**
	 * @param array $results
	 * @return PagerPage
	 * @throws InvalidInputException
	 */
	protected function processPage( array $results ) {
		$pagingLinks = [];

		// Retrieve paging links
		if ( $this->options['pager-dir'] === 'fwd' ) {
			if ( count( $results ) > $this->options['pager-limit'] ) {
				// We got extra, another page exists
				$results = array_slice( $results, 0, $this->options['pager-limit'] );
				$pagingLinks['fwd'] = $this->makePagingLink(
					'fwd',
					end( $results ),
					$this->options['pager-limit']
				);
			}

			if ( $this->options['pager-offset'] !== null ) {
				$pagingLinks['rev'] = $this->makePagingLink(
					'rev',
					reset( $results ),
					$this->options['pager-limit']
				);
			}
		} elseif ( $this->options['pager-dir'] === 'rev' ) {
			if ( count( $results ) > $this->options['pager-limit'] ) {
				// We got extra, another page exists
				$results = array_slice( $results, -$this->options['pager-limit'] );
				$pagingLinks['rev'] = $this->makePagingLink(
					'rev',
					reset( $results ),
					$this->options['pager-limit']
				);
			}

			if ( $this->options['pager-offset'] !== null ) {
				$pagingLinks['fwd'] = $this->makePagingLink(
					'fwd',
					end( $results ),
					$this->options['pager-limit']
				);
			}
		} else {
			throw new InvalidInputException( "Unrecognised direction " . $this->options['pager-dir'], 'invalid-input' );
		}

		return new PagerPage( $results, $pagingLinks, $this );
	}

	/**
	 * @param string $direction
	 * @param object $object
	 * @param int $pageLimit
	 * @return array
	 */
	protected function makePagingLink( $direction, $object, $pageLimit ) {
		$return = [
			'offset-dir' => $direction,
			'limit' => $pageLimit,
			$this->offsetKey => $this->storage->serializeOffset( $object, $this->sort ),
		];
		if ( isset( $this->options['sortby'] ) ) {
			$return['sortby'] = $this->options['sortby'];
		}
		return $return;
	}
}
