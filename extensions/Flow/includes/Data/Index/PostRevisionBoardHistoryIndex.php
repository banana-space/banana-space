<?php

namespace Flow\Data\Index;

use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;

class PostRevisionBoardHistoryIndex extends BoardHistoryIndex {
	/**
	 * @param PostRevision $post
	 * @return \Flow\Model\UUID
	 * @suppress PhanParamSignatureMismatch But the signature doesn't match
	 */
	protected function findTopicId( AbstractRevision $post ) {
		return $post->getRootPost()->getPostId();
	}
}
