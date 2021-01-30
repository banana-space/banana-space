<?php

namespace Flow\Parsoid\Extractor;

use DOMElement;
use Flow\Model\WikiReference;
use Flow\Parsoid\Extractor;
use Flow\Parsoid\ReferenceFactory;
use FormatJson;
use Title;

/**
 * Finds and creates References for transclusions in parsoid HTML
 */
class TransclusionExtractor implements Extractor {
	/**
	 * @inheritDoc
	 */
	public function getXPath() {
		return '//*[@typeof="mw:Transclusion"]';
	}

	/**
	 * @inheritDoc
	 */
	public function perform( ReferenceFactory $factory, DOMElement $element ) {
		$orig = $element->getAttribute( 'data-mw' );
		$data = FormatJson::decode( $orig );
		if ( !isset( $data->parts ) || !is_array( $data->parts ) ) {
			throw new \Exception( "Missing template target: $orig" );
		}
		$target = null;
		foreach ( $data->parts as $part ) {
			if ( isset( $part->template->target->wt ) ) {
				$target = $part->template->target->wt;
				break;
			}
		}
		if ( $target === null ) {
			throw new \Exception( "Missing template target: $orig" );
		}
		$templateTarget = Title::newFromText( $target, NS_TEMPLATE );

		if ( !$templateTarget ) {
			return null;
		}

		return $factory->createWikiReference(
			WikiReference::TYPE_TEMPLATE,
			$templateTarget->getPrefixedText()
		);
	}
}
