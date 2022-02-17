<?php

namespace CirrusSearch\Dispatch;

/**
 * Cirrus default routes
 */
class CirrusDefaultSearchQueryRoute implements SearchQueryRoute {
	private static $SEARCH_TEXT;

	public static function searchTextDefaultRoute(): SearchQueryRoute {
		if ( self::$SEARCH_TEXT === null ) {
			self::$SEARCH_TEXT = new self( 'searchText' );
		}
		return self::$SEARCH_TEXT;
	}

	/** @var string */
	private $searchEngineEntryPoint;

	/**
	 * @param string $searchEngineEntryPoint
	 */
	private function __construct( $searchEngineEntryPoint ) {
		$this->searchEngineEntryPoint = $searchEngineEntryPoint;
	}

	/**
	 * @param \CirrusSearch\Search\SearchQuery $query
	 * @return float
	 */
	public function score( \CirrusSearch\Search\SearchQuery $query ) {
		return SearchQueryDispatchService::CIRRUS_DEFAULTS_SCORE;
	}

	/**
	 * @return string
	 */
	public function getSearchEngineEntryPoint() {
		return $this->searchEngineEntryPoint;
	}

	/**
	 * @return string
	 */
	public function getProfileContext() {
		return \CirrusSearch\Profile\SearchProfileService::CONTEXT_DEFAULT;
	}
}
