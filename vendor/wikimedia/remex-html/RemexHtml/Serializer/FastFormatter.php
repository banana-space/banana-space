<?php

namespace RemexHtml\Serializer;

/**
 * A formatter suitable for pre-sanitized input with ignoreEntities enabled
 * in the Tokenizer.
 */
class FastFormatter implements Formatter {
	function __construct( $options = [] ) {
	}

	function startDocument( $fragmentNamespace, $fragmentName ) {
		if ( $fragmentNamespace === null ) {
			return "<!DOCTYPE html>\n";
		} else {
			return '';
		}
	}

	function doctype( $name, $public, $system ) {
	}

	function characters( SerializerNode $parent, $text, $start, $length ) {
		return substr( $text, $start, $length );
	}

	function element( SerializerNode $parent, SerializerNode $node, $contents ) {
		$name = $node->name;
		$ret = "<$name";
		foreach ( $node->attrs->getValues() as $attrName => $value ) {
			$ret .= " $attrName=\"$value\"";
		}
		if ( $contents === null ) {
			$ret .= "/>";
		} elseif ( isset( $contents[0] ) && $contents[0] === "\n"
			&& in_array( $name, [ 'pre', 'textarea', 'listing' ] )
		) {
			$ret .= ">\n$contents</$name>";
		} else {
			$ret .= ">$contents</$name>";
		}
		return $ret;
	}

	function comment( SerializerNode $parent, $text ) {
		return "<!--$text-->";
	}
}
