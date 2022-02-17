<?php

namespace CirrusSearch\Query;

use CirrusSearch\Search\SearchContext;

/**
 * Legacy keyword markup interface for keywords
 * that still work by manipulating the SearchContext
 */
interface LegacyKeywordFeature {

	/**
	 * Fully featured apply method which delegates to doApply by default.
	 *
	 * @param SearchContext $context
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped and escaped
	 *  quotes un-escaped.
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param bool $negated Is the search negated? Not used to generate the returned AbstractQuery,
	 *  that will be negated as necessary. Used for any other building/context necessary.
	 * @param string $delimiter the delimiter char used to wrap the keyword value ('"' in intitle:"test")
	 * @param string $suffix the optional suffix used after the value ('i' in insource:/regex/i)
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	public function doApplyExtended( SearchContext $context, $key, $value, $quotedValue, $negated, $delimiter, $suffix );
}
