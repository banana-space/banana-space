<?php

namespace CirrusSearch\Search;

use ArrayIterator;
use Elastica\Search;
use MultipleIterator;
use Status;
use Wikimedia\Assert\Assert;

/**
 * Holds the Elastic search requests meant to be sent to the _msearch endpoint.
 * Users attach a search request identified by a key and after the search requests have been submitted
 * to the backend the response can later then be retrieved using that same key.
 * @See MSearchResponses
 */
class MSearchRequests {
	/**
	 * @var Search[]
	 */
	private $requests = [];

	/**
	 * @param string $key
	 * @param Search $search
	 * @return self
	 */
	public static function build( $key, Search $search ): self {
		$self = new self();
		$self->addRequest( $key, $search );
		return $self;
	}

	/**
	 * @param string $key
	 * @param Search $search
	 */
	public function addRequest( $key, Search $search ) {
		Assert::parameter( !isset( $this->requests[$key] ), '$key', 'duplicated key' );
		$this->requests[$key] = $search;
	}

	/**
	 * @return Search[]
	 */
	public function getRequests(): array {
		return $this->requests;
	}

	/**
	 * @param \Elastica\ResultSet[] $resultSets
	 * @return MSearchResponses
	 */
	public function toMSearchResponses( array $resultSets ): MSearchResponses {
		Assert::parameter( count( $resultSets ) === count( $this->requests ), '$responses',
			'must have as many responses as requests (wanted ' . count( $this->requests ) . ' received ' . count( $resultSets ) . ')' );
		$mi = new MultipleIterator( MultipleIterator::MIT_NEED_ALL );
		$mi->attachIterator( new ArrayIterator( $this->requests ) );
		$mi->attachIterator( new ArrayIterator( $resultSets ) );
		$resultSetsWithKeys = [];
		foreach ( $mi as $k => $v ) {
			$resultSetsWithKeys[$k[0]] = $v[1];
		}

		return new MSearchResponses( $resultSetsWithKeys, $this->requests );
	}

	/**
	 * @param Status $status
	 * @return MSearchResponses
	 */
	public function failure( Status $status ): MSearchResponses {
		return new MSearchResponses( [], [], $status );
	}

	/**
	 * @param string $description
	 * @return Status
	 */
	public function dumpQuery( $description ): Status {
		$retval = [];
		foreach ( $this->requests as $k => $search ) {
			$retval[$k] = [
				'description' => $description,
				'path' => $search->getPath(),
				'params' => $search->getOptions(),
				'query' => $search->getQuery()->toArray(),
				'options' => $search->getOptions(),
			];
		}
		return Status::newGood( $retval );
	}
}
