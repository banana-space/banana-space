<?php

namespace CirrusSearch\Search;

use CirrusSearch\Util;

/**
 * Randomly ordered but consistent for a single user
 */
class RandomCrossProjectBlockScorer extends CrossProjectBlockScorer {
	public function __construct( array $settings ) {
		parent::__construct( $settings );
		mt_srand( hexdec( substr( Util::generateIdentToken(), 0, 8 ) ) );
	}

	/**
	 * @param string $prefix
	 * @param CirrusSearchResultSet $results
	 * @return float
	 */
	public function score( $prefix, CirrusSearchResultSet $results ) {
		return (float)mt_rand();
	}
}
