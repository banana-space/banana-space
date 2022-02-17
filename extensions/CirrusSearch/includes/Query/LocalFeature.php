<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Search\SearchContext;

/**
 * Limits the search to the local wiki. Primarily this excludes results from
 * commons when searching the NS_FILE namespace. No value may be provided
 * along with this keyword, it is a simple boolean flag.
 */
class LocalFeature extends SimpleKeywordFeature implements LegacyKeywordFeature {

	/**
	 * @return string[] The list of keywords this feature is supposed to match
	 */
	protected function getKeywords() {
		return [ 'local' ];
	}

	/**
	 * @return bool
	 */
	public function hasValue() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function queryHeader() {
		return true;
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy( KeywordFeatureNode $node ) {
		return CrossSearchStrategy::hostWikiOnlyStrategy();
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
		$context->setLimitSearchToLocalWiki( true );
		return [ null, false ];
	}
}
