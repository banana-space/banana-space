<?php

namespace CirrusSearch\Test;

use CirrusSearch\Search\BaseCirrusSearchResultSet;
use CirrusSearch\Search\Result;
use CirrusSearch\Search\TitleHelper;
use SearchResult;

class DummySearchResultSet extends BaseCirrusSearchResultSet {
	/**
	 * @var \Elastica\ResultSet
	 */
	private $resultSet;

	/**
	 * @var TitleHelper
	 */
	private $titleHelper;

	/**
	 * @param TitleHelper $titleHelper
	 * @param int $totalHits
	 */
	private function __construct( TitleHelper $titleHelper, $totalHits ) {
		$this->titleHelper = $titleHelper;
		$results = [];
		foreach ( range( 1, min( $totalHits, 20 ) ) as $i ) {
			$results[] = new \Elastica\Result( [] );
		}
		$this->resultSet = new \Elastica\ResultSet(
			new \Elastica\Response( [ "hits" => [ "total" => $totalHits ] ] ),
			new \Elastica\Query(),
			$results
		);
	}

	/**
	 * @param TitleHelper $titleHelper
	 * @param int $totalHits
	 * @param int[] $interwikiTotals total hits for secondary results interwiki results.
	 * @return DummySearchResultSet
	 */
	public static function fakeTotalHits( TitleHelper $titleHelper, $totalHits, array $interwikiTotals = [] ) {
		$results = new self( $titleHelper, $totalHits );
		foreach ( $interwikiTotals as $pref => $iwTotal ) {
			$results->addInterwikiResults( self::fakeTotalHits( $titleHelper, $iwTotal ), self::SECONDARY_RESULTS, (string)$pref );
		}
		return $results;
	}

	/**
	 * @param \Elastica\Result $result Result from search engine
	 * @return Result|null Elasticsearch result transformed into mediawiki
	 *  search result object.
	 */
	protected function transformOneResult( \Elastica\Result $result ) {
		return new Result( $this, $result, $this->titleHelper );
	}

	/**
	 * @return \Elastica\ResultSet|null
	 */
	public function getElasticaResultSet() {
		return $this->resultSet;
	}

	/**
	 * Did the search contain search syntax?  If so, Special:Search won't offer
	 * the user a link to a create a page named by the search string because the
	 * name would contain the search syntax.
	 * @return bool
	 */
	public function searchContainedSyntax() {
		return false;
	}

	protected function getTitleHelper(): TitleHelper {
		return $this->titleHelper;
	}

	/**
	 * Returns extra data for specific result and store it in SearchResult object.
	 * @param SearchResult $result
	 */
	public function augmentResult( SearchResult $result ) {
		// Do nothing, we do not test result augmentation
		// it relies on Title::getArticleID() which depends on MWServices
	}
}
