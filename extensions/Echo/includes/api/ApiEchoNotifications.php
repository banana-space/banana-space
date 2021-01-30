<?php

class ApiEchoNotifications extends ApiQueryBase {
	use ApiCrossWiki;

	/**
	 * @var bool
	 */
	protected $crossWikiSummary = false;

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'not' );
	}

	public function execute() {
		// To avoid API warning, register the parameter used to bust browser cache
		$this->getMain()->getVal( '_' );

		if ( $this->getUser()->isAnon() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'login-required' );
		}

		$params = $this->extractRequestParams();

		/* @deprecated */
		if ( $params['format'] === 'flyout' ) {
			$this->addDeprecation( 'apiwarn-echo-deprecation-flyout',
				'action=query&meta=notifications&notformat=flyout' );
		} elseif ( $params['format'] === 'html' ) {
			$this->addDeprecation( 'apiwarn-echo-deprecation-html',
				'action=query&meta=notifications&notformat=html' );
		}

		if ( $this->allowCrossWikiNotifications() ) {
			$this->crossWikiSummary = $params['crosswikisummary'];
		}

		$results = [];
		if ( in_array( wfWikiID(), $this->getRequestedWikis() ) ) {
			$results[wfWikiID()] = $this->getLocalNotifications( $params );
		}

		if ( $this->getRequestedForeignWikis() ) {
			$foreignResults = $this->getFromForeign();
			foreach ( $foreignResults as $wiki => $result ) {
				if ( isset( $result['query']['notifications'] ) ) {
					$results[$wiki] = $result['query']['notifications'];
				}
			}
		}

		// after getting local & foreign results, merge them all together
		$result = $this->mergeResults( $results, $params );
		if ( $params['groupbysection'] ) {
			foreach ( $params['sections'] as $section ) {
				if ( in_array( 'list', $params['prop'] ) ) {
					$this->getResult()->setIndexedTagName( $result[$section]['list'], 'notification' );
				}
			}
		} else {
			if ( in_array( 'list', $params['prop'] ) ) {
				$this->getResult()->setIndexedTagName( $result['list'], 'notification' );
			}
		}
		$this->getResult()->addValue( 'query', $this->getModuleName(), $result );
	}

	/**
	 * @param array $params
	 * @return array
	 */
	protected function getLocalNotifications( array $params ) {
		$user = $this->getUser();
		$prop = $params['prop'];
		$titles = null;
		if ( $params['titles'] ) {
			$titles = array_values( array_filter( array_map( [ Title::class, 'newFromText' ], $params['titles'] ) ) );
			if ( in_array( '[]', $params['titles'] ) ) {
				$titles[] = null;
			}
		}

		$result = [];
		if ( in_array( 'list', $prop ) ) {
			// Group notification results by section
			if ( $params['groupbysection'] ) {
				foreach ( $params['sections'] as $section ) {
					$result[$section] = $this->getSectionPropList(
						$user, $section, $params['filter'], $params['limit'],
						$params[$section . 'continue'], $params['format'],
						$titles, $params[$section . 'unreadfirst'], $params['bundle']
					);

					if ( $this->crossWikiSummary ) {
						// insert fake notification for foreign notifications
						$foreignNotification = $this->makeForeignNotification( $user, $params['format'], $section );
						if ( $foreignNotification ) {
							array_unshift( $result[$section]['list'], $foreignNotification );
						}
					}
				}
			} else {
				$attributeManager = EchoAttributeManager::newFromGlobalVars();
				$result = $this->getPropList(
					$user,
					$attributeManager->getUserEnabledEventsbySections( $user, 'web', $params['sections'] ),
					$params['filter'], $params['limit'], $params['continue'], $params['format'],
					$titles, $params['unreadfirst'], $params['bundle']
				);

				// if exactly 1 section is specified, we consider only that section, otherwise
				// we pass ALL to consider all foreign notifications
				$section = count( $params['sections'] ) === 1
					? reset( $params['sections'] )
					: EchoAttributeManager::ALL;
				if ( $this->crossWikiSummary ) {
					$foreignNotification = $this->makeForeignNotification( $user, $params['format'], $section );
					if ( $foreignNotification ) {
						array_unshift( $result['list'], $foreignNotification );
					}
				}
			}
		}

		if ( in_array( 'count', $prop ) ) {
			$result = array_merge_recursive(
				$result,
				$this->getPropCount( $user, $params['sections'], $params['groupbysection'] )
			);
		}

		if ( in_array( 'seenTime', $prop ) ) {
			$result = array_merge_recursive(
				$result,
				$this->getPropSeenTime( $user, $params['sections'], $params['groupbysection'] )
			);
		}

		return $result;
	}

	/**
	 * Internal method for getting the property 'list' data for individual section
	 * @param User $user
	 * @param string $section 'alert' or 'message'
	 * @param string[] $filter 'all', 'read' or 'unread'
	 * @param int $limit
	 * @param string $continue
	 * @param string $format
	 * @param Title[]|null $titles
	 * @param bool $unreadFirst
	 * @param bool $bundle
	 * @return array
	 */
	protected function getSectionPropList(
		User $user,
		$section,
		$filter,
		$limit,
		$continue,
		$format,
		array $titles = null,
		$unreadFirst = false,
		$bundle = false
	) {
		$attributeManager = EchoAttributeManager::newFromGlobalVars();
		$sectionEvents = $attributeManager->getUserEnabledEventsbySections( $user, 'web', [ $section ] );

		if ( !$sectionEvents ) {
			$result = [
				'list' => [],
				'continue' => null
			];
		} else {
			$result = $this->getPropList(
				$user, $sectionEvents, $filter, $limit, $continue, $format, $titles, $unreadFirst, $bundle
			);
		}

		return $result;
	}

	/**
	 * Internal helper method for getting property 'list' data, this is based
	 * on the event types specified in the arguments and it could be event types
	 * of a set of sections or a single section
	 * @param User $user
	 * @param string[] $eventTypes
	 * @param string[] $filter 'all', 'read' or 'unread'
	 * @param int $limit
	 * @param string $continue
	 * @param string $format
	 * @param Title[]|null $titles
	 * @param bool $unreadFirst
	 * @param bool $bundle
	 * @return array
	 */
	protected function getPropList(
		User $user,
		array $eventTypes,
		$filter,
		$limit,
		$continue,
		$format,
		array $titles = null,
		$unreadFirst = false,
		$bundle = false
	) {
		$result = [
			'list' => [],
			'continue' => null
		];

		$notifMapper = new EchoNotificationMapper();

		// check if we want both read & unread...
		if ( in_array( 'read', $filter ) && in_array( '!read', $filter ) ) {
			// Prefer unread notifications. We don't care about next offset in this case
			if ( $unreadFirst ) {
				// query for unread notifications past 'continue' (offset)
				$notifs = $notifMapper->fetchUnreadByUser( $user, $limit + 1, $continue, $eventTypes, $titles );

				/*
				 * 'continue' has a timestamp & id (to start with, in case
				 * there would be multiple events with that same timestamp)
				 * Unread notifications should always load first, but may be
				 * older than read ones, but we can work with current
				 * 'continue' format:
				 * * if there's no continue, first load unread notifications
				 * * if there's a continue, fetch unread notifications first
				 * * if there are no unread ones, continue must've been
				 *   about read notifications: fetch 'em
				 * * if there are unread ones but first one doesn't match
				 *   continue id, it must've been about read notifications:
				 *   discard unread & fetch read
				 */
				if ( $notifs && $continue ) {
					/** @var EchoNotification $first */
					$first = reset( $notifs );
					$continueId = intval( trim( strrchr( $continue, '|' ), '|' ) );
					if ( $first->getEvent()->getId() !== $continueId ) {
						// notification doesn't match continue id, it must've been
						// about read notifications: discard all unread ones
						$notifs = [];
					}
				}

				// If there are less unread notifications than we requested,
				// then fill the result with some read notifications
				$count = count( $notifs );
				// we need 1 more than $limit, so we can respond 'continue'
				if ( $count <= $limit ) {
					// Query planner should be smart enough that passing a short list of ids to exclude
					// will only visit at most that number of extra rows.
					$mixedNotifs = $notifMapper->fetchByUser(
						$user,
						$limit - $count + 1,
						// if there were unread notifications, 'continue' was for
						// unread notifications and we should start fetching read
						// notifications from start
						$count > 0 ? null : $continue,
						$eventTypes,
						array_keys( $notifs ),
						$titles
					);
					foreach ( $mixedNotifs as $notif ) {
						$notifs[$notif->getEvent()->getId()] = $notif;
					}
				}
			} else {
				$notifs = $notifMapper->fetchByUser( $user, $limit + 1, $continue, $eventTypes, [], $titles );
			}
		} elseif ( in_array( 'read', $filter ) ) {
			$notifs = $notifMapper->fetchReadByUser( $user, $limit + 1, $continue, $eventTypes, $titles );
		} else { // = if ( in_array( '!read', $filter ) ) {
			$notifs = $notifMapper->fetchUnreadByUser( $user, $limit + 1, $continue, $eventTypes, $titles );
		}

		// get $overfetchedItem before bundling and rendering so that it is not affected by filtering
		/** @var EchoNotification $overfetchedItem */
		$overfetchedItem = count( $notifs ) > $limit ? array_pop( $notifs ) : null;

		$bundler = null;
		if ( $bundle ) {
			$bundler = new Bundler();
			$notifs = $bundler->bundle( $notifs );
		}

		while ( $notifs !== [] ) {
			/** @var EchoNotification $notif */
			$notif = array_shift( $notifs );
			$output = EchoDataOutputFormatter::formatOutput( $notif, $format, $user, $this->getLanguage() );
			if ( $output !== false ) {
				$result['list'][] = $output;
			} elseif ( $bundler && $notif->getBundledNotifications() ) {
				// when the bundle_base gets filtered out, bundled notifications
				// have to be re-bundled and formatted
				$notifs = array_merge( $bundler->bundle( $notif->getBundledNotifications() ), $notifs );
			}
		}

		// Generate offset if necessary
		if ( $overfetchedItem ) {
			// @todo: what to do with this when fetching from multiple wikis?
			$timestamp = wfTimestamp( TS_UNIX, $overfetchedItem->getTimestamp() );
			$id = $overfetchedItem->getEvent()->getId();
			$result['continue'] = $timestamp . '|' . $id;
		}

		return $result;
	}

	/**
	 * Internal helper method for getting property 'count' data
	 * @param User $user
	 * @param string[] $sections
	 * @param bool $groupBySection
	 * @return array
	 */
	protected function getPropCount( User $user, array $sections, $groupBySection ) {
		$result = [];
		$notifUser = MWEchoNotifUser::newFromUser( $user );
		$global = $this->crossWikiSummary ? 'preference' : false;

		$totalRawCount = 0;
		foreach ( $sections as $section ) {
			$rawCount = $notifUser->getNotificationCount( $section, $global );
			if ( $groupBySection ) {
				$result[$section]['rawcount'] = $rawCount;
				$result[$section]['count'] = EchoNotificationController::formatNotificationCount( $rawCount );
			}
			$totalRawCount += $rawCount;
		}
		$result['rawcount'] = $totalRawCount;
		$result['count'] = EchoNotificationController::formatNotificationCount( $totalRawCount );

		return $result;
	}

	/**
	 * Internal helper method for getting property 'seenTime' data
	 * @param User $user
	 * @param string[] $sections
	 * @param bool $groupBySection
	 * @return array
	 */
	protected function getPropSeenTime( User $user, array $sections, $groupBySection ) {
		$result = [];
		$seenTimeHelper = EchoSeenTime::newFromUser( $user );

		if ( $groupBySection ) {
			foreach ( $sections as $section ) {
				$result[$section]['seenTime'] = $seenTimeHelper->getTime( $section, TS_ISO_8601 );
			}
		} else {
			$result['seenTime'] = [];
			foreach ( $sections as $section ) {
				$result['seenTime'][$section] = $seenTimeHelper->getTime( $section, TS_ISO_8601 );
			}
		}

		return $result;
	}

	/**
	 * Build and format a "fake" notification to represent foreign notifications.
	 * @param User $user
	 * @param string $format
	 * @param string $section
	 * @return array|false A formatted notification, or false if there are no foreign notifications
	 */
	protected function makeForeignNotification(
		User $user,
		$format,
		$section = EchoAttributeManager::ALL
	) {
		$wikis = $this->getForeignNotifications()->getWikis( $section );
		$count = $this->getForeignNotifications()->getCount( $section );
		$maxTimestamp = $this->getForeignNotifications()->getTimestamp( $section );
		$timestampsByWiki = [];
		foreach ( $wikis as $wiki ) {
			$timestampsByWiki[$wiki] = $this->getForeignNotifications()->getWikiTimestamp( $wiki, $section );
		}

		if ( $count === 0 || $wikis === [] ) {
			return false;
		}

		// Sort wikis by timestamp, in descending order (newest first)
		usort( $wikis, function ( $a, $b ) use ( $section, $timestampsByWiki ) {
			return (int)$timestampsByWiki[$b]->getTimestamp( TS_UNIX )
				- (int)$timestampsByWiki[$a]->getTimestamp( TS_UNIX );
		} );

		$row = new stdClass;
		$row->event_id = -1;
		$row->event_type = 'foreign';
		$row->event_variant = null;
		$row->event_agent_id = $user->getId();
		$row->event_agent_ip = null;
		$row->event_page_id = null;
		$row->event_extra = serialize( [
			'section' => $section ?: 'all',
			'wikis' => $wikis,
			'count' => $count
		] );
		$row->event_deleted = 0;

		$row->notification_user = $user->getId();
		$row->notification_timestamp = $maxTimestamp;
		$row->notification_read_timestamp = null;
		$row->notification_bundle_hash = md5( 'bogus' );

		// Format output like any other notification
		$notif = EchoNotification::newFromRow( $row );
		$output = EchoDataOutputFormatter::formatOutput( $notif, $format, $user, $this->getLanguage() );

		// Add cross-wiki-specific data
		$output['section'] = $section ?: 'all';
		$output['count'] = $count;
		$output['sources'] = EchoForeignNotifications::getApiEndpoints( $wikis );
		// Add timestamp information
		foreach ( $output['sources'] as $wiki => &$data ) {
			$data['ts'] = $timestampsByWiki[$wiki]->getTimestamp( TS_ISO_8601 );
		}
		return $output;
	}

	protected function getForeignQueryParams() {
		$params = $this->getRequest()->getValues();

		// don't request cross-wiki notification summaries
		unset( $params['notcrosswikisummary'] );

		return $params;
	}

	/**
	 * @param array[] $results
	 * @param array $params
	 * @return array
	 */
	protected function mergeResults( array $results, array $params ) {
		$master = array_shift( $results );
		if ( !$master ) {
			$master = [];
		}

		if ( in_array( 'list', $params['prop'] ) ) {
			$master = $this->mergeList( $master, $results, $params['groupbysection'] );
		}

		if ( in_array( 'count', $params['prop'] ) && !$this->crossWikiSummary ) {
			// if crosswiki data was requested, the count in $master
			// is accurate already
			// otherwise, we'll want to combine counts for all wikis
			$master = $this->mergeCount( $master, $results, $params['groupbysection'] );
		}

		return $master;
	}

	/**
	 * @param array $master
	 * @param array[] $results
	 * @param bool $groupBySection
	 * @return array
	 */
	protected function mergeList( array $master, array $results, $groupBySection ) {
		// sort all notifications by timestamp: most recent first
		$sort = function ( $a, $b ) {
			return $a['timestamp']['utcunix'] - $b['timestamp']['utcunix'];
		};

		if ( $groupBySection ) {
			foreach ( EchoAttributeManager::$sections as $section ) {
				if ( !isset( $master[$section]['list'] ) ) {
					$master[$section]['list'] = [];
				}
				foreach ( $results as $result ) {
					$master[$section]['list'] = array_merge( $master[$section]['list'], $result[$section]['list'] );
				}
				usort( $master[$section]['list'], $sort );
			}
		} else {
			if ( !isset( $master['list'] ) || !is_array( $master['list'] ) ) {
				$master['list'] = [];
			}
			foreach ( $results as $result ) {
				$master['list'] = array_merge( $master['list'], $result['list'] );
			}
			usort( $master['list'], $sort );
		}

		return $master;
	}

	/**
	 * @param array $master
	 * @param array[] $results
	 * @param bool $groupBySection
	 * @return array
	 */
	protected function mergeCount( array $master, array $results, $groupBySection ) {
		if ( $groupBySection ) {
			foreach ( EchoAttributeManager::$sections as $section ) {
				if ( !isset( $master[$section]['rawcount'] ) ) {
					$master[$section]['rawcount'] = 0;
				}
				foreach ( $results as $result ) {
					$master[$section]['rawcount'] += $result[$section]['rawcount'];
				}
				$master[$section]['count'] = EchoNotificationController::formatNotificationCount(
					$master[$section]['rawcount'] );
			}
		}

		if ( !isset( $master['rawcount'] ) ) {
			$master['rawcount'] = 0;
		}
		foreach ( $results as $result ) {
			// regardless of groupbysection, totals are always included
			$master['rawcount'] += $result['rawcount'];
		}
		$master['count'] = EchoNotificationController::formatNotificationCount( $master['rawcount'] );

		return $master;
	}

	public function getAllowedParams() {
		$sections = EchoAttributeManager::$sections;

		$params = $this->getCrossWikiParams() + [
			'filter' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_DFLT => 'read|!read',
				ApiBase::PARAM_TYPE => [
					'read',
					'!read',
				],
			],
			'prop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'list',
					'count',
					'seenTime',
				],
				ApiBase::PARAM_DFLT => 'list',
			],
			'sections' => [
				ApiBase::PARAM_DFLT => implode( '|', $sections ),
				ApiBase::PARAM_TYPE => $sections,
				ApiBase::PARAM_ISMULTI => true,
			],
			'groupbysection' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			],
			'format' => [
				ApiBase::PARAM_TYPE => [
					'model',
					'special',
					'flyout', /* @deprecated */
					'html', /* @deprecated */
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => 20,
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_SML1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_SML2,
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'unreadfirst' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			],
			'titles' => [
				ApiBase::PARAM_ISMULTI => true,
			],
			'bundle' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			],
		];
		foreach ( $sections as $section ) {
			$params[$section . 'continue'] = null;
			$params[$section . 'unreadfirst'] = [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			];
		}

		if ( $this->allowCrossWikiNotifications() ) {
			$params += [
				// create "x notifications from y wikis" notification bundle &
				// include unread counts from other wikis in prop=count results
				'crosswikisummary' => [
					ApiBase::PARAM_TYPE => 'boolean',
					ApiBase::PARAM_DFLT => false,
				],
			];
		}

		return $params;
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&meta=notifications'
				=> 'apihelp-query+notifications-example-1',
			'action=query&meta=notifications&notprop=count&notsections=alert|message&notgroupbysection=1'
				=> 'apihelp-query+notifications-example-2',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Echo_(Notifications)/API';
	}
}
