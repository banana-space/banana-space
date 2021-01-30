<?php
// @phan-file-suppress PhanUndeclaredMethod This is a trait, and phan is confused by $this
/**
 * Trait that adds cross-wiki functionality to an API module. For mixing into ApiBase subclasses.
 *
 * In addition to mixing in this trait, you have to do the following in your API module:
 * - In your getAllowedParams() method, merge in the return value of getCrossWikiParams()
 * - In your execute() method, call getFromForeign() somewhere and do something with the result
 * - Optionally, override getForeignQueryParams() to customize what is sent to the foreign wikis
 */
trait ApiCrossWiki {
	/**
	 * @var EchoForeignNotifications
	 */
	protected $foreignNotifications;

	/**
	 * This will take the current API call (with all of its params) and execute
	 * it on all foreign wikis, returning an array of results per wiki.
	 *
	 * @param array|null $wikis List of wikis to query. Defaults to the result of getRequestedForeignWikis().
	 * @param array $paramOverrides Request parameter overrides
	 * @return array[]
	 * @throws Exception
	 */
	protected function getFromForeign( array $wikis = null, array $paramOverrides = [] ) {
		$wikis = $wikis ?? $this->getRequestedForeignWikis();
		if ( $wikis === [] ) {
			return [];
		}
		$tokenType = $this->needsToken();
		$foreignReq = new EchoForeignWikiRequest(
			$this->getUser(),
			$paramOverrides + $this->getForeignQueryParams(),
			$wikis,
			$this->getModulePrefix() . 'wikis',
			$tokenType !== false ? $tokenType : null
		);
		return $foreignReq->execute();
	}

	/**
	 * Get the query parameters to use for the foreign API requests.
	 * Implementing classes should override this if they need to customize
	 * the parameters.
	 * @return array Query parameters
	 */
	protected function getForeignQueryParams() {
		return $this->getRequest()->getValues();
	}

	/**
	 * @return bool
	 */
	protected function allowCrossWikiNotifications() {
		global $wgEchoCrossWikiNotifications;
		return $wgEchoCrossWikiNotifications;
	}

	/**
	 * This is basically equivalent to $params['wikis'], but some added checks:
	 * - `*` will expand to "all wikis with unread notifications"
	 * - if `$wgEchoCrossWikiNotifications` is off, foreign wikis will be excluded
	 *
	 * @return string[]
	 */
	protected function getRequestedWikis() {
		$params = $this->extractRequestParams();

		// if wiki is omitted from params, that's because crosswiki is/was not
		// available, and it'll default to current wiki
		$wikis = $params['wikis'] ?? [ wfWikiID() ];

		if ( array_search( '*', $wikis ) !== false ) {
			// expand `*` to all foreign wikis with unread notifications + local
			$wikis = array_merge(
				[ wfWikiID() ],
				$this->getForeignWikisWithUnreadNotifications()
			);
		}

		if ( !$this->allowCrossWikiNotifications() ) {
			// exclude foreign wikis if x-wiki is not enabled
			$wikis = array_intersect_key( [ wfWikiID() ], $wikis );
		}

		return $wikis;
	}

	/**
	 * @return string[] Wiki names
	 */
	protected function getRequestedForeignWikis() {
		return array_diff( $this->getRequestedWikis(), [ wfWikiID() ] );
	}

	/**
	 * @return EchoForeignNotifications
	 */
	protected function getForeignNotifications() {
		if ( $this->foreignNotifications === null ) {
			$this->foreignNotifications = new EchoForeignNotifications( $this->getUser() );
		}
		return $this->foreignNotifications;
	}

	/**
	 * @return string[] Wiki names
	 */
	protected function getForeignWikisWithUnreadNotifications() {
		return $this->getForeignNotifications()->getWikis();
	}

	/**
	 * @return array[]
	 */
	public function getCrossWikiParams() {
		global $wgConf;

		$params = [];

		if ( $this->allowCrossWikiNotifications() ) {
			$params += [
				// fetch notifications from multiple wikis
				'wikis' => [
					ApiBase::PARAM_ISMULTI => true,
					ApiBase::PARAM_DFLT => wfWikiID(),
					// `*` will let you immediately fetch from all wikis that have
					// unread notifications, without having to look them up first
					ApiBase::PARAM_TYPE => array_unique( array_merge( $wgConf->wikis, [ wfWikiID(), '*' ] ) ),
				],
			];
		}

		return $params;
	}
}
