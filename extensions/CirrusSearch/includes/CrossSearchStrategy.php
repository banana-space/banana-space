<?php

namespace CirrusSearch;

/**
 * Defines support strategies regarding cross wiki searches.
 *
 * Cross wiki search techniques include:
 *  - extra indices searches (i.e. search media files on commons)
 *  - cross project searches
 *  - cross language searches (i.e. second try attempt)
 */
class CrossSearchStrategy {
	/**
	 * @see InterwikiSearcher
	 * @var bool
	 */
	private $crossProjectSearchSupported;

	/**
	 * @see \CirrusSearch::searchTextSecondTry()
	 * @var bool
	 */
	private $crossLanguageSearchSupported;

	/**
	 * Controlled by $wgCirrusSearchExtraIndexes
	 * (Usually used to search media files on commons from any host wikis)
	 * @var bool
	 */
	private $extraIndicesSearchSupported;

	/**
	 * @var CrossSearchStrategy
	 */
	private static $hostWikiOnly;

	/**
	 * @var CrossSearchStrategy
	 */
	private static $allWikisStrategy;

	/**
	 * Only host wiki is supported
	 * Applies to features that are probably not available on any
	 * other target wiki cirrus may access.
	 * @return CrossSearchStrategy
	 */
	public static function hostWikiOnlyStrategy() {
		if ( self::$hostWikiOnly === null ) {
			self::$hostWikiOnly = new CrossSearchStrategy( false, false, false );
		}
		return self::$hostWikiOnly;
	}

	/**
	 * Applies to features that must be available on any target wiki.
	 *
	 * @return CrossSearchStrategy
	 */
	public static function allWikisStrategy() {
		if ( self::$allWikisStrategy === null ) {
			self::$allWikisStrategy = new CrossSearchStrategy( true, true, true );
		}
		return self::$allWikisStrategy;
	}

	/**
	 * @param bool $crossProjectSearchSupported
	 * @param bool $crossLanguageSearchSupported
	 * @param bool $extraIndicesSupported
	 */
	public function __construct( $crossProjectSearchSupported, $crossLanguageSearchSupported, $extraIndicesSupported ) {
		$this->crossProjectSearchSupported = $crossProjectSearchSupported;
		$this->crossLanguageSearchSupported = $crossLanguageSearchSupported;
		$this->extraIndicesSearchSupported = $extraIndicesSupported;
	}

	/**
	 * Is cross project search supported (aka interwiki search)
	 * @return bool
	 */
	public function isCrossProjectSearchSupported() {
		return $this->crossProjectSearchSupported;
	}

	/**
	 * Is cross language search supported (i.e. second try language search)
	 * @return bool
	 */
	public function isCrossLanguageSearchSupported() {
		return $this->crossLanguageSearchSupported;
	}

	/**
	 * Is extra indices search supported.
	 * (in WMF context it's most commonly used to search media files
	 * on commons from any wikis.)
	 *
	 * See wgCirrusSearchExtraIndexes
	 * @return bool
	 */
	public function isExtraIndicesSearchSupported() {
		return $this->extraIndicesSearchSupported;
	}

	/**
	 * Intersect this strategy with other.
	 * @param CrossSearchStrategy $other
	 * @return CrossSearchStrategy
	 */
	public function intersect( CrossSearchStrategy $other ) {
		if ( $other === self::hostWikiOnlyStrategy() || $this === self::hostWikiOnlyStrategy() ) {
			return self::hostWikiOnlyStrategy();
		}
		$crossL = $other->crossLanguageSearchSupported && $this->crossLanguageSearchSupported;
		$crossP = $other->crossProjectSearchSupported && $this->crossProjectSearchSupported;
		$otherI = $other->extraIndicesSearchSupported && $this->extraIndicesSearchSupported;
		if ( $crossL === $crossP && $crossP === $otherI ) {
			if ( $crossL ) {
				return self::allWikisStrategy();
			} else {
				return self::hostWikiOnlyStrategy();
			}
		}
		return new CrossSearchStrategy( $crossP, $crossL, $otherI );
	}
}
