<?php

namespace CirrusSearch\Elastica;

use Elastica\Client;

/**
 * Overrides Elastica's Health class to allow filtering by index.
 */
class Health extends \Elastica\Cluster\Health {

	/** @var string|null Index or index pattern to limit health check to. */
	private $index;

	/**
	 * @param Client $client
	 * @param string|null $index Index or index pattern to limit health check to.
	 */
	public function __construct( Client $client, string $index = null ) {
		$this->index = $index;
		parent::__construct( $client );
	}

	/** @inheritDoc */
	protected function _retrieveHealthData() {
		$endpoint = new \Elasticsearch\Endpoints\Cluster\Health();
		if ( $this->index ) {
			$endpoint->setIndex( $this->index );
		}
		$endpoint->setParams( [ 'level' => 'shards' ] );
		$response = $this->_client->requestEndpoint( $endpoint );
		return $response->getData();
	}

}
