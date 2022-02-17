<?php

namespace CirrusSearch\Parser;

/**
 * Registry of KeywordFeature
 */
interface KeywordRegistry {

	/**
	 * @return \CirrusSearch\Query\KeywordFeature[]
	 */
	public function getKeywords();
}
