<?php

namespace CirrusSearch;

use Language;
use MediaWiki\Logger\LoggerFactory;
use Title;

/**
 * Picks the best "near match" title.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class NearMatchPicker {
	/**
	 * @var Language language to use during normalization process
	 */
	private $language;
	/**
	 * @var string the search term
	 */
	private $term;
	/**
	 * @var array[] Potential near matches
	 */
	private $titles;

	/**
	 * @param Language $language to use during normalization process
	 * @param string $term the search term
	 * @param array[] $titles Array of arrays, each with optional keys:
	 *   titleMatch => a title if the title matched
	 *   redirectMatches => an array of redirect matches, one per matched redirect
	 */
	public function __construct( $language, $term, $titles ) {
		$this->language = $language;
		$this->term = $term;
		$this->titles = $titles;
	}

	/**
	 * Pick the best near match if possible.
	 *
	 * @return Title|null title if there is a near match and null otherwise
	 */
	public function pickBest() {
		if ( !$this->titles ) {
			return null;
		}
		if ( !$this->term ) {
			return null;
		}
		if ( count( $this->titles ) === 1 ) {
			if ( isset( $this->titles[ 0 ][ 'titleMatch' ] ) ) {
				return $this->titles[ 0 ][ 'titleMatch' ];
			}
			if ( isset( $this->titles[ 0 ][ 'redirectMatches' ][ 0 ] ) ) {
				return $this->titles[ 0 ][ 'redirectMatches' ][ 0 ];
			}
			LoggerFactory::getInstance( 'CirrusSearch' )->info(
				'NearMatchPicker built with busted matches.  Assuming no near match' );
			return null;
		}

		$transformers = [
			function ( $term ) {
				return $term;
			},
			[ $this->language, 'lc' ],
			[ $this->language, 'ucwords' ],
		];

		foreach ( $transformers as $transformer ) {
			$transformedTerm = call_user_func( $transformer, $this->term );
			$found = null;
			foreach ( $this->titles as $title ) {
				$match = $this->checkAllMatches( $transformer, $transformedTerm, $title );
				if ( $match ) {
					if ( $found === null ) {
						$found = $match;
					} else {
						// Found more than one result so we try another transformer
						$found = null;
						break;
					}
				}

			}
			if ( $found ) {
				return $found;
			}
		}

		// Didn't find anything
		return null;
	}

	/**
	 * Check a single title's worth of matches.  The big thing here is that titles cannot compete with themselves.
	 * @param callable $transformer
	 * @param string $transformedTerm
	 * @param array $allMatchedTitles
	 * @return null|Title null if no title matches and the actual title (either of the page or of a redirect to the
	 *       page) if one did match
	 */
	private function checkAllMatches( $transformer, $transformedTerm, $allMatchedTitles ) {
		if ( isset( $allMatchedTitles[ 'titleMatch' ] ) &&
				$this->checkOneMatch( $transformer, $transformedTerm, $allMatchedTitles[ 'titleMatch' ] ) ) {
			return $allMatchedTitles[ 'titleMatch' ];
		}
		if ( isset( $allMatchedTitles[ 'redirectMatches' ] ) ) {
			foreach ( $allMatchedTitles[ 'redirectMatches' ] as $redirectMatch ) {
				if ( $this->checkOneMatch( $transformer, $transformedTerm, $redirectMatch ) ) {
					return $redirectMatch;
				}
			}
		}
		return null;
	}

	/**
	 * @param callable $transformer
	 * @param string $transformedTerm
	 * @param Title $matchedTitle
	 * @return bool
	 */
	private function checkOneMatch( $transformer, $transformedTerm, $matchedTitle ) {
		$transformedTitle = call_user_func( $transformer, $matchedTitle->getText() );
		return $transformedTerm === $transformedTitle;
	}
}
