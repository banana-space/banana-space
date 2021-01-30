<?php

namespace Flow\Tests\Mock;

use ArrayIterator;
use Flow\Import\IImportHeader;

class MockImportHeader implements IImportHeader {
	/**
	 * @var MockImportRevision
	 */
	protected $revisions;

	/**
	 * @param MockImportRevision[] $revisions
	 */
	public function __construct( array $revisions ) {
		$this->revisions = $revisions;
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
	public function getObjectKey() {
		return 'mock-header:1';
	}
}
