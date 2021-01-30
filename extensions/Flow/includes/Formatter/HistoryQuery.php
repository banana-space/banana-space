<?php

namespace Flow\Formatter;

use Flow\Data\ManagerGroup;
use Flow\FlowActions;
use Flow\Model\AbstractRevision;
use Flow\Model\UUID;
use Flow\Repository\TreeRepository;

abstract class HistoryQuery extends AbstractQuery {
	// This requests extra to take into account that we will filter some out,
	// to try to reduce the number of rounds (preferably to 1).
	// If you raise this, also increase FLOW_HISTORY_INDEX_LIMIT and bump the
	// key of the indexes using FLOW_HISTORY_INDEX_LIMIT
	// This magic number is based on new-post/new-topic being about 26% of post revisions.
	// (queried from production), since that is the only thing currently excluded.
	protected const POST_OVERFETCH_FACTOR = 1.36;

	/**
	 * @var FlowActions
	 */
	protected $actions;

	/**
	 * @param ManagerGroup $storage
	 * @param TreeRepository $treeRepo
	 * @param FlowActions $actions
	 */
	public function __construct(
		ManagerGroup $storage,
		TreeRepository $treeRepo,
		FlowActions $actions
	) {
		parent::__construct( $storage, $treeRepo );
		$this->actions = $actions;
	}

	/**
	 * @param AbstractRevision $revision
	 * @return bool
	 */
	protected function includeInHistory( AbstractRevision $revision ) {
		// If you add exclude_from_history to a new type, use doInternalQueries on additional
		// queries as needed.
		return !$this->actions->getValue( $revision->getChangeType(), 'exclude_from_history' );
	}

	/**
	 * Gets query options that are common to all history queries
	 *
	 * @param string $direction 'fwd' or 'rev'.  'fwd' means to get items older than
	 *  the offset.  'rev' means to get items newer.  Either way, an individual page is
	 *  eventually returned and displayed in descending order.
	 * @param int $limit Maximum number of items
	 * @param UUID|null $offset UUID to use as offset (optional)
	 * @return array Associative array of options for query
	 */
	protected function getOptions( $direction, $limit, UUID $offset = null ) {
		return [
			'sort' => 'rev_id',
			'order' => $direction === 'fwd' ? 'DESC' : 'ASC',
			'limit' => $limit,
			'offset-id' => $offset,
			'offset-dir' => $direction,
			'offset-include' => false,
		];
	}

	/**
	 * Internally re-query as needed to handle items excluded from history
	 *
	 * Re-queries until there are no more entries or after filtering, there are the
	 * desired number of results.
	 *
	 * This respects the given order (ASC or DESC), but the reversing for 'rev' is in
	 * getResults.
	 *
	 * @param string $storageClass Storage class ID
	 * @param array $attributes Query attriutes
	 * @param array $options Query options, including offset-id and limit
	 * @param float $overfetchFactor Factor to overfetch by to anticipate excludes
	 * @return array Array of history rows
	 */
	protected function doInternalQueries( $storageClass, $attributes, $options, $overfetchFactor ) {
		$result = [];

		$limit = $options['limit'];
		$internalOffset = $options['offset-id'];

		do {
			$remainingNeeded = $limit - count( $result );

			// The special cases here are to try reduce dribbling out of final requests (50, 25, 10, 5...).
			if ( $remainingNeeded < 50 ) {
				$overfetchFactor *= 2;
			}

			$beforeFilteringCountWanted = max( 10, intval( $overfetchFactor * $remainingNeeded ) );

			// Over-fetch by 1 item so we can figure out when to stop re-querying.
			$options['limit'] = $beforeFilteringCountWanted + 1;

			$options['offset-id'] = $internalOffset;

			$resultBeforeFiltering = $this->storage->find( $storageClass, $attributes, $options );

			// We over-fetched, now get rid of redundant value for our "real" data
			$internalOverfetched = null;
			if ( count( $resultBeforeFiltering ) > $beforeFilteringCountWanted ) {
				$internalOverfetched = array_pop( $resultBeforeFiltering );
			}

			$resultAfterFiltering = array_filter( $resultBeforeFiltering, [ $this, 'includeInHistory' ] );

			if ( count( $resultBeforeFiltering ) >= 1 ) {
				$internalOffset = end( $resultBeforeFiltering )->getRevisionId();
			}

			$trimmedResultAfterFiltering = array_slice( $resultAfterFiltering, 0, $remainingNeeded );
			$result = array_merge( $result, $trimmedResultAfterFiltering );
		} while ( count( $result ) < $limit && $internalOverfetched !== null );

		return $result;
	}

}
