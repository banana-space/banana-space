<?php

// This is a GET module, not a POST module, for multi-DC support. See T222851.
// Note that this module doesn't write to the database, only to the seentime cache.
class ApiEchoMarkSeen extends ApiBase {

	public function execute() {
		// To avoid API warning, register the parameter used to bust browser cache
		$this->getMain()->getVal( '_' );

		$user = $this->getUser();
		if ( $user->isAnon() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'login-required' );
		}

		$params = $this->extractRequestParams();
		$timestamp = wfTimestamp( TS_MW );
		$seenTime = EchoSeenTime::newFromUser( $user );
		$seenTime->setTime( $timestamp, $params['type'] );

		if ( $params['timestampFormat'] === 'ISO_8601' ) {
			$outputTimestamp = wfTimestamp( TS_ISO_8601, $timestamp );
		} else {
			// MW
			$this->addDeprecation(
				'apiwarn-echo-deprecation-timestampformat',
				'action=echomarkseen&timestampFormat=MW'
			);

			$outputTimestamp = $timestamp;
		}

		$this->getResult()->addValue( 'query', $this->getModuleName(), [
			'result' => 'success',
			'timestamp' => $outputTimestamp,
		] );
	}

	public function getAllowedParams() {
		return [
			'type' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => [ 'alert', 'message', 'all' ],
			],
			'timestampFormat' => [
				// Not using the TS constants, since clients can't.
				ApiBase::PARAM_DFLT => 'MW',
				ApiBase::PARAM_TYPE => [ 'ISO_8601', 'MW' ],
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return string[]
	 */
	protected function getExamplesMessages() {
		return [
			'action=echomarkseen&type=all' => 'apihelp-echomarkseen-example-1',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Echo_(Notifications)/API';
	}
}
