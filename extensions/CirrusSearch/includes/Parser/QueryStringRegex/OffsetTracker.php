<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use Wikimedia\Assert\Assert;

/**
 * Simple class to track offsets
 * Supports only simple cases (no support for overlapping offsets)
 */
class OffsetTracker {

	/**
	 * Always ordered with no overlapping offsets
	 * @var int[] array of end offsets indexed by start offsets
	 */
	private $offsets = [];

	/**
	 * @param int $start start offset (inclusive)
	 * @param int $end end offset (exclusive)
	 */
	public function append( $start, $end ) {
		Assert::precondition( $start < $end, '$start < $end' );
		$this->offsets[$start] = $end;
		ksort( $this->offsets );
	}

	/**
	 * Append an array of ParsedNode into the tracker.
	 * The array must not contain any overlapping node.
	 * @param \CirrusSearch\Parser\AST\ParsedNode[] $nodes
	 */
	public function appendNodes( array $nodes ) {
		foreach ( $nodes as $node ) {
			// slow: Assert::invariant( $this->overlap( $node->getStartOffset(), $node->getEndOffset() ) );
			$this->offsets[$node->getStartOffset()] = $node->getEndOffset();
		}
		ksort( $this->offsets );
	}

	/**
	 * @param int $start
	 * @param int $end
	 * @return bool
	 */
	public function overlap( $start, $end ) {
		Assert::precondition( $start < $end,  '$start < $end' );
		foreach ( $this->offsets as $s => $e ) {
			if ( $e > $start && $s < $end ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return int[]
	 */
	public function getOffsets() {
		return $this->offsets;
	}

	/**
	 * return this first unconsumed offset in this tracker
	 * greater than $offset
	 * @param int $offset
	 * @return int
	 */
	public function getMinimalUnconsumedOffset( $offset = 0 ) {
		Assert::precondition( $offset >= 0, '$offset >= 0' );
		foreach ( $this->offsets as $start => $end ) {
			if ( $offset < $start ) {
				return $offset;
			}
			$offset = $end > $offset ? $end : $offset;
		}
		return $offset;
	}
}
