<?php

namespace Flow\Parsoid\Extractor;

use DOMElement;
use Flow\Model\Reference;
use Flow\Parsoid\Extractor;
use Flow\Parsoid\ReferenceFactory;

/**
 * Finds and creates References for internal wiki links in parsoid HTML
 */
class WikiLinkExtractor implements Extractor {
	/**
	 * @inheritDoc
	 */
	public function getXPath() {
		return '//a[@rel="mw:WikiLink"][not(@typeof)]';
	}

	/**
	 * @inheritDoc
	 */
	public function perform( ReferenceFactory $factory, DOMElement $element ) {
		$href = $element->getAttribute( 'href' );
		if ( $href === '' ) {
			return null;
		}

		return $factory->createWikiReference(
			Reference::TYPE_LINK,
			urldecode( $href )
		);
	}
}
