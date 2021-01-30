<?php

namespace Flow\Formatter;

use Flow\Exception\FlowException;
use Flow\Model\UUID;

class PostHistoryQuery extends HistoryQuery {

	/**
	 * @param UUID $postId
	 * @param int $limit
	 * @param UUID|null $offset
	 * @param string $direction 'rev' or 'fwd'
	 * @return FormatterRow[]
	 */
	public function getResults( UUID $postId, $limit = 50, UUID $offset = null, $direction = 'fwd' ) {
		$history = $this->storage->find(
			'PostRevision',
			[ 'rev_type_id' => $postId ],
			$this->getOptions( $direction, $limit, $offset )
		);
		if ( !$history ) {
			return [];
		}

		// See explanation in BoardHistoryQuery::getResults.
		if ( $direction === 'rev' ) {
			$history = array_reverse( $history );
		}

		$this->loadMetadataBatch( $history );
		$results = [];
		foreach ( $history as $revision ) {
			try {
				$results[] = $row = new FormatterRow;
				$this->buildResult( $revision, null, $row );
			} catch ( FlowException $e ) {
				\MWExceptionHandler::logException( $e );
			}
		}

		return $results;
	}

}
