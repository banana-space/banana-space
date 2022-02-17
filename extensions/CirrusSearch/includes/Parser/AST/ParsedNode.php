<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitable;
use Wikimedia\Assert\Assert;

/**
 * Base class representing a "node" in the query AST.
 */
abstract class ParsedNode implements Visitable {
	/**
	 * @var int
	 */
	private $startOffset;

	/**
	 * @var int
	 */
	private $endOffset;

	/**
	 * @param int $startOffset (incl)
	 * @param int $endOffset (excl)
	 */
	public function __construct( $startOffset, $endOffset ) {
		Assert::precondition( $startOffset <= $endOffset, '$startOffset <= $endOffset' );
		$this->startOffset = $startOffset;
		$this->endOffset = $endOffset;
	}

	/**
	 * @return int
	 */
	public function getStartOffset() {
		return $this->startOffset;
	}

	/**
	 * @return int
	 */
	public function getEndOffset() {
		return $this->endOffset;
	}

	/**
	 * @return array
	 */
	abstract public function toArray();

	/**
	 * @return array
	 */
	protected function baseParams() {
		return [
			'startOffset' => $this->startOffset,
			'endOffset' => $this->endOffset,
		];
	}
}
