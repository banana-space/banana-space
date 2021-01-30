<?php

namespace Flow\Data\Index;

use Flow\Exception\DataModelException;

/**
 * Offers direct lookup of an object via a unique feature(set of properties)
 * on the object.
 */
class UniqueFeatureIndex extends FeatureIndex {

	public function getLimit() {
		return 1;
	}

	public function queryOptions() {
		return [ 'LIMIT' => $this->getLimit() ];
	}

	public function limitIndexSize( array $values ) {
		if ( count( $values ) > $this->getLimit() ) {
			throw new DataModelException( 'Unique index should never have more than ' . $this->getLimit() . ' value', 'process-data' );
		}
		return $values;
	}

	protected function removeFromIndex( array $indexed, array $row ) {
		$this->cache->delete( $this->cacheKey( $indexed ) );
	}
}
