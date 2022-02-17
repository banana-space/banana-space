<?php

namespace CirrusSearch\Parser\AST\Visitor;

/**
 * "Visitable" node from the AST
 */
interface Visitable {
	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor );
}
