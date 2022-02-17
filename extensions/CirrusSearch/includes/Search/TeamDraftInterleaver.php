<?php

namespace CirrusSearch\Search;

use CirrusSearch\Util;

/**
 * Implementation of algorithm 2, Team-Draft Interleaving, from F. Radlinski, M.
 * Kurup, and T. Joachims. How does clickthrough data reflect retrieval
 * quality? In Proc 17th Intl. Conf. on Information and Knowledge Management.
 * ACM New York, NY, USA, 2008.
 */
class TeamDraftInterleaver {
	/** @var string The query provided by the user. */
	private $searchTerm;

	/**
	 * @param string $searchTerm The query provided by the user.
	 */
	public function __construct( $searchTerm ) {
		$this->searchTerm = $searchTerm;
	}

	/**
	 * @param CirrusSearchResultSet $a Results for team A
	 * @param CirrusSearchResultSet $b Results for team B
	 * @param int $limit Maximum number of results to return
	 * @return CirrusSearchResultSet
	 */
	public function interleave( CirrusSearchResultSet $a, CirrusSearchResultSet $b, $limit ): CirrusSearchResultSet {
		// Seed the randomness so an individual user the clicks a result and then
		// comes back to the search page sees the same interleaving. This is not
		// strictly necessary for the test, but provides the user a more consistent
		// experience.
		$seed = hexdec( substr( Util::generateIdentToken( $this->searchTerm ), 0, 8 ) );
		mt_srand( $seed );

		$aResults = $this->extractResults( $a );
		$bResults = $this->extractResults( $b );
		list( $interleaved, $teamA, $teamB, $aOffset ) = $this->interleaveResults(
			$aResults,
			$bResults,
			$limit
		);

		return new InterleavedResultSet( $a, $interleaved, $teamA, $teamB, $aOffset );
	}

	private function extractResults( CirrusSearchResultSet $resultSet ) {
		$extracted = [];
		/** @var $result CirrusSearchResult */
		foreach ( $resultSet as $result ) {
			$extracted[$result->getDocId()] = $result;
		}
		return $extracted;
	}

	/**
	 * Interleave two arrays using team draft interleaving. Only public
	 * for unit tests. The id's used as keys in $a and $b must represent
	 * the same thing, to prevent duplicates in the returned results.
	 *
	 * @param mixed[] $a Map from id to anything in preferred order for team A
	 * @param mixed[] $b Map from id to anything in preferred order for team B
	 * @param int $limit Maximum number of results to interleave
	 * @return mixed[] Four item array. first item being the values from $a
	 *  and $b in the interleaved ordering, second the ids belonging to team
	 *  A, third the ids belonging to team B, and finally the offset for the
	 *  next page in $a.
	 */
	public static function interleaveResults( $a, $b, $limit ) {
		$interleaved = [];
		$teamA = [];
		$teamB = [];
		$aIds = array_combine( array_keys( $a ), array_keys( $a ) );
		$bIds = array_combine( array_keys( $b ), array_keys( $b ) );
		while (
			count( $interleaved ) < $limit
			&& ( $aIds || $bIds )
		) {
			if ( !$aIds ) {
				$id = reset( $bIds );
				$teamB[] = $id;
				$interleaved[] = $b[$id];
			} elseif ( !$bIds ) {
				$id = reset( $aIds );
				$teamA[] = $id;
				$interleaved[] = $a[$id];
			// If team b has already chosen, then a needs to go
			} elseif ( count( $teamA ) < count( $teamB )
				|| (
				// If the counts are equal choose randomly which team goes next
					count( $teamA ) == count( $teamB )
					&& mt_rand( 0, 1 ) === 1
			) ) {
				$id = reset( $aIds );
				$teamA[] = $id;
				$interleaved[] = $a[$id];
			} else {
				$id = reset( $bIds );
				$teamB[] = $id;
				$interleaved[] = $b[$id];
			}
			unset( $aIds[$id], $bIds[$id] );
		}

		if ( $aIds ) {
			// $offset needs to be set such that results starting at $offset +
			// $limit are new. As such if we have 20 items and 10 of a are
			// used, then offset should be -10. If 15 are used we want -5.
			// This doesn't guarantee we don't show duplicates. Items from B
			// could be in second page of A. The goal here is to ensure
			// everything from A is seen when paginating even if there are
			// dupes.
			$nextA = reset( $aIds );
			$nextAIdx = array_search( $nextA, array_keys( $a ) );
			$offset = -( $limit - $nextAIdx );
		} else {
			$offset = 0;
		}

		return [ $interleaved, $teamA, $teamB, $offset ];
	}
}
