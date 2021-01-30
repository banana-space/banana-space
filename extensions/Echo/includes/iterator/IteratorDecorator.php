<?php

/**
 * Allows extending classes to decorate an Iterator with
 * reduced boilerplate.
 */
abstract class EchoIteratorDecorator implements Iterator {
	protected $iterator;

	public function __construct( Iterator $iterator ) {
		$this->iterator = $iterator;
	}

	public function current() {
		return $this->iterator->current();
	}

	public function key() {
		return $this->iterator->key();
	}

	public function next() {
		return $this->iterator->next();
	}

	public function rewind() {
		return $this->iterator->rewind();
	}

	public function valid() {
		return $this->iterator->valid();
	}
}
