<?php

namespace CirrusSearch;

use Title;

class SearchRequestLog extends BaseRequestLog {
	/**
	 * @var bool
	 */
	private $cached = false;

	/**
	 * @var \Elastica\Client
	 */
	private $client;

	/**
	 * @var \Elastica\Request|null The elasticsearch request for this log
	 */
	protected $request;

	/**
	 * @var \Elastica\Request|null The elasticsearch request issued prior to
	 *  this log, used to protect against accidentaly using the wrong request.
	 */
	private $lastRequest;

	/**
	 * @var \Elastica\Response|null The elasticsearch response for this log
	 */
	protected $response;

	/**
	 * @var \Elastica\Response|null The elasticsearch response issued prior to
	 *  this log, used to protect against accidentaly using the wrong response.
	 */
	private $lastResponse;

	/**
	 * @var int[]|null $namespaces (null if unknown)
	 */
	private $namespaces;

	/**
	 * @param \Elastica\Client $client
	 * @param string $description
	 * @param string $queryType
	 * @param array $extra
	 * @param int[]|null $namespaces list of known namespaces to query, null if unknown or inappropriate
	 */
	public function __construct( \Elastica\Client $client, $description, $queryType, array $extra = [], array $namespaces = null ) {
		parent::__construct( $description, $queryType, $extra );
		$this->client = $client;
		$this->lastRequest = $client->getLastRequest();
		$this->lastResponse = $client->getLastResponse();
		$this->namespaces = $namespaces;
	}

	/**
	 * @param string[] $extra
	 */
	public function setCachedResult( array $extra ) {
		$this->extra += $extra;
		$this->cached = true;
	}

	public function finish() {
		if ( $this->request || $this->response ) {
			throw new \RuntimeException( 'Finishing a log more than once' );
		}
		parent::finish();
		$request = $this->client->getLastRequest();
		$this->request = $request === $this->lastRequest ? null : $request;
		$this->lastRequest = null;
		$response = $this->client->getLastResponse();
		$this->response = $response === $this->lastResponse ? null : $response;
		$this->lastResponse = null;
	}

	/**
	 * @return bool
	 */
	public function isCachedResponse() {
		return $this->cached;
	}

	/**
	 * @return int
	 */
	public function getElasticTookMs() {
		if ( !$this->response ) {
			return -1;
		}
		$data = $this->response->getData();

		return $data['took'] ?? -1;
	}

	/**
	 * @return array
	 */
	public function getLogVariables() {
		$vars = [
			'queryType' => $this->queryType,
			'tookMs' => $this->getTookMs(),
		] + $this->extra;

		if ( !$this->request || !$this->response ) {
			// @todo this is probably incomplete
			return $vars;
		}

		$index = explode( '/', $this->request->getPath() );
		$vars['index'] = $index[0];
		if ( $this->namespaces !== null ) {
			$vars['namespaces'] = $this->namespaces;
		}

		return $this->extractRequestVariables( $this->request->getData() ) +
			$this->extractResponseVariables( $this->response->getData() ) +
			// $vars must come *after* extractResponseVariables, because items
			// like 'suggestion' override data provided in $this->extra
			$vars;
	}

	/**
	 * @return array[]
	 */
	public function getRequests() {
		$vars = $this->getLogVariables();
		if ( $this->response ) {
			$vars['hits'] = $this->extractHits( $this->response->getData() );
		}

		return [ $vars ];
	}

	/**
	 * @param array $query
	 * @return array
	 */
	protected function extractRequestVariables( $query ) {
		if ( !is_array( $query ) ) {
			// @todo log something? this means some request was not as expected. Often
			// happens with multi-endpoints such as \Elastica\Type::deleteIds()
			return [];
		}

		$vars = [
			'hitsOffset' => $query['from'] ?? 0,
		];
		if ( !empty( $query['suggest'] ) ) {
			$vars['suggestionRequested'] = true;
		} else {
			$vars['suggestionRequested'] = false;
		}

		return $vars;
	}

	/**
	 * @param array $responseData
	 * @return array
	 */
	protected function extractResponseVariables( $responseData ) {
		if ( !is_array( $responseData ) ) {
			// No known offenders, but just in case...
			return [];
		}
		$vars = [];
		if ( isset( $responseData['took'] ) ) {
			$vars['elasticTookMs'] = $responseData['took'];
		}
		if ( isset( $responseData['hits']['total'] ) ) {
			$vars['hitsTotal'] = $responseData['hits']['total'];
		}
		if ( isset( $responseData['hits']['max_score'] ) ) {
			$vars['maxScore'] = $responseData['hits']['max_score'];
		}
		if ( isset( $responseData['hits']['hits'] ) ) {
			$vars['hitsReturned'] = count( $responseData['hits']['hits'] );
		}
		if ( isset( $responseData['suggest']['suggest'][0]['options'][0]['text'] ) ) {
			$vars['suggestion'] = $responseData['suggest']['suggest'][0]['options'][0]['text'];
		}

		// in case of failures from Elastica
		if ( isset( $responseData['message'] ) ) {
			$vars['error_message'] = $responseData['message'];
		}

		return $vars;
	}

	/**
	 * @param array $responseData
	 * @return array[]
	 */
	protected function extractHits( array $responseData ) {
		$hits = [];
		if ( isset( $responseData['hits']['hits'] ) ) {
			foreach ( $responseData['hits']['hits'] as $hit ) {
				if ( !isset( $hit['_source']['namespace'] )
					|| !isset( $hit['_source']['title'] )
				) {
					// This is probably a query that does not return pages like
					// geo or namespace queries.
					// @todo Should these get their own request logging class?
					continue;
				}
				// duplication of work...this happens in the transformation
				// stage but we can't see that here...Perhaps we instead attach
				// this data at a later stage like CompletionSuggester?
				$title = Title::makeTitle( $hit['_source']['namespace'], $hit['_source']['title'] );
				$hits[] = [
					'title' => (string)$title,
					'index' => $hit['_index'] ?? "",
					'pageId' => $hit['_id'] ?? -1,
					'score' => $hit['_score'] ?? -1.0,
				];
			}
		}

		return $hits;
	}
}
