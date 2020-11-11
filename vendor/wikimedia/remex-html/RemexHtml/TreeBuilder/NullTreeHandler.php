<?php

namespace RemexHtml\TreeBuilder;

use RemexHtml\Tokenizer\Attributes;

/**
 * A TreeHandler which does nothing
 */
class NullTreeHandler implements TreeHandler {
	function startDocument( $fns, $fn ) {
	}

	function endDocument( $pos ) {
	}

	function characters( $parent, $refNode, $text, $start, $length, $sourceStart, $sourceLength ) {
	}

	function insertElement( $parent, $refNode, Element $element, $void,
		$sourceStart, $sourceLength
	) {
	}

	function endTag( Element $element, $sourceStart, $sourceLength ) {
	}

	function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
	}

	function comment( $parent, $refNode, $text, $sourceStart, $sourceLength ) {
	}

	function error( $text, $pos ) {
	}

	function mergeAttributes( Element $element, Attributes $attrs, $sourceStart ) {
	}

	function removeNode( Element $element, $sourceStart ) {
	}

	function reparentChildren( Element $element, Element $newParent, $sourceStart ) {
	}
}
