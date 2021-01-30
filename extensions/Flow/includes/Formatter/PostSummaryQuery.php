<?php

namespace Flow\Formatter;

use Flow\Model\UUID;

class PostSummaryQuery extends AbstractQuery {
	/**
	 * @param UUID $postId
	 * @return FormatterRow|null
	 */
	public function getResult( UUID $postId ) {
		$found = $this->storage->find(
			'PostSummary',
			[ 'rev_type_id' => $postId ],
			[ 'sort' => 'rev_id', 'order' => 'DESC', 'limit' => 1 ]
		);
		if ( !$found ) {
			return null;
		}
		$this->loadMetadataBatch( $found );

		return $this->buildResult( reset( $found ), 'rev_id' );
	}
}
