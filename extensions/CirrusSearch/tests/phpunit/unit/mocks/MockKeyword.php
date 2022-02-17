<?php

namespace CirrusSearch\Test;

use CirrusSearch\Query\SimpleKeywordFeature;
use CirrusSearch\Search\SearchContext;

class MockKeyword extends SimpleKeywordFeature {

	/**
	 * NOTE: will be removed once all implementations implement getKeywordStrings
	 * (transitional state to change the visibility of getKeywords())
	 * @return string[] The list of keywords this feature is supposed to match
	 */
	protected function getKeywords() {
		return [ 'mock1', 'mock2' ];
	}

	/**
	 * Applies the detected keyword from the search term. May apply changes
	 * either to $context directly, or return a filter to be added.
	 *
	 * @param SearchContext $context
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped and escaped
	 *  quotes un-escaped.
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param bool $negated Is the search negated? Not used to generate the returned AbstractQuery,
	 *  that will be negated as necessary. Used for any other building/context necessary.
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		// never called
	}
}
