<?php

namespace CirrusSearch\Iterator;

use Iterator;

/**
 * Applies a callback to all values returned from the iterator
 */
class CallbackIterator extends IteratorDecorator {
	protected $callable;

	public function __construct( Iterator $iterator, $callable ) {
		parent::__construct( $iterator );
		$this->callable = $callable;
	}

	public function current() {
		return call_user_func( $this->callable, $this->iterator->current() );
	}
}
