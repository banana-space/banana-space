<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitor;
use Wikimedia\Assert\Assert;

/**
 * Negated node
 * NOTE: may not appear in any resulting query AST,
 * NegatedNode are always removed and attached to a BooleanClause
 * as MUST_NOT.
 */
class NegatedNode extends ParsedNode {

	/**
	 * @var ParsedNode
	 */
	private $child;

	/**
	 * @var string type of negation used
	 */
	private $negationType;

	/**
	 * @param int $startOffset
	 * @param int $endOffset
	 * @param ParsedNode $child
	 * @param string $negationType
	 */
	public function __construct( $startOffset, $endOffset, ParsedNode $child, $negationType ) {
		Assert::parameter( self::validNegationType( $negationType ), 'negationType', 'Invalid negation type provided' );
		Assert::parameter( self::validNegationType( $negationType ), 'negationType', 'Invalid negation type provided' );
		parent::__construct( $startOffset, $endOffset );
		$this->child = $child;
		$this->negationType = $negationType;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [ 'not' => array_merge( parent::baseParams(), [
			'negation_type' => $this->negationType,
			'child' => $this->child->toArray(),
		] ) ];
	}

	/**
	 * @return ParsedNode
	 */
	public function getChild() {
		return $this->child;
	}

	/**
	 * Check whether this negation type is valid
	 * @param string $negationType
	 * @return bool
	 */
	public static function validNegationType( $negationType ) {
		return $negationType === '!' || $negationType === '-' || $negationType === 'NOT';
	}

	/**
	 * @return string type of negation used (NOT, ! or -)
	 */
	public function getNegationType() {
		return $this->negationType;
	}

	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor ) {
		$visitor->visitNegatedNode( $this );
	}
}
