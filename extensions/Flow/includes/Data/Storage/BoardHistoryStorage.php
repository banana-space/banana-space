<?php

namespace Flow\Data\Storage;

use Flow\Exception\DataModelException;

/**
 * SQL backing for BoardHistoryIndex fetches revisions related
 * to a specific TopicList (board workflow)
 * Subclassed for each type that needs it, so each TopKIndex
 * has a distinct backend.
 */
abstract class BoardHistoryStorage extends DbStorage {
	abstract public function find( array $attributes, array $options = [] );

	public function findMulti( array $queries, array $options = [] ) {
		if ( count( $queries ) !== 1 ) {
			throw new DataModelException( __METHOD__ . ' expects exactly one value in $queries', 'process-data' );
		}

		$result = [];
		foreach ( $queries as $i => $attributes ) {
			$result[$i] = $this->find( $attributes, $options );
		}

		$result = RevisionStorage::mergeExternalContent( $result );

		return $result;
	}

	/**
	 * When retrieving revisions from DB, RevisionStorage::mergeExternalContent
	 * will be called to fetch the content. This could fail, resulting in the
	 * content being a 'false' value.
	 *
	 * @inheritDoc
	 */
	public function validate( array $row ) {
		return !isset( $row['rev_content'] ) || $row['rev_content'] !== false;
	}

	public function getPrimaryKeyColumns() {
		return [ 'topic_list_id' ];
	}

	public function insert( array $row ) {
		throw new DataModelException( __CLASS__ . ' does not support insert action', 'process-data' );
	}

	public function update( array $old, array $new ) {
		throw new DataModelException( __CLASS__ . ' does not support update action', 'process-data' );
	}

	public function remove( array $row ) {
		throw new DataModelException( __CLASS__ . ' does not support remove action', 'process-data' );
	}
}
