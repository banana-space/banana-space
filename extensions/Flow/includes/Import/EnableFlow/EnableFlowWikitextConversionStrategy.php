<?php

namespace Flow\Import\EnableFlow;

use Flow\Import\Wikitext\ConversionStrategy;
use Title;

class EnableFlowWikitextConversionStrategy extends ConversionStrategy {
	/**
	 * @inheritDoc
	 */
	public function meetsSubpageRequirements( Title $sourceTitle ) {
		// If they're using Special:EnableFlow, they're choosing a specific page
		// one at a time, so assume they know what they're doing.  This allows:
		// * Namespace_talk:Foo/bar even if Namespace:Foo/bar does not exist
		// * Namespace:Baz/bang
		return true;
	}
}
