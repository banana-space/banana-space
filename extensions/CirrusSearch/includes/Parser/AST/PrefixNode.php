<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitor;

/**
 * A simple word prefix query
 */
class PrefixNode extends ParsedNode {

	/**
	 * @var string
	 */
	private $prefix;

	/**
	 * @param int $startOffset
	 * @param int $endOffset
	 * @param string $prefix
	 */
	public function __construct( $startOffset, $endOffset, $prefix ) {
		parent::__construct( $startOffset, $endOffset );
		$this->prefix = $prefix;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [ 'prefix' => [ array_merge( parent::baseParams(), [ 'prefix' => $this->prefix ] ) ] ];
	}

	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor ) {
		$visitor->visitPrefixNode( $this );
	}
}
