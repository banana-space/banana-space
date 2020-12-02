<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Cite;

use DOMElement;
use Wikimedia\Parsoid\Ext\DOMProcessor;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * wt -> html DOM PostProcessor
 */
class RefProcessor extends DOMProcessor {

	/**
	 * @inheritDoc
	 */
	public function wtPostprocess(
		ParsoidExtensionAPI $extApi, DOMElement $body, array $options, bool $atTopLevel
	): void {
		if ( $atTopLevel ) {
			$refsData = new ReferencesData();
			References::processRefs( $extApi, $refsData, $body );
			References::insertMissingReferencesIntoDOM( $extApi, $refsData, $body );
		}
	}

	// FIXME: should implement an htmlPreprocess method as well.
}
