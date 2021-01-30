<?php

/**
 * Wraps a non-recursive iterator with methods to be recursive
 * without children.
 *
 * Alternatively wraps a recursive iterator to prevent recursing deeper
 * than the wrapped iterator.
 */
class EchoNotRecursiveIterator extends EchoIteratorDecorator implements RecursiveIterator {
	public function hasChildren() {
		return false;
	}

	public function getChildren() {
		// @phan-suppress-next-line PhanTypeMismatchReturn Never called
		return null;
	}
}
