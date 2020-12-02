<?php

namespace Test\Parsoid\Utils;

use DOMDocument;
use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Utils\DOMPostOrder;

class DOMPostOrderTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @covers \Wikimedia\Parsoid\Utils\DOMPostOrder::traverse
	 */
	public function testTraverse() {
		$trace = [];

		$html = <<<'HTML'
<html><body>
	<div id="x1">
		<div id="x1_1"></div>
		<div id="x1_2"></div>
	</div>
	<div id="x2">
		<div id="x2_1"></div>
	</div>
</body></html>
HTML;
		$doc = new DOMDocument();
		$doc->loadHTML( $html );

		DOMPostOrder::traverse( $doc->documentElement, function ( DOMNode $node ) use ( &$trace ) {
			if ( $node instanceof DOMElement && $node->hasAttribute( 'id' ) ) {
				$trace[] = $node->getAttribute( 'id' );
			}
		} );

		$this->assertSame( [ 'x1_1', 'x1_2', 'x1', 'x2_1', 'x2' ], $trace );
	}

}
