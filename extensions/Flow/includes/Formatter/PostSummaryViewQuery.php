<?php

namespace Flow\Formatter;

use Flow\Model\UUID;

class PostSummaryViewQuery extends RevisionViewQuery {

	/**
	 * @inheritDoc
	 */
	protected function createRevision( $revId ) {
		if ( !$revId instanceof UUID ) {
			$revId = UUID::create( $revId );
		}

		return $this->storage->get(
			'PostSummary',
			$revId
		);
	}
}
