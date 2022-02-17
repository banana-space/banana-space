<?php

namespace CirrusSearch\Search;

use Elastica\ResultSet as ElasticaResultSet;
use Elastica\Search;
use Status;
use Wikimedia\Assert\Assert;

/**
 * Holds the Elastica result sets returned by Elastic when issuing search requests.
 * Used to keep track of which search request led to which result set.
 */
class MSearchResponses {
	/**
	 * @var ElasticaResultSet[]
	 */
	private $resultSets;

	/**
	 * @var Search[]
	 */
	private $requests;

	/**
	 * @var Status|null
	 */
	private $failure;

	/**
	 * MSearchResponses constructor.
	 * @param ElasticaResultSet[] $resultSets
	 * @param Search[] $requests
	 * @param Status|null $status failure
	 */
	public function __construct( array $resultSets, array $requests, Status $status = null ) {
		$this->resultSets = $resultSets;
		$this->requests = $requests;
		Assert::parameter( $status === null || !$status->isOK(), '$status', 'must be a failure if set' );
		$this->failure = $status;
	}

	/**
	 * @param string $key
	 * @return ElasticaResultSet
	 */
	public function getResultSet( $key ): ElasticaResultSet {
		return $this->resultSets[$key];
	}

	/**
	 * Transform all the resultsets identified by keys and wrap them inside a success Status stored as an array.
	 *
	 * @param ResultsType $transformation
	 * @param string[] $keys
	 * @param callable|null $reordering reordering function
	 * @return Status
	 */
	public function transformAndGetMulti( ResultsType $transformation, array $keys, callable $reordering = null ): Status {
		$result = [];
		$input = array_intersect_key( $this->resultSets, array_flip( $keys ) );
		foreach ( $input as $k => $v ) {
			$result[$k] = $transformation->transformElasticsearchResult( $v );
		}
		if ( $reordering !== null ) {
			$result = $reordering( $result );
		}
		return Status::newGood( $result );
	}

	/**
	 * Transform the resultset identified by key and wrap it inside a success Status
	 * @param ResultsType $transformation
	 * @param string $key
	 * @return Status
	 */
	public function transformAndGetSingle( ResultsType $transformation, $key ): Status {
		return Status::newGood( $transformation->transformElasticsearchResult( $this->resultSets[$key] ) );
	}

	/**
	 * Transform the resultset identified by key and returns it as a CirrusSearch ResultSet
	 * NOTE: The $tranformation provided must emit a CirrusResultSet
	 * @param ResultsType $transformation
	 * @param string $key
	 * @return CirrusSearchResultSet
	 */
	public function transformAsResultSet( ResultsType $transformation, $key ): CirrusSearchResultSet {
		return $transformation->transformElasticsearchResult( $this->resultSets[$key] );
	}

	/**
	 * @return bool
	 */
	public function hasResponses() {
		return $this->resultSets !== [];
	}

	/**
	 * @return bool
	 */
	public function hasFailure() {
		return $this->failure !== null;
	}

	/**
	 * @return Status
	 */
	public function getFailure(): Status {
		Assert::precondition( $this->failure !== null, 'must have failed' );
		return $this->failure;
	}

	/**
	 * @return bool
	 */
	public function hasTimeout() {
		foreach ( $this->resultSets as $resultSet ) {
			if ( $resultSet->hasTimedOut() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasResultsFor( $key ) {
		return array_key_exists( $key, $this->resultSets );
	}

	/**
	 * @param string $description
	 * @return Status
	 */
	public function dumpResults( $description ): Status {
		$retval = [];
		foreach ( $this->resultSets as $key => $resultSet ) {
			$retval[$key] = [
				'description' => $description,
				'path' => $this->requests[$key]->getPath(),
				'result' => $resultSet->getResponse()->getData(),
			];
		}
		return Status::newGood( $retval );
	}
}
