<?php

namespace Flow\Tests\Mock;

use Flow\Import\IObjectRevision;
use User;

class MockImportRevision implements IObjectRevision {
	/**
	 * @var array
	 */
	protected $attribs;

	/**
	 * @param array $attribs
	 */
	public function __construct( array $attribs = [] ) {
		$this->attribs = $attribs + [
			'text' => 'dvorak',
			'timestamp' => time(),
			'author' => User::newFromName( '127.0.0.1', false ),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getText() {
		return $this->attribs['text'];
	}

	/**
	 * @inheritDoc
	 */
	public function getTimestamp() {
		return $this->attribs['timestamp'];
	}

	/**
	 * @inheritDoc
	 */
	public function getAuthor() {
		return $this->attribs['author'];
	}

	/**
	 * @inheritDoc
	 */
	public function getObjectKey() {
		return 'mock-revision:1';
	}
}
