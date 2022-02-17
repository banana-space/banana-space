<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitor;

/**
 * Empty query node (we could not parse anything useful)
 */
class EmptyQueryNode extends ParsedNode {

	/**
	 * @return array
	 */
	public function toArray() {
		return [ 'empty' => parent::baseParams() ];
	}

	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor ) {
		$visitor->visitEmptyQueryNode( $this );
	}
}
