<?php

namespace Flow\Import\LiquidThreadsApi;

use MediaWiki\MediaWikiServices;

class RemoteApiBackend extends ApiBackend {
	/**
	 * @var string
	 */
	protected $apiUrl;

	/**
	 * @var string|null
	 */
	protected $cacheDir;

	/**
	 * @param string $apiUrl
	 * @param string|null $cacheDir
	 */
	public function __construct( $apiUrl, $cacheDir = null ) {
		parent::__construct();
		$this->apiUrl = $apiUrl;
		$this->cacheDir = $cacheDir;
	}

	public function getKey() {
		return $this->apiUrl;
	}

	public function apiCall( array $params, $retry = 1 ) {
		$params['format'] = 'json';
		$url = wfAppendQuery( $this->apiUrl, $params );
		$file = $this->cacheDir . '/' . md5( $url ) . '.cache';
		$this->logger->debug( __METHOD__ . ": $url" );
		if ( $this->cacheDir && file_exists( $file ) ) {
			$result = file_get_contents( $file );
		} else {
			$httpRequestFactory = MediaWikiServices::getInstance()->getHttpRequestFactory();
			do {
				$result = $httpRequestFactory->get( $url, [], __METHOD__ );
			} while ( $result === null && --$retry >= 0 );

			if ( $this->cacheDir && file_put_contents( $file, $result ) === false ) {
				$this->logger->warning( "Failed writing cached api result to $file" );
			}
		}

		if ( !$result ) {
			return [];
		}

		return json_decode( $result, true );
	}
}
