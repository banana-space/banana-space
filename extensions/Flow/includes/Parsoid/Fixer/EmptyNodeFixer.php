<?php

namespace Flow\Parsoid\Fixer;

use Flow\Parsoid\Fixer;

/**
 * We use saveXML() instead of saveHTML() to serialize our HTML DOMs, to work around bugs
 * in saveHTML(). However, saveXML() serializes all empty tags as self-closing tags.
 * This is correct for some HTML tags (like <img>) but breaks for others (like <a>).
 * We can pass LIBXML_NOEMPTYTAG to saveXML(), but then no self-closing tags will ever be
 * generated, and we get output like <img></img>. We want self-closing tags for "void" elements
 * (like <img />), but non-self-closing tags for other elements (like <a></a>).
 *
 * This fixer accomplishes this by inserting an empty text node into every childless non-void tag.
 * It is not used to map Parsoid HTML to display HTML like the other fixers are; instead it's used
 * whenever we serialize a DOM.
 */
class EmptyNodeFixer implements Fixer {
	/**
	 * Returns XPath matching elements that need to be transformed
	 *
	 * @return string XPath of elements this acts on
	 */
	public function getXPath() {
		// List from https://www.w3.org/TR/2011/WD-html-markup-20110113/syntax.html#void-elements
		$voidPattern = "self::area or self::base or self::br or self::col or self::command or " .
			"self::embed or self::hr or self::img or self::input or self::keygen or self::link or " .
			"self::meta or self::param or self::source or self::track or self::wbr";
		// Find empty non-void elements
		$pattern = "//*[not($voidPattern)][not(node())]";
		return $pattern;
	}

	/**
	 * Adds an empty text node to an element.
	 *
	 * @param \DOMNode $node
	 * @param \Title $title
	 */
	public function apply( \DOMNode $node, \Title $title ) {
		if ( !$node instanceof \DOMElement ) {
			return;
		}

		$node->appendChild( $node->ownerDocument->createTextNode( '' ) );
	}
}
