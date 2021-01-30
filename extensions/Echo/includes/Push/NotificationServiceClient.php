<?php

namespace EchoPush;

use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Status;

class NotificationServiceClient implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var string */
	private $endpointBase;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param string $endpointBase push service notification request endpoint base URL
	 */
	public function __construct( HttpRequestFactory $httpRequestFactory, string $endpointBase ) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->endpointBase = $endpointBase;
	}

	/**
	 * Send a CHECK_ECHO notification request to the push service for each subscription found.
	 * TODO: Update the service to handle multiple providers in a single request (T254379)
	 * @param array $subscriptions Subscriptions for which to send the message
	 */
	public function sendCheckEchoRequests( array $subscriptions ): void {
		$tokensByProvider = [];
		foreach ( $subscriptions as $subscription ) {
			$provider = $subscription->getProvider();
			if ( !isset( $tokensByProvider[$provider] ) ) {
				$tokensByProvider[$provider] = [];
			}
			$tokensByProvider[$provider][] = $subscription->getToken();
		}
		foreach ( array_keys( $tokensByProvider ) as $provider ) {
			$tokens = $tokensByProvider[$provider];
			$payload = [ 'deviceTokens' => $tokens, 'messageType' => 'checkEchoV1' ];
			$this->sendRequest( $provider, $payload );
		}
	}

	/**
	 * Send a notification request for a single push provider
	 * @param string $provider Provider endpoint to which to send the message
	 * @param array $payload message payload
	 */
	private function sendRequest( string $provider, array $payload ): void {
		$request = $this->constructRequest( $provider, $payload );
		$status = $request->execute();
		if ( !$status->isOK() ) {
			$errors = $status->getErrorsByType( 'error' );
			$this->logger->warning(
				Status::wrap( $status )->getMessage( false, false, 'en' )->serialize(),
				[
					'error' => $errors,
					'caller' => __METHOD__,
					'content' => $request->getContent()
				]
			);
		}
	}

	/**
	 * Construct a MWHttpRequest object based on the subscription and payload.
	 * @param string $provider
	 * @param array $payload
	 * @return MWHttpRequest
	 */
	private function constructRequest( string $provider, array $payload ): MWHttpRequest {
		$url = "$this->endpointBase/$provider";
		$opts = [ 'method' => 'POST', 'postData' => json_encode( $payload ) ];
		$req = $this->httpRequestFactory->create( $url, $opts );
		$req->setHeader( 'Content-Type', 'application/json; charset=utf-8' );
		return $req;
	}

}
