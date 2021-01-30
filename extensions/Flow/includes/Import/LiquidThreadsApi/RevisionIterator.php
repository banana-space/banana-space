<?php

namespace Flow\Import\LiquidThreadsApi;

use Flow\Import\IImportObject;
use Iterator;

/**
 * Iterates over the revisions of a foreign page to produce
 * revisions of a Flow object.
 */
class RevisionIterator implements Iterator {
	/** @var array */
	protected $pageData;

	/** @var int */
	protected $pointer;

	/** @var IImportObject */
	protected $parent;

	/** @var callable */
	protected $factory;

	public function __construct( array $pageData, IImportObject $parent, callable $factory ) {
		$this->pageData = $pageData;
		$this->pointer = 0;
		$this->parent = $parent;
		$this->factory = $factory;
	}

	protected function getRevisionCount() {
		if ( isset( $this->pageData['revisions'] ) ) {
			return count( $this->pageData['revisions'] );
		} else {
			return 0;
		}
	}

	public function valid() {
		return $this->pointer < $this->getRevisionCount();
	}

	public function next() {
		++$this->pointer;
	}

	public function key() {
		return $this->pointer;
	}

	public function rewind() {
		$this->pointer = 0;
	}

	public function current() {
		return ( $this->factory )( $this->pageData['revisions'][$this->pointer], $this->parent );
	}
}
