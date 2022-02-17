<?php

namespace CirrusSearch\Parser\AST\Visitor;

use CirrusSearch\Parser\AST\BooleanClause;
use CirrusSearch\Parser\AST\NamespaceHeaderNode;
use CirrusSearch\Parser\AST\NegatedNode;
use CirrusSearch\Parser\AST\ParsedBooleanNode;
use Wikimedia\Assert\Assert;

/**
 * Visit leaves only
 */
abstract class LeafVisitor implements Visitor {
	/**
	 * @var int[]
	 */
	private $excludeOccurs;

	/**
	 * @var bool true when this branch is "negated".
	 */
	private $inNegation;

	/**
	 * @param int[] $excludeOccurs
	 */
	public function __construct( $excludeOccurs = [] ) {
		array_walk( $excludeOccurs, function ( $x ) {
			BooleanClause::validateOccur( $x );
		} );
		$this->excludeOccurs = $excludeOccurs;
	}

	/**
	 * @param ParsedBooleanNode $node
	 */
	final public function visitParsedBooleanNode( ParsedBooleanNode $node ) {
		foreach ( $node->getClauses() as $clause ) {
			$clause->accept( $this );
		}
	}

	/**
	 * @param NegatedNode $node
	 */
	final public function visitNegatedNode( NegatedNode $node ) {
		/** @phan-suppress-next-line PhanImpossibleCondition I agree, this is impossible. */
		Assert::invariant( false, 'NegatedNode should be optimized at parse time' );
	}

	/**
	 * @param NamespaceHeaderNode $node
	 */
	final public function visitNamespaceHeader( NamespaceHeaderNode $node ) {
		/** @phan-suppress-next-line PhanImpossibleCondition I agree, this is impossible. */
		Assert::invariant( false, 'Not yet part of the AST, should not be visited.' );
	}

	/**
	 * @param BooleanClause $node
	 */
	final public function visitBooleanClause( BooleanClause $node ) {
		if ( in_array( $node->getOccur(), $this->excludeOccurs ) ) {
			return;
		}

		$oldNegated = $this->inNegation;
		if ( $node->getOccur() === BooleanClause::MUST_NOT ) {
			$this->inNegation = !$this->inNegation;
		}

		$node->getNode()->accept( $this );
		$this->inNegation = $oldNegated;
	}

	/**
	 * @return bool true if this node is in a negation
	 */
	final public function negated() {
		return $this->inNegation;
	}
}
