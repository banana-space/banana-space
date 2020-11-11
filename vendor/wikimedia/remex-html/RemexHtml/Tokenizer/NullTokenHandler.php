<?php

namespace RemexHtml\Tokenizer;

/**
 * A TokenHandler which does nothing
 */
class NullTokenHandler implements TokenHandler {
	function startDocument( Tokenizer $t, $fns, $fn ) {
	}

	function endDocument( $pos ) {
	}

	function error( $text, $pos ) {
	}

	function characters( $text, $start, $length, $sourceStart, $sourceLength ) {
	}

	function startTag( $name, Attributes $attrs, $selfClose,
	   $sourceStart, $sourceLength
	) {
	}

	function endTag( $name, $sourceStart, $sourceLength ) {
	}

	function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
	}

	function comment( $text, $sourceStart, $sourceLength ) {
	}
}
