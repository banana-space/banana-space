<?php

namespace Flow\Model;

use Flow\Collection\PostSummaryCollection;
use Title;
use User;

class PostSummary extends AbstractSummary {

	/**
	 * @param Title $title
	 * @param PostRevision $post
	 * @param User $user
	 * @param string $content
	 * @param string $format wikitext|html
	 * @param string $changeType
	 * @return PostSummary
	 */
	public static function create( Title $title, PostRevision $post, User $user, $content, $format, $changeType ) {
		$obj = new self;
		$obj->revId = UUID::create();
		$obj->user = UserTuple::newFromUser( $user );
		$obj->prevRevision = null;
		$obj->changeType = $changeType;
		$obj->summaryTargetId = $post->getPostId();
		$obj->setContent( $content, $format, $title );
		return $obj;
	}

	/**
	 * @return string
	 */
	public function getRevisionType() {
		return 'post-summary';
	}

	/**
	 * @return PostSummaryCollection
	 */
	public function getCollection() {
		return PostSummaryCollection::newFromRevision( $this );
	}
}
