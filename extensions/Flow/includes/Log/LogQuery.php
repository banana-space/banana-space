<?php

namespace Flow\Log;

use Flow\Formatter\AbstractQuery;
use Flow\Model\PostRevision;
use Flow\Model\UUID;

class LogQuery extends AbstractQuery {
	/**
	 * @param UUID[] $uuids
	 * @suppress PhanParamSignatureMismatch It doesn't match though
	 */
	public function loadMetadataBatch( $uuids ) {
		$posts = $this->loadPostsBatch( $uuids );
		parent::loadMetadataBatch( $posts );
	}

	/**
	 * @param UUID[] $uuids
	 * @return PostRevision[]
	 */
	protected function loadPostsBatch( array $uuids ) {
		$queries = [];
		foreach ( $uuids as $uuid ) {
			$queries[] = [ 'rev_type_id' => $uuid ];
		}

		$found = $this->storage->findMulti(
			'PostRevision',
			$queries,
			[ 'sort' => 'rev_id', 'order' => 'DESC', 'limit' => 1 ]
		);

		$revisions = [];
		foreach ( $found as $result ) {
			/** @var PostRevision $revision */
			$revision = reset( $result );
			$revisions[$revision->getPostId()->getAlphadecimal()] = $revision;
		}

		return $revisions;
	}
}
