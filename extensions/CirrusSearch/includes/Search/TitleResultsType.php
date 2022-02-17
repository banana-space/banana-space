<?php

namespace CirrusSearch\Search;

use Elastica\ResultSet as ElasticaResultSet;

/**
 * Returns titles and makes no effort to figure out how the titles matched.
 */
class TitleResultsType extends BaseResultsType {

	/**
	 * @var TitleHelper
	 */
	private $titleHelper;

	public function __construct( TitleHelper $titleHelper = null ) {
		$this->titleHelper = $titleHelper ?: new TitleHelper();
	}

	/**
	 * @return array corresponding to Elasticsearch fields syntax
	 */
	public function getStoredFields() {
		return [];
	}

	/**
	 * @param array $extraHighlightFields
	 * @return array|null
	 */
	public function getHighlightingConfiguration( array $extraHighlightFields = [] ) {
		return null;
	}

	/**
	 * @param ElasticaResultSet $resultSet
	 * @return mixed Set of search results, the types of which vary by implementation.
	 */
	public function transformElasticsearchResult( ElasticaResultSet $resultSet ) {
		$results = [];
		foreach ( $resultSet->getResults() as $r ) {
			$results[] = $this->getTitleHelper()->makeTitle( $r );
		}
		return $results;
	}

	/**
	 * @return array
	 */
	public function createEmptyResult() {
		return [];
	}

	/**
	 * @return TitleHelper
	 */
	public function getTitleHelper(): TitleHelper {
		if ( $this->titleHelper === null ) {
			$this->titleHelper = new TitleHelper();
		}
		return $this->titleHelper;
	}
}
