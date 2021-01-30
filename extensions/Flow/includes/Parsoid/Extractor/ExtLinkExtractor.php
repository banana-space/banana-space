<?php

namespace Flow\Parsoid\Extractor;

use DOMElement;
use Flow\Model\Reference;
use Flow\Parsoid\Extractor;
use Flow\Parsoid\ReferenceFactory;

/**
 * Finds and creates References for external links in parsoid HTML
 */
class ExtLinkExtractor implements Extractor {
	/**
	 * @inheritDoc
	 */
	public function getXPath() {
		return '//a[@rel="mw:ExtLink"]';
	}

	/**
	 * @inheritDoc
	 */
	public function perform( ReferenceFactory $factory, DOMElement $element ) {
		return $factory->createUrlReference(
			Reference::TYPE_LINK,
			urldecode( $element->getAttribute( 'href' ) )
		);
	}
}
