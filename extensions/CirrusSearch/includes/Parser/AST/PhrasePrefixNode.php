<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitor;

/**
 * A phrase prefix.
 */
class PhrasePrefixNode extends ParsedNode {

	/**
	 * @var string
	 */
	private $phrase;

	/**
	 * @param int $startOffset
	 * @param int $endOffset
	 * @param string $phrase
	 */
	public function __construct( $startOffset, $endOffset, $phrase ) {
		parent::__construct( $startOffset, $endOffset );
		$this->phrase = $phrase;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [
			"phrase_prefix" => array_merge( parent::baseParams(), [
				'phrase' => $this->phrase
			] )
		];
	}

	/**
	 * @return string
	 */
	public function getPhrase() {
		return $this->phrase;
	}

	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor ) {
		$visitor->visitPhrasePrefixNode( $this );
	}
}
