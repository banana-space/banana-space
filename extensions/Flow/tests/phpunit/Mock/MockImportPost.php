<?php

namespace Flow\Tests\Mock;

use ArrayIterator;
use Flow\Import\IImportPost;
use Flow\Import\IObjectRevision;

class MockImportPost implements IImportPost {
	/**
	 * @var IObjectRevision[]
	 */
	protected $revisions;

	/**
	 * @var IImportPost[]
	 */
	protected $replies;

	/**
	 * @param IObjectRevision[] $revisions
	 * @param IImportPost[] $replies
	 */
	public function __construct( array $revisions, array $replies ) {
		$this->revisions = $revisions;
		$this->replies = $replies;
	}

	/**
	 * @inheritDoc
	 */
	public function getRevisions() {
		return new ArrayIterator( $this->revisions );
	}

	/**
	 * @inheritDoc
	 */
	public function getReplies() {
		return new ArrayIterator( $this->replies );
	}

	/**
	 * @inheritDoc
	 */
	public function getObjectKey() {
		return 'mock-post:1';
	}
}
