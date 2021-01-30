<?php

namespace Flow\Parsoid;

use DOMElement;
use Flow\Model\Reference;

/**
 * Find and create References for DOM elements within parsoid HTML
 */
interface Extractor {
	/**
	 * XPath selector that finds elements to be processed with self::perform
	 *
	 * @return string
	 */
	public function getXPath();

	/**
	 * Generate one or no references for a DOMElement found with self::getXPath
	 *
	 * @param ReferenceFactory $factory
	 * @param DOMElement $element
	 * @return Reference|null
	 */
	public function perform( ReferenceFactory $factory, DOMElement $element );
}
