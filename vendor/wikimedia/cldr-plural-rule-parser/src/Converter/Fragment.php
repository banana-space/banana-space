<?php
/**
 * @author Niklas Laxström, Tim Starling
 * @license GPL-2.0+
 * @file
 */

namespace CLDRPluralRuleParser\Converter;

use CLDRPluralRuleParser\Error;
use CLDRPluralRuleParser\Converter;

/**
 * Helper for Converter.
 * The base class for operators and expressions, describing a region of the input string.
 */
class Fragment {
	public $parser, $pos, $length, $end;

	function __construct( Converter $parser, $pos, $length ) {
		$this->parser = $parser;
		$this->pos = $pos;
		$this->length = $length;
		$this->end = $pos + $length;
	}

	public function error( $message ) {
		$text = $this->getText();
		throw new Error( "$message at position " . ( $this->pos + 1 ) . ": \"$text\"" );
	}

	public function getText() {
		return substr( $this->parser->rule, $this->pos, $this->length );
	}
}
