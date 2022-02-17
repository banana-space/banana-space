<?php

namespace CirrusSearch\Search;

use Elastica\ResultSet as ElasticaResultSet;

/**
 * Result type for aggregations.
 */
class SingleAggResultsType implements ResultsType {
	/** @var string Name of aggregation */
	private $name;

	/** @param string $name Name of aggregation to return */
	public function __construct( $name ) {
		$this->name = $name;
	}

	/**
	 * @return false|string|array corresponding to Elasticsearch source filtering syntax
	 */
	public function getSourceFiltering() {
		return false;
	}

	public function getStoredFields() {
		return [];
	}

	public function getHighlightingConfiguration( array $extraHighlightFields ) {
		return null;
	}

	/**
	 * @param ElasticaResultSet $resultSet
	 * @return mixed|null Type depends on the aggregation performed. For
	 *  a sum this will return an integer.
	 */
	public function transformElasticsearchResult( ElasticaResultSet $resultSet ) {
		$aggs = $resultSet->getAggregations();
		if ( isset( $aggs[$this->name] ) ) {
			return $aggs[$this->name]['value'];
		}
		return $this->createEmptyResult();
	}

	/**
	 * @return null
	 */
	public function createEmptyResult() {
		return null;
	}
}
