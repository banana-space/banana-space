<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitor;

/**
 * A phrase
 */
class PhraseQueryNode extends ParsedNode {
	/**
	 * @var string
	 */
	private $phrase;

	/**
	 * @var int
	 */
	private $slop;

	/**
	 * @var bool
	 */
	private $stem;

	/**
	 * @var bool
	 */
	private $unbalanced = false;

	/**
	 * @param int $start
	 * @param int $end
	 * @param string $phrase
	 * @param int $slop the edit distance (in words) allowed between words defined in this query, set to -1
	 * if a specific slop is not specified in the syntax.
	 * @param bool $stem true if the syntax specifies that this phrase should be applied to stem fields
	 */
	public function __construct( $start, $end, $phrase, $slop, $stem ) {
		parent::__construct( $start, $end );
		$this->phrase = $phrase;
		$this->slop = $slop;
		$this->stem = $stem;
	}

	/**
	 * @param int $start
	 * @param int $end
	 * @param string $phrase
	 * @return PhraseQueryNode
	 */
	public static function unbalanced( $start, $end, $phrase ) {
		$node = new self( $start, $end, $phrase, -1, false );
		$node->unbalanced = true;
		return $node;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [
			'phrase' => array_merge( parent::baseParams(), [
				'phrase' => $this->phrase,
				'slop' => $this->slop,
				'stem' => $this->stem,
				'unbalanced' => $this->unbalanced,
			] )
		];
	}

	/**
	 * The phrase
	 * @return string
	 */
	public function getPhrase() {
		return $this->phrase;
	}

	/**
	 * number of words allowed between phrase words
	 * (-1 to use wiki defaults)
	 * @return int
	 */
	public function getSlop() {
		return $this->slop;
	}

	/**
	 * Should this phrase be applied on stem fields
	 * @return bool
	 */
	public function isStem() {
		return $this->stem;
	}

	/**
	 * True if this phrase was created by detecting unbalanced quotes in the query
	 * @return bool
	 */
	public function isUnbalanced() {
		return $this->unbalanced;
	}

	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor ) {
		$visitor->visitPhraseQueryNode( $this );
	}
}
