<?php

namespace Flow\Formatter;

use Flow\Model\UUID;

class HeaderViewQuery extends RevisionViewQuery {

	/**
	 * @inheritDoc
	 */
	protected function createRevision( $revId ) {
		if ( !$revId instanceof UUID ) {
			$revId = UUID::create( $revId );
		}

		return $this->storage->get(
			'Header',
			$revId
		);
	}
}
