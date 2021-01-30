<?php

namespace Flow\Import\LiquidThreadsApi;

use Iterator;

class ReplyIterator implements Iterator {
	/** @var ImportPost */
	protected $post;
	/** @var array Array of thread IDs */
	protected $threadReplies;
	/** @var int */
	protected $replyIndex;
	/** @var ImportPost|null */
	protected $current;

	public function __construct( ImportPost $post ) {
		$this->post = $post;
		$this->replyIndex = 0;

		$apiResponse = $post->getApiResponse();
		$this->threadReplies = array_values( $apiResponse['replies'] );
	}

	/**
	 * @return ImportPost|null
	 */
	public function current() {
		return $this->current;
	}

	/**
	 * @return int
	 */
	public function key() {
		return $this->replyIndex;
	}

	public function next() {
		while ( ++$this->replyIndex < count( $this->threadReplies ) ) {
			try {
				$replyId = $this->threadReplies[$this->replyIndex]['id'];
				$this->current = $this->post->getSource()->getPost( $replyId );

				return;
			} catch ( ApiNotFoundException $e ) {
				// while loop fall-through handles our error case
			}
		}

		// Nothing found, set current to null
		$this->current = null;
	}

	public function rewind() {
		$this->replyIndex = -1;
		$this->next();
	}

	public function valid() {
		return $this->current !== null;
	}
}
