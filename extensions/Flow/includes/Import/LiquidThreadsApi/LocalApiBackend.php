<?php

namespace Flow\Import\LiquidThreadsApi;

use ApiErrorFormatter;
use ApiMain;
use ApiMessage;
use ApiUsageException;
use Exception;
use FauxRequest;
use RequestContext;
use User;

class LocalApiBackend extends ApiBackend {
	/**
	 * @var User|null
	 */
	protected $user;

	public function __construct( User $user = null ) {
		parent::__construct();
		$this->user = $user;
	}

	public function getKey() {
		return 'local';
	}

	public function apiCall( array $params, $retry = 1 ) {
		try {
			$context = new RequestContext;
			$context->setRequest( new FauxRequest( $params ) );
			if ( $this->user ) {
				$context->setUser( $this->user );
			}

			$api = new ApiMain( $context );
			$api->execute();

			return $api->getResult()->getResultData( null, [ 'Strip' => 'all' ] );
		} catch ( ApiUsageException $exception ) {
			// Mimic the behaviour when called remotely
			$errors = $exception->getStatusValue()->getErrorsByType( 'error' );
			if ( !$errors ) {
				$errors = $exception->getStatusValue()->getErrorsByType( 'warning' );
			}
			if ( !$errors ) {
				$errors = [
					[
						'message' => 'unknownerror-nocode',
						'params' => []
					]
				];
			}
			$msg = ApiMessage::create( $errors[0] );

			return [
				'error' => [
						'code' => $msg->getApiCode(),
						'info' => ApiErrorFormatter::stripMarkup(
						// @phan-suppress-next-line PhanUndeclaredMethod Phan is mostly right
							$msg->inLanguage( 'en' )->useDatabase( 'false' )->text()
						),
					] + $msg->getApiData()
			];
		} catch ( Exception $exception ) {
			// Mimic behaviour when called remotely
			return [
				'error' => [
					'code' => 'internal_api_error_' . get_class( $exception ),
					'info' => 'Exception Caught: ' . $exception->getMessage(),
				],
			];
		}
	}
}
