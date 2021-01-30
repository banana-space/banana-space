<?php

// phpcs:disable Generic.Files.LineLength -- Long html test examples

namespace Flow\Tests\Parsoid;

use Flow\Parsoid\ContentFixer;
use Flow\Parsoid\Fixer\EmptyNodeFixer;
use Title;

/**
 * @covers \Flow\Parsoid\Fixer\EmptyNodeFixer
 *
 * @group Flow
 */
class EmptyNodeFixerTest extends \MediaWikiTestCase {

	public function testEmptyNodeFixer() {
		$html = '<body><p><a id="notempty">Hello</a><a id="empty"></a><a id="image"><img src="foo"></a></p></body>';
		$dom = ContentFixer::createDOM( $html );
		$notemptyLink = $dom->getElementById( 'notempty' );
		$emptyLink = $dom->getElementById( 'empty' );
		$imageLink = $dom->getElementById( 'image' );
		$imageNode = $dom->getElementsByTagName( 'img' )->item( 0 );

		$this->assertEquals( $notemptyLink->childNodes->length, 1, 'non-empty link has one child before fixer' );
		$this->assertEquals( $emptyLink->childNodes->length, 0, 'empty link has no children before fixer' );
		$this->assertEquals( $imageLink->childNodes->length, 1, 'image link has one child before fixer' );
		$this->assertEquals( $imageNode->childNodes->length, 0, 'img has no children before fixer' );

		$fixer = new ContentFixer( new EmptyNodeFixer );
		$fixer->applyToDom( $dom, Title::newMainPage() );

		$this->assertEquals( $notemptyLink->childNodes->length, 1, 'non-empty link has one child after fixer' );
		$this->assertEquals( $emptyLink->childNodes->length, 1, 'empty link has one child after fixer' );
		$this->assertEquals( $emptyLink->childNodes->item( 0 )->nodeType, XML_TEXT_NODE, 'empty link child is a text node' );
		$this->assertEquals( $emptyLink->childNodes->item( 0 )->data, '', 'empty link child text node is empty' );
		$this->assertEquals( $imageLink->childNodes->length, 1, 'image link has one child after fixer' );
		$this->assertEquals( $imageNode->childNodes->length, 0, 'img has no children after fixer' );
	}
}
