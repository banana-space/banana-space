<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitor;

/**
 * A boolean expression.
 *
 * This "boolean expression" is suited for matching document in search. It's not really
 * describing a binary tree as one would use to a compute a boolean algebra.
 * It is a flat list of clauses where the clauses are not connected to each others but
 * rather describe how individual clause must occur in a given document:
 * - MUST
 * - MUST_NOT
 * - SHOULD
 *
 * There is a direct relationship between boolean algebra and this representation:
 * - A AND B: [ MUST:A, MUST:B ]
 * - A OR B: [ SHOULD:A, SHOULD:B ]
 * - A AND NOT B: [ MUST:A, MUST_NOT: B ]
 *
 * But it supports describing something that is impossible to describe in a boolean algebra:
 * - [ MUST:A, SHOULD:B ]: here the boolean algebra is not suited since SHOULD:B is pointless
 *   but for search it can be considered as a "scoring hint", i.e. give me all docs matching A but
 *   it's "better" to have docs matching B as well. When filtering the search backend can silently
 *   ignore the SHOULD:B but when scoring it will be taken into account.
 *
 * In the end this representation makes it possible to support a syntax like:
 * word1 +word2 -word3: [ SHOULD:word1, MUST:word2, MUST_NOT:word3 ]
 * which would not be possible with a binary expression tree
 */
class ParsedBooleanNode extends ParsedNode {
	/**
	 * @var BooleanClause[]
	 */
	private $clauses;

	/**
	 * @param int $startOffset
	 * @param int $endOffset
	 * @param BooleanClause[] $children
	 */
	public function __construct( $startOffset, $endOffset, array $children ) {
		parent::__construct( $startOffset, $endOffset );
		$this->clauses = $children;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [ "bool" => array_merge(
			parent::baseParams(),
			[
				'clauses' => array_map(
					function ( BooleanClause $clause ) {
						return [
							$clause->getOccur() => $clause->getNode()->toArray(),
							"explicit" => $clause->isExplicit(),
						];
					},
					$this->clauses
				)
			]
		) ];
	}

	/**
	 * @return BooleanClause[]
	 */
	public function getClauses() {
		return $this->clauses;
	}

	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor ) {
		$visitor->visitParsedBooleanNode( $this );
	}
}
