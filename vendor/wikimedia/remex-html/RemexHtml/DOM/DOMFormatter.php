<?php

namespace RemexHtml\DOM;

interface DOMFormatter {
	/**
	 * Recursively format a DOMNode.
	 *
	 * @param DOMNode $node The node to format
	 * @return string
	 */
	function formatDOMNode( \DOMNode $node );

	/**
	 * Non-recursively format a DOMElement.
	 *
	 * @param DOMElement $element The element to format
	 * @param string $contents The formatted contents of the element
	 */
	function formatDOMElement( \DOMElement $element, $contents );
}
