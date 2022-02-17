<?php

namespace CirrusSearch\Search;

/**
 * Score based on total hits : log(total_hits + 2)
 */
class RecallCrossProjectBlockScorer extends CrossProjectBlockScorer {
	/**
	 * @param string $prefix
	 * @param CirrusSearchResultSet $results
	 * @return float
	 */
	public function score( $prefix, CirrusSearchResultSet $results ) {
		return log( $results->getTotalHits() + 2 );
	}
}
