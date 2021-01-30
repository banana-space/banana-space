<?php

namespace Flow\Parsoid\Extractor;

use DOMElement;
use Flow\Model\WikiReference;
use Flow\Parsoid\Extractor;
use Flow\Parsoid\ReferenceFactory;

/**
 * Runs against page content via Flow\Parsoid\ReferenceExtractor
 * and collects all category references output by parsoid. The DOM
 * spec states that categories are represented as such:
 *
 *   <link rel="mw:PageProp/Category" href="...">
 *
 * Flow does not currently handle the other page properties. When it becomes
 * necessary a more generic page property extractor should be implemented and
 * this class should be removed.
 */
class CategoryExtractor implements Extractor {
	/**
	 * @inheritDoc
	 */
	public function getXPath() {
		return '//link[starts-with( @rel, "mw:PageProp/Category" )]';
	}

	/**
	 * @inheritDoc
	 */
	public function perform( ReferenceFactory $factory, DOMElement $element ) {
		// our provided xpath guarantees there is a rel attribute
		// with our expected format so only perform a very minimal
		// validation.
		$rel = $element->getAttribute( 'rel' );
		if ( $rel !== 'mw:PageProp/Category' ) {
			return null;
		}

		$href = $element->getAttribute( 'href' );
		if ( $href ) {
			return $factory->createWikiReference(
				WikiReference::TYPE_CATEGORY,
				urldecode( $href )
			);
		} else {
			return null;
		}
	}
}
