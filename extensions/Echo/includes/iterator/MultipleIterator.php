<?php

/**
 * Presents a list of iterators as a single stream of results
 * when wrapped with the RecursiveIteratorIterator.
 *
 * This differs from the SPL MultipleIterator in the following ways:
 * * Does not return null for non-valid child iterators
 * * implements RecursiveIterator
 * * Lots less features(e.g. simple!)
 */
class EchoMultipleIterator implements RecursiveIterator {
	protected $active = [];
	protected $children;
	protected $key = 0;

	public function __construct( array $children ) {
		$this->children = $children;
	}

	public function rewind() {
		$this->active = $this->children;
		$this->key = 0;
		foreach ( $this->active as $key => $it ) {
			$it->rewind();
			if ( !$it->valid() ) {
				unset( $this->active[$key] );
			}
		}
	}

	public function valid() {
		return (bool)$this->active;
	}

	public function next() {
		$this->key++;
		foreach ( $this->active as $key => $it ) {
			$it->next();
			if ( !$it->valid() ) {
				unset( $this->active[$key] );
			}
		}
	}

	public function current() {
		$result = [];
		foreach ( $this->active as $it ) {
			$result[] = $it->current();
		}

		return $result;
	}

	public function key() {
		return $this->key;
	}

	public function hasChildren() {
		return (bool)$this->active;
	}

	public function getChildren() {
		// The NotRecursiveIterator is used rather than a RecursiveArrayIterator
		// so that nested arrays dont get recursed.
		return new EchoNotRecursiveIterator( new ArrayIterator( $this->current() ) );
	}
}
