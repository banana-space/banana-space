<?php

namespace CirrusSearch\Test;

use Elastica\Request;
use Elastica\Transport\AbstractTransport;

class PassThruTransport extends AbstractTransport {

	private $transportConfig;
	private $inner;
	private $responses = [];

	public function __construct( $inner ) {
		if ( $inner instanceof AbstractTransport ) {
			$this->inner = $inner;
		} else {
			$this->transportConfig = $inner;
		}
	}

	public function getResponses() {
		return $this->responses;
	}

	public function exec( Request $request, array $params ) {
		$response = $this->inner->exec( $request, $params );
		$this->responses[] = $response;

		return $response;
	}

	public function getConnection() {
		return $this->inner->getConnection();
	}

	public function setConnection( \Elastica\Connection $connection ) {
		if ( $this->inner ) {
			$this->inner->setConnection( $connection );
		} else {
			$this->inner = AbstractTransport::create(
				$this->transportConfig['transport'],
				$connection,
				$this->transportConfig['params']
			);
		}

		return $this;
	}

	public function toArray() {
		return $this->inner->toArray();
	}

	public function setParam( $key, $value ) {
		$this->inner->setParam( $key, $value );

		return $this;
	}

	public function setParams( array $params ) {
		$this->inner->setParams( $params );

		return $this;
	}

	public function addParam( $key, $value ) {
		$this->inner->addParam( $key, $value );

		return $this;
	}

	public function getParam( $key ) {
		return $this->inner->getParam( $key );
	}

	public function hasParam( $key ) {
		return $this->inner->hasParam( $key );
	}

	public function getParams() {
		return $this->inner->getParams();
	}
}
