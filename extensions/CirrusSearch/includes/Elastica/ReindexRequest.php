<?php

namespace CirrusSearch\Elastica;

use Elastica\Client;
use Elastica\Index;
use Elastica\Request;
use Elastica\Response;
use Elastica\Type;
use InvalidArgumentException;

class ReindexRequest {
	/** @var array */
	private $source;
	/** @var array */
	private $dest;
	/** @var array|null */
	private $script;
	/** @var int|null */
	private $size;
	/** @var int */
	private $requestsPerSecond = -1;
	/** @var int */
	private $slices = 1;
	/** @var Client */
	private $client;

	/**
	 * @param Index|Type $source
	 * @param Index|Type $dest
	 * @param int $chunkSize
	 */
	public function __construct( $source, $dest, $chunkSize = 100 ) {
		$this->source = $this->asSourceDest( $source );
		$this->source['size'] = $chunkSize;
		$this->dest = $this->asSourceDest( $dest );
		$this->client = $dest instanceof Index
			? $dest->getClient()
			: $dest->getIndex()->getClient();
	}

	/**
	 * @param array $remote
	 * @return $this
	 */
	public function setRemoteInfo( array $remote ) {
		$this->source['remote'] = $remote;
		return $this;
	}

	/**
	 * @param array $script
	 * @return $this
	 */
	public function setScript( array $script ) {
		$this->script = $script;
		return $this;
	}

	/**
	 * The number of documents to reindex
	 *
	 * @param int $size
	 * @return $this
	 */
	public function setSize( $size ) {
		$this->size = $size;
		return $this;
	}

	/**
	 * @param int $rps
	 * @return $this
	 */
	public function setRequestsPerSecond( $rps ) {
		$this->requestsPerSecond = $rps;
		return $this;
	}

	/**
	 * @param int $slices
	 * @return $this
	 */
	public function setSlices( $slices ) {
		$this->slices = $slices;
		return $this;
	}

	/**
	 * @return ReindexTask
	 */
	public function reindexTask() {
		$response = $this->request( [
			'wait_for_completion' => 'false',
		] );

		return new ReindexTask( $this->client, $response->getData()['task'] );
	}

	/**
	 * @return ReindexResponse
	 */
	public function reindex() {
		return new ReindexResponse( $this->request()->getData() );
	}

	/**
	 * @param array $query
	 * @return Response
	 */
	private function request( array $query = [] ) {
		$query['requests_per_second'] = $this->requestsPerSecond;
		$query['slices'] = $this->slices;
		$response = $this->client->request( '_reindex', Request::POST, $this->toArray(), $query );

		if ( !$response->isOK() ) {
			throw new \Exception( $response->hasError()
				? 'Failed reindex request: ' . $response->getErrorMessage()
				: 'Unknown reindex failure: ' . $response->getStatus()
			);
		}

		return $response;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		$request = [
			'source' => $this->source,
			'dest' => $this->dest,
		];
		if ( $this->script ) {
			$request['script'] = $this->script;
		}
		if ( $this->size ) {
			$request['size'] = $this->size;
		}

		return $request;
	}

	/**
	 * @param Index|Type $input
	 * @return array
	 * @throws InvalidArgumentException
	 */
	private function asSourceDest( $input ) {
		if ( $input instanceof Type ) {
			return [
				'index' => $input->getIndex()->getName(),
				'type' => $input->getName(),
			];
		} elseif ( $input instanceof Index ) {
			return [ 'index' => $input->getName() ];
		}
		throw new InvalidArgumentException( 'Expected Type|Index, got '
			. ( is_object( $input ) ? get_class( $input ) : gettype( $input ) )
		);
	}

}
