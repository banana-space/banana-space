<?php

namespace CirrusSearch\Test;

use CirrusSearch\Query\LegacyKeywordFeature;
use CirrusSearch\Query\SimpleKeywordFeature;
use CirrusSearch\Search\SearchContext;

class MockSimpleKeywordFeature extends SimpleKeywordFeature implements LegacyKeywordFeature {
	private $calls = [];

	protected function getKeywords() {
		return [ 'mock', 'mock2' ];
	}

	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$this->calls[] = [ $key, $value, $quotedValue, $negated ];
	}

	public function getApplyCallArguments() {
		return $this->calls;
	}
}
