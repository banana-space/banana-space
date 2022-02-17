<?php

namespace CirrusSearch;

/**
 * Request log for requests that update the elasticsearch cluster. All
 * update requests are done through bulk actions.
 */
class BulkUpdateRequestLog extends BaseRequestLog {
	/**
	 * @var \Elastica\Client
	 */
	private $client;

	/**
	 * @var \Elastica\Response|null
	 */
	private $lastResponse;

	/**
	 * @var \Elastica\Response|null
	 */
	private $response;

	/**
	 * @param \Elastica\Client $client
	 * @param string $description
	 * @param string $queryType
	 * @param string[] $extra
	 */
	public function __construct( \Elastica\Client $client, $description, $queryType, array $extra = [] ) {
		parent::__construct( $description, $queryType, $extra );
		$this->client = $client;
		$this->lastResponse = $client->getLastResponse();
	}

	public function finish() {
		if ( $this->response ) {
			throw new \RuntimeException( 'Finishing a log more than once' );
		}
		parent::finish();
		$response = $this->client->getLastResponse();
		$this->response = $response === $this->lastResponse ? null : $response;
		$this->lastResponse = null;
	}

	public function isCachedResponse() {
		return false;
	}

	public function getElasticTookMs() {
		if ( $this->response ) {
			$data = $this->response->getData();
			if ( isset( $data['took'] ) ) {
				return $data['took'];
			}
		}

		return -1;
	}

	/**
	 * @return array
	 */
	public function getLogVariables() {
		$vars = [
			'queryType' => $this->queryType,
			'tookMs' => $this->getTookMs(),
		] + $this->extra;

		if ( $this->response ) {
			$responseData = $this->response->getData();
			if ( isset( $responseData['items'] ) ) {
				$indices = [];
				foreach ( $responseData['items'] as $item ) {
					if ( isset( $item['update'] ) ) {
						$indices[] = $item['update']['_index'];
					}
				}
				$vars['numBulk'] = count( $responseData['items'] );
				$vars['index'] = implode( ',', array_unique( $indices ) );
			} else {
				$vars['index'] = 'UNKNOWN';
			}
		}

		return $vars;
	}

	/**
	 * We could generate multiple items for each bulk update that was sent..but
	 * doesn't seem necessary (yet).
	 *
	 * @return array[]
	 */
	public function getRequests() {
		return [ $this->getLogVariables() ];
	}
}
