<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Session\SessionManager;

class EchoForeignWikiRequest {

	/** @var User */
	protected $user;

	/** @var array */
	protected $params;

	/** @var array */
	protected $wikis;

	/** @varstring|null */
	protected $wikiParam;

	/** @var string */
	protected $method;

	/** @var string|null */
	protected $tokenType;

	/** @var string[]|null */
	protected $csrfTokens;

	/**
	 * @param User $user
	 * @param array $params Request parameters
	 * @param array $wikis Wikis to send the request to
	 * @param string|null $wikiParam Parameter name to set to the name of the wiki
	 * @param string|null $postToken If set, use POST requests and inject a token of this type;
	 *  if null, use GET requests.
	 */
	public function __construct( User $user, array $params, array $wikis, $wikiParam = null, $postToken = null ) {
		$this->user = $user;
		$this->params = $params;
		$this->wikis = $wikis;
		$this->wikiParam = $wikiParam;
		$this->method = $postToken === null ? 'GET' : 'POST';
		$this->tokenType = $postToken;

		$this->csrfTokens = null;
	}

	/**
	 * Execute the request
	 * @return array[] [ wiki => result ]
	 */
	public function execute() {
		if ( !$this->canUseCentralAuth() ) {
			return [];
		}

		$reqs = $this->getRequestParams( $this->method, [ $this, 'getQueryParams' ] );
		return $this->doRequests( $reqs );
	}

	protected function getCentralId( $user ) {
		$lookup = CentralIdLookup::factory();
		$id = $lookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
		return $id;
	}

	protected function canUseCentralAuth() {
		global $wgFullyInitialised, $wgUser;

		return $wgFullyInitialised &&
			$wgUser->isSafeToLoad() &&
			$this->user->isSafeToLoad() &&
			SessionManager::getGlobalSession()->getProvider() instanceof CentralAuthSessionProvider &&
			$this->getCentralId( $this->user ) !== 0;
	}

	/**
	 * Returns CentralAuth token, or null on failure.
	 *
	 * @param User $user
	 * @return string|null
	 */
	protected function getCentralAuthToken( User $user ) {
		$context = new RequestContext;
		$context->setRequest( new FauxRequest( [ 'action' => 'centralauthtoken' ] ) );
		$context->setUser( $user );

		$api = new ApiMain( $context );

		try {
			$api->execute();

			return $api->getResult()->getResultData( [ 'centralauthtoken', 'centralauthtoken' ] );
		} catch ( Exception $ex ) {
			LoggerFactory::getInstance( 'Echo' )->debug(
				'Exception when fetching CentralAuth token: wiki: {wiki}, userName: {userName}, ' .
					'userId: {userId}, centralId: {centralId}, exception: {exception}',
				[
					'wiki' => wfWikiID(),
					'userName' => $user->getName(),
					'userId' => $user->getId(),
					'centralId' => $this->getCentralId( $user ),
					'exception' => $ex,
				]
			);

			MWExceptionHandler::logException( $ex );

			return null;
		}
	}

	/**
	 * Get the CSRF token for a given wiki.
	 * This method fetches the tokens for all requested wikis at once and caches the result.
	 *
	 * @param string $wiki Name of the wiki to get a token for
	 * @suppress PhanTypeInvalidCallableArraySize getRequestParams can take an array, too (phan bug)
	 * @return string Token
	 */
	protected function getCsrfToken( $wiki ) {
		if ( $this->csrfTokens === null ) {
			$this->csrfTokens = [];
			$reqs = $this->getRequestParams( 'GET', [
				'action' => 'query',
				'meta' => 'tokens',
				'type' => $this->tokenType,
				'format' => 'json',
				'centralauthtoken' => $this->getCentralAuthToken( $this->user ),
			] );
			$responses = $this->doRequests( $reqs );
			foreach ( $responses as $w => $response ) {
				$this->csrfTokens[$w] = $response['query']['tokens']['csrftoken'];
			}
		}
		return $this->csrfTokens[$wiki];
	}

	/**
	 * @param string $method 'GET' or 'POST'
	 * @param array|callable $params Associative array of query string / POST parameters,
	 *  or a callback that takes a wiki name and returns such an array
	 * @return array[] Array of request parameters to pass to doRequests(), keyed by wiki name
	 */
	protected function getRequestParams( $method, $params ) {
		$apis = EchoForeignNotifications::getApiEndpoints( $this->wikis );
		if ( !$apis ) {
			return [];
		}

		$reqs = [];
		foreach ( $apis as $wiki => $api ) {
			$queryKey = $method === 'POST' ? 'body' : 'query';
			$reqs[$wiki] = [
				'method' => $method,
				'url' => $api['url'],
				$queryKey => is_callable( $params ) ? $params( $wiki ) : $params
			];
		}

		return $reqs;
	}

	/**
	 * @param string $wiki Wiki name
	 * @return array
	 */
	protected function getQueryParams( $wiki ) {
		$extraParams = [];
		if ( $this->wikiParam ) {
			// Only request data from that specific wiki, or they'd all spawn
			// cross-wiki api requests...
			$extraParams[$this->wikiParam] = $wiki;
		}
		if ( $this->method === 'POST' ) {
			$extraParams['token'] = $this->getCsrfToken( $wiki );
		}

		return [
			'centralauthtoken' => $this->getCentralAuthToken( $this->user ),
			// once all the results are gathered & merged, they'll be output in the
			// user requested format
			// but this is going to be an internal request & we don't want those
			// results in the format the user requested but in a fixed format that
			// we can interpret here
			'format' => 'json',
		] + $extraParams + $this->params;
	}

	/**
	 * @param array $reqs API request params
	 * @return array[]
	 * @throws Exception
	 */
	protected function doRequests( array $reqs ) {
		$http = MediaWikiServices::getInstance()->getHttpRequestFactory()->createMultiClient();
		$responses = $http->runMulti( $reqs );

		$results = [];
		foreach ( $responses as $wiki => $response ) {
			$statusCode = $response['response']['code'];

			if ( $statusCode >= 200 && $statusCode <= 299 ) {
				$parsed = json_decode( $response['response']['body'], true );
				if ( $parsed ) {
					$results[$wiki] = $parsed;
				}
			}

			if ( !isset( $results[$wiki] ) ) {
				LoggerFactory::getInstance( 'Echo' )->warning(
					'Failed to fetch API response from {wiki}. Error code {code}',
					[
						'wiki' => $wiki,
						'code' => $response['response']['code'],
						'response' => $response['response']['body'],
						'request' => $reqs[$wiki],
					]
				);
			}
		}

		return $results;
	}
}
