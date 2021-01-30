<?php

class ApiEchoArticleReminder extends ApiBase {

	public function execute() {
		$this->getMain()->setCacheMode( 'private' );
		$user = $this->getUser();
		if ( $user->isAnon() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'login-required' );
		}

		$params = $this->extractRequestParams();
		$result = [];
		$userTimestamp = new MWTimestamp( $params['timestamp'] );
		$nowTimestamp = new MWTimestamp();
		// We need $params['timestamp'] to be a future timestamp:
		// $userTimestamp < $nowTimestamp = invert 0
		// $userTimestamp > $nowTimestamp = invert 1
		if ( $userTimestamp->diff( $nowTimestamp )->invert === 0 ) {
			$this->dieWithError( [ 'apierror-badparameter', 'timestamp' ], 'timestamp-not-in-future', null, 400 );
		}

		$eventCreation = EchoEvent::create( [
			'type' => 'article-reminder',
			'agent' => $user,
			'title' => $this->getTitleFromTitleOrPageId( $params ),
			'extra' => [
				'comment' => $params['comment'],
			],
		] );

		if ( !$eventCreation ) {
			$this->dieWithError( 'apierror-echo-event-creation-failed', null, null, 500 );
		}

		/* Temp - removing the delay just for now:
		$job = new JobSpecification(
			'articleReminder',
			[
				'userId' => $user->getId(),
				'timestamp' => $params['timestamp'],
				'comment' => $params['comment'],
			],
			[ 'removeDuplicates' => true ],
			Title::newFromID( $params['pageid'] )
		);
		JobQueueGroup::singleton()->push( $job );*/
		$result += [
			'result' => 'success'
		];
		$this->getResult()->addValue( 'query', $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return [
			'pageid' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'comment' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'timestamp' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'timestamp',
			],
			'token' => [
				ApiBase::PARAM_REQUIRED => true,
			],
		];
	}

	public function needsToken() {
		return 'csrf';
	}

	public function getTokenSalt() {
		return '';
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return string[]
	 */
	protected function getExamplesMessages() {
		$todayDate = new DateTime();
		$oneDay = new DateInterval( 'P1D' );
		$tomorrowDate = $todayDate->add( $oneDay );
		$tomorrowDateTimestamp = new MWTimestamp( $tomorrowDate );
		$tomorrowTimestampStr = $tomorrowDateTimestamp->getTimestamp( TS_ISO_8601 );
		return [
			"action=echoarticlereminder&pageid=1&timestamp=$tomorrowTimestampStr&comment=example"
				=> 'apihelp-echoarticlereminder-example-1',
			"action=echoarticlereminder&title=Main_Page&timestamp=$tomorrowTimestampStr"
				=> 'apihelp-echoarticlereminder-example-2',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Echo_(Notifications)/API';
	}
}
