<?php

class ApiEchoMarkRead extends ApiBase {
	use ApiCrossWiki;

	public function execute() {
		// To avoid API warning, register the parameter used to bust browser cache
		$this->getMain()->getVal( '_' );

		$user = $this->getUser();
		if ( $user->isAnon() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'login-required' );
		} elseif ( MWEchoDbFactory::newFromDefault()->isReadOnly() ) {
			$this->dieReadOnly();
		}

		$notifUser = MWEchoNotifUser::newFromUser( $user );

		$params = $this->extractRequestParams();

		// Mark as read/unread locally, if requested
		if ( in_array( wfWikiID(), $this->getRequestedWikis() ) ) {
			// There is no need to trigger markRead if all notifications are read
			if ( $notifUser->getLocalNotificationCount() > 0 ) {
				if ( $params['list'] ) {
					// Make sure there is a limit to the update
					$notifUser->markRead( array_slice( $params['list'], 0, ApiBase::LIMIT_SML2 ) );
					// Mark all as read
				} elseif ( $params['all'] ) {
					$notifUser->markAllRead();
					// Mark all as read for sections
				} elseif ( $params['sections'] ) {
					$notifUser->markAllRead( $params['sections'] );
				}
			}

			// Mark as unread
			if ( $params['unreadlist'] !== null && $params['unreadlist'] !== [] ) {
				// Make sure there is a limit to the update
				$notifUser->markUnRead( array_slice( $params['unreadlist'], 0, ApiBase::LIMIT_SML2 ) );
			}
		}

		$foreignResults = $this->getFromForeign();

		$result = [
			'result' => 'success'
		];

		foreach ( $foreignResults as $wiki => $foreignResult ) {
			if ( isset( $foreignResult['error'] ) ) {
				$result['errors'][$wiki] = $foreignResult['error'];
			}
		}

		$rawCount = 0;
		foreach ( EchoAttributeManager::$sections as $section ) {
			$rawSectionCount = $notifUser->getNotificationCount( $section );
			$result[$section]['rawcount'] = $rawSectionCount;
			$result[$section]['count'] = EchoNotificationController::formatNotificationCount( $rawSectionCount );
			$rawCount += $rawSectionCount;
		}

		$result += [
			'rawcount' => $rawCount,
			'count' => EchoNotificationController::formatNotificationCount( $rawCount ),
		];

		$this->getResult()->addValue( 'query', $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return $this->getCrossWikiParams() + [
			'list' => [
				ApiBase::PARAM_ISMULTI => true,
			],
			'unreadlist' => [
				ApiBase::PARAM_ISMULTI => true,
			],
			'all' => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'boolean'
			],
			'sections' => [
				ApiBase::PARAM_TYPE => EchoAttributeManager::$sections,
				ApiBase::PARAM_ISMULTI => true,
			],
			'token' => [
				ApiBase::PARAM_REQUIRED => true,
			]
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
		return [
			'action=echomarkread&list=8'
				=> 'apihelp-echomarkread-example-1',
			'action=echomarkread&all=true'
				=> 'apihelp-echomarkread-example-2',
			'action=echomarkread&unreadlist=1'
				=> 'apihelp-echomarkread-example-3',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Echo_(Notifications)/API';
	}
}
