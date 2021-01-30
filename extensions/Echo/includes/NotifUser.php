<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\Database;

/**
 * Entity that represents a notification target user
 */
class MWEchoNotifUser {

	/**
	 * Notification target user
	 * @var User
	 */
	private $mUser;

	/**
	 * Object cache
	 * @var WANObjectCache
	 */
	private $cache;

	/**
	 * Database access gateway
	 * @var EchoUserNotificationGateway
	 */
	private $userNotifGateway;

	/**
	 * Notification mapper
	 * @var EchoNotificationMapper
	 */
	private $notifMapper;

	/**
	 * Target page mapper
	 * @var EchoTargetPageMapper
	 */
	private $targetPageMapper;

	/**
	 * @var EchoForeignNotifications|null
	 */
	private $foreignNotifications;

	/**
	 * @var array[]|null
	 */
	private $localCountsAndTimestamps;

	/**
	 * @var array[]|null
	 */
	private $globalCountsAndTimestamps;

	/**
	 * @var array[]|null
	 */
	private $mForeignData;

	// The max notification count shown in badge

	// The max number shown in bundled message, eg, <user> and 99+ others <action>.
	// This is really a totally separate thing, and could be its own constant.

	// WARNING: If you change this, you should also change all references in the
	// i18n messages (100 and 99) in all repositories using Echo.
	const MAX_BADGE_COUNT = 99;

	const CACHE_TTL = 86400;
	const CACHE_KEY = 'echo-notification-counts';
	const CHECK_KEY = 'echo-notification-updated';

	/**
	 * Usually client code doesn't need to initialize the object directly
	 * because it could be obtained from factory method newFromUser()
	 * @param User $user
	 * @param WANObjectCache $cache
	 * @param EchoUserNotificationGateway $userNotifGateway
	 * @param EchoNotificationMapper $notifMapper
	 * @param EchoTargetPageMapper $targetPageMapper
	 */
	public function __construct(
		User $user,
		WANObjectCache $cache,
		EchoUserNotificationGateway $userNotifGateway,
		EchoNotificationMapper $notifMapper,
		EchoTargetPageMapper $targetPageMapper
	) {
		$this->mUser = $user;
		$this->userNotifGateway = $userNotifGateway;
		$this->cache = $cache;
		$this->notifMapper = $notifMapper;
		$this->targetPageMapper = $targetPageMapper;
	}

	/**
	 * Factory method
	 * @param User $user
	 * @throws MWException
	 * @return MWEchoNotifUser
	 */
	public static function newFromUser( User $user ) {
		if ( $user->isAnon() ) {
			throw new MWException( 'User must be logged in to view notification!' );
		}

		return new MWEchoNotifUser(
			$user,
			MediaWikiServices::getInstance()->getMainWANObjectCache(),
			new EchoUserNotificationGateway(
				$user,
				MWEchoDbFactory::newFromDefault(),
				MediaWikiServices::getInstance()->getMainConfig()
			),
			new EchoNotificationMapper(),
			new EchoTargetPageMapper()
		);
	}

	/**
	 * Mark all edit-user-talk notifications as read. This is called when a user visits their user talk page.
	 */
	public function clearUserTalkNotifications() {
		$this->markRead(
			$this->userNotifGateway->getUnreadNotifications(
				'edit-user-talk'
			)
		);
	}

	/**
	 * Get message count for this user.
	 *
	 * @return int
	 */
	public function getMessageCount() {
		return $this->getNotificationCount( EchoAttributeManager::MESSAGE );
	}

	/**
	 * Get alert count for this user.
	 *
	 * @return int
	 */
	public function getAlertCount() {
		return $this->getNotificationCount( EchoAttributeManager::ALERT );
	}

	/**
	 * Get the number of unread local notifications in a given section. This does not include
	 * foreign notifications, even if the user has cross-wiki notifications enabled.
	 *
	 * @param string $section Notification section
	 * @return int
	 */
	public function getLocalNotificationCount( $section = EchoAttributeManager::ALL ) {
		return $this->getNotificationCount( $section, false );
	}

	/**
	 * Retrieves number of unread notifications that a user has, would return
	 * MWEchoNotifUser::MAX_BADGE_COUNT + 1 at most.
	 *
	 * If $wgEchoCrossWikiNotifications is disabled, the $global parameter is ignored.
	 *
	 * @param string $section Notification section
	 * @param bool|string $global Whether to include foreign notifications.
	 *   If set to 'preference', uses the user's preference.
	 * @return int
	 */
	public function getNotificationCount( $section = EchoAttributeManager::ALL, $global = 'preference' ) {
		if ( $this->mUser->isAnon() ) {
			return 0;
		}

		global $wgEchoCrossWikiNotifications;
		if ( !$wgEchoCrossWikiNotifications ) {
			// Ignore the $global parameter
			$global = false;
		}

		if ( $global === 'preference' ) {
			$global = $this->getForeignNotifications()->isEnabledByUser();
		}

		$data = $this->getCountsAndTimestamps( $global );
		$count = $data[$global ? 'global' : 'local'][$section]['count'];
		return (int)$count;
	}

	/**
	 * Get the timestamp of the latest unread alert
	 *
	 * @return bool|MWTimestamp Timestamp of latest unread alert, or false if there are no unread alerts.
	 */
	public function getLastUnreadAlertTime() {
		return $this->getLastUnreadNotificationTime( EchoAttributeManager::ALERT );
	}

	/**
	 * Get the timestamp of the latest unread message
	 *
	 * @return bool|MWTimestamp
	 */
	public function getLastUnreadMessageTime() {
		return $this->getLastUnreadNotificationTime( EchoAttributeManager::MESSAGE );
	}

	/**
	 * Returns the timestamp of the last unread notification.
	 *
	 * If $wgEchoCrossWikiNotifications is disabled, the $global parameter is ignored.
	 *
	 * @param string $section Notification section
	 * @param bool|string $global Whether to include foreign notifications.
	 *   If set to 'preference', uses the user's preference.
	 * @return bool|MWTimestamp Timestamp of latest unread message, or false if there are no unread messages.
	 */
	public function getLastUnreadNotificationTime( $section = EchoAttributeManager::ALL, $global = 'preference' ) {
		if ( $this->mUser->isAnon() ) {
			return false;
		}

		global $wgEchoCrossWikiNotifications;
		if ( !$wgEchoCrossWikiNotifications ) {
			// Ignore the $global parameter
			$global = false;
		}

		if ( $global === 'preference' ) {
			$global = $this->getForeignNotifications()->isEnabledByUser();
		}

		$data = $this->getCountsAndTimestamps( $global );
		$timestamp = $data[$global ? 'global' : 'local'][$section]['timestamp'];
		return $timestamp === -1 ? false : new MWTimestamp( $timestamp );
	}

	/**
	 * Mark one or more notifications read for a user.
	 * @param array $eventIds Array of event IDs to mark read
	 * @return bool Returns true when data has been updated in DB, false on
	 *   failure, or when there was nothing to update
	 */
	public function markRead( $eventIds ) {
		$eventIds = array_filter( (array)$eventIds, 'is_numeric' );
		if ( !$eventIds || wfReadOnly() ) {
			return false;
		}

		$updated = $this->userNotifGateway->markRead( $eventIds );
		if ( $updated ) {
			// Update notification count in cache
			$this->resetNotificationCount();

			// After this 'mark read', is there any unread edit-user-talk
			// remaining?  If not, we should clear the newtalk flag.
			$talkPageNotificationManager = MediaWikiServices::getInstance()
				->getTalkPageNotificationManager();
			if ( $talkPageNotificationManager->userHasNewMessages( $this->mUser ) ) {
				$attributeManager = EchoAttributeManager::newFromGlobalVars();
				$categoryMap = $attributeManager->getEventsByCategory();
				$usertalkTypes = $categoryMap['edit-user-talk'];
				$unreadEditUserTalk = $this->notifMapper->fetchUnreadByUser(
					$this->mUser,
					1,
					null,
					$usertalkTypes,
					null,
					DB_MASTER
				);
				if ( $unreadEditUserTalk === [] ) {
					$talkPageNotificationManager->removeUserHasNewMessages( $this->mUser );
				}
			}
		}

		return $updated;
	}

	/**
	 * Mark one or more notifications unread for a user.
	 * @param array $eventIds Array of event IDs to mark unread
	 * @return bool Returns true when data has been updated in DB, false on
	 *   failure, or when there was nothing to update
	 */
	public function markUnRead( $eventIds ) {
		$eventIds = array_filter( (array)$eventIds, 'is_numeric' );
		if ( !$eventIds || wfReadOnly() ) {
			return false;
		}

		$updated = $this->userNotifGateway->markUnRead( $eventIds );
		if ( $updated ) {
			// Update notification count in cache
			$this->resetNotificationCount();

			// After this 'mark unread', is there any unread edit-user-talk?
			// If so, we should add the edit-user-talk flag
			$talkPageNotificationManager = MediaWikiServices::getInstance()
				->getTalkPageNotificationManager();
			if ( !$talkPageNotificationManager->userHasNewMessages( $this->mUser ) ) {
				$attributeManager = EchoAttributeManager::newFromGlobalVars();
				$categoryMap = $attributeManager->getEventsByCategory();
				$usertalkTypes = $categoryMap['edit-user-talk'];
				$unreadEditUserTalk = $this->notifMapper->fetchUnreadByUser(
					$this->mUser,
					1,
					null,
					$usertalkTypes,
					null,
					DB_MASTER
				);
				if ( $unreadEditUserTalk !== [] ) {
					$talkPageNotificationManager->setUserHasNewMessages( $this->mUser );
				}
			}
		}

		return $updated;
	}

	/**
	 * Attempt to mark all or sections of notifications as read, this only
	 * updates up to $wgEchoMaxUpdateCount records per request, see more
	 * detail about this in Echo.php, the other reason is that mediawiki
	 * database interface doesn't support updateJoin() that would update
	 * across multiple tables, we would visit this later
	 *
	 * @param string[] $sections
	 * @return bool
	 */
	public function markAllRead( array $sections = [ EchoAttributeManager::ALL ] ) {
		if ( wfReadOnly() ) {
			return false;
		}

		global $wgEchoMaxUpdateCount;

		// Mark all sections as read if this is the case
		if ( in_array( EchoAttributeManager::ALL, $sections ) ) {
			$sections = EchoAttributeManager::$sections;
		}

		$attributeManager = EchoAttributeManager::newFromGlobalVars();
		$eventTypes = $attributeManager->getUserEnabledEventsbySections( $this->mUser, 'web', $sections );

		$notifs = $this->notifMapper->fetchUnreadByUser( $this->mUser, $wgEchoMaxUpdateCount, null, $eventTypes );

		$eventIds = array_filter(
			array_map( function ( EchoNotification $notif ) {
				// This should not happen at all, but use 0 in
				// such case so to keep the code running
				if ( $notif->getEvent() ) {
					return $notif->getEvent()->getId();
				}
				return 0;
			}, $notifs )
		);

		$updated = $this->markRead( $eventIds );
		if ( $updated ) {
			// Delete records from echo_target_page
			/**
			 * Keep the 'echo_target_page' records so they can be used for moderation.
			 */
			// $this->targetPageMapper->deleteByUserEvents( $this->mUser, $eventIds );
		}

		return $updated;
	}

	/**
	 * Mark one of more notifications as read on a foreign wiki.
	 *
	 * @param int[] $eventIds Event IDs to mark as read
	 * @param string $wiki Wiki name
	 */
	public function markReadForeign( array $eventIds, $wiki ) {
		$foreignReq = new EchoForeignWikiRequest(
			$this->mUser,
			[
				'action' => 'echomarkread',
				'list' => implode( '|', $eventIds ),
			],
			[ $wiki ],
			'wikis',
			'csrf'
		);
		$foreignReq->execute();
	}

	/**
	 * Get information about a set of unread notifications on a foreign wiki.
	 *
	 * @param int[] $eventIds Event IDs to look up. Only unread notifications can be found.
	 * @param string $wiki Wiki name
	 * @return array[] Array of notification data as returned by api.php, keyed by event ID
	 */
	public function getForeignNotificationInfo( array $eventIds, $wiki ) {
		$foreignReq = new EchoForeignWikiRequest(
			$this->mUser,
			[
				'action' => 'query',
				'meta' => 'notifications',
				'notprop' => 'list',
				'notfilter' => '!read',
				'notlimit' => 'max'
			],
			[ $wiki ],
			'notwikis'
		);
		$foreignResults = $foreignReq->execute();
		$list = $foreignResults[$wiki]['query']['notifications']['list'] ?? [];

		$result = [];
		foreach ( $list as $notif ) {
			if ( in_array( $notif['id'], $eventIds ) ) {
				$result[$notif['id']] = $notif;
			}
		}
		return $result;
	}

	/**
	 * Invalidate cache and update echo_unread_wikis if x-wiki notifications is enabled.
	 *
	 * This updates the user's touched timestamp, as well as the value returned by getGlobalUpdateTime().
	 *
	 * NOTE: Consider calling this function from a deferred update, since it will read from and write to
	 * the master DB if cross-wiki notifications are enabled.
	 */
	public function resetNotificationCount() {
		global $wgEchoCrossWikiNotifications;

		// Delete cached local counts and timestamps
		$localMemcKey = $this->getMemcKey( self::CACHE_KEY );
		$this->cache->delete( $localMemcKey );

		// Update the user touched timestamp for the local user
		$this->mUser->invalidateCache();

		if ( $wgEchoCrossWikiNotifications ) {
			// Delete cached global counts and timestamps
			$globalMemcKey = $this->getGlobalMemcKey( self::CACHE_KEY );
			if ( $globalMemcKey !== false ) {
				$this->cache->delete( $globalMemcKey );
			}

			$uw = EchoUnreadWikis::newFromUser( $this->mUser );
			if ( $uw ) {
				// Immediately compute new local counts and timestamps
				$newLocalData = $this->computeLocalCountsAndTimestamps( DB_MASTER );
				// Write the new values to the echo_unread_wikis table
				$alertTs = $newLocalData[EchoAttributeManager::ALERT]['timestamp'];
				$messageTs = $newLocalData[EchoAttributeManager::MESSAGE]['timestamp'];
				$uw->updateCount(
					wfWikiID(),
					$newLocalData[EchoAttributeManager::ALERT]['count'],
					$alertTs === -1 ? false : new MWTimestamp( $alertTs ),
					$newLocalData[EchoAttributeManager::MESSAGE]['count'],
					$messageTs === -1 ? false : new MWTimestamp( $messageTs )
				);
				// We could set() $newLocalData into the cache here, but we don't because that seems risky;
				// instead we let it be recomputed on demand
			}

			// Update the global touched timestamp
			$checkKey = $this->getGlobalMemcKey( self::CHECK_KEY );
			if ( $checkKey ) {
				$this->cache->touchCheckKey( $checkKey );
			}
		}
	}

	/**
	 * Get the timestamp of the last time the global notification counts/timestamps were updated, if available.
	 *
	 * If the timestamp of the last update is not known, this will return the current timestamp.
	 * If the user is not attached, this will return false.
	 *
	 * @return string|false MW timestamp of the last update, or false if the user is not attached
	 */
	public function getGlobalUpdateTime() {
		$key = $this->getGlobalMemcKey( self::CHECK_KEY );
		if ( $key === false ) {
			return false;
		}
		return wfTimestamp( TS_MW, $this->cache->getCheckKeyTime( $key ) );
	}

	/**
	 * Get the number of notifications in each section, and the timestamp of the latest notification in
	 * each section. This returns the raw data structure that is stored in the cache; unless you want
	 * all of this information, you're probably looking for getNotificationCount(),
	 * getLastUnreadNotificationTime() or one of its wrappers.
	 *
	 * The returned data structure looks like:
	 * [
	 *   'local' => [
	 *     'alert' => [ 'count' => N, 'timestamp' => TS ],
	 *     'message' => [ 'count' = N, 'timestamp' => TS ],
	 *     'all' => [ 'count' => N, 'timestamp' => TS ],
	 *   ],
	 *   'global' => [
	 *     'alert' => [ 'count' => N, 'timestamp' => TS ],
	 *     'message' => [ 'count' = N, 'timestamp' => TS ],
	 *     'all' => [ 'count' => N, 'timestamp' => TS ],
	 *   ],
	 * ]
	 * Where N is a number and TS is a timestamp in TS_MW format or -1. If $includeGlobal is false,
	 * the 'global' key will not be present.
	 *
	 * @param bool $includeGlobal Whether to include cross-wiki notifications as well
	 * @return array[]
	 */
	public function getCountsAndTimestamps( $includeGlobal = false ) {
		if ( $this->localCountsAndTimestamps === null ) {
			$this->localCountsAndTimestamps = $this->cache->getWithSetCallback(
				$this->getMemcKey( self::CACHE_KEY ),
				self::CACHE_TTL,
				function ( $oldValue, &$ttl, array &$setOpts ) {
					$dbr = $this->userNotifGateway->getDB( DB_REPLICA );
					$setOpts += Database::getCacheSetOptions( $dbr );
					return $this->computeLocalCountsAndTimestamps();
				}
			);
		}
		$result = [ 'local' => $this->localCountsAndTimestamps ];

		if ( $includeGlobal ) {
			if ( $this->globalCountsAndTimestamps === null ) {
				$memcKey = $this->getGlobalMemcKey( self::CACHE_KEY );
				// If getGlobalMemcKey returns false, we don't have a global user ID
				// In that case, don't compute data that we can't cache or store
				if ( $memcKey !== false ) {
					$this->globalCountsAndTimestamps = $this->cache->getWithSetCallback(
						$memcKey,
						self::CACHE_TTL,
						function ( $oldValue, &$ttl, array &$setOpts ) {
							$dbr = $this->userNotifGateway->getDB( DB_REPLICA );
							$setOpts += Database::getCacheSetOptions( $dbr );
							return $this->computeGlobalCountsAndTimestamps();
						}
					);
				}
			}
			$result['global'] = $this->globalCountsAndTimestamps;
		}
		return $result;
	}

	/**
	 * Compute the counts and timestamps for the local notifications in each section.
	 * @param int $dbSource DB_REPLICA or DB_MASTER
	 * @return array[] [ 'alert' => [ 'count' => N, 'timestamp' => TS ], ... ]
	 */
	protected function computeLocalCountsAndTimestamps( $dbSource = DB_REPLICA ) {
		$attributeManager = EchoAttributeManager::newFromGlobalVars();
		$result = [];
		$totals = [ 'count' => 0, 'timestamp' => -1 ];

		foreach ( EchoAttributeManager::$sections as $section ) {
			$eventTypesToLoad = $attributeManager->getUserEnabledEventsbySections(
				$this->mUser,
				'web',
				[ $section ]
			);

			$count = (int)$this->userNotifGateway->getCappedNotificationCount(
				$dbSource,
				$eventTypesToLoad,
				self::MAX_BADGE_COUNT + 1
			);
			$result[$section]['count'] = $count;
			$totals['count'] += $count;

			$notifications = $this->notifMapper->fetchUnreadByUser(
				$this->mUser,
				1,
				null,
				$eventTypesToLoad,
				null,
				$dbSource
			);
			if ( $notifications ) {
				$notification = reset( $notifications );
				$timestamp = $notification->getTimestamp();
			} else {
				$timestamp = -1;
			}
			$result[$section]['timestamp'] = $timestamp;
			$totals['timestamp'] = max( $totals['timestamp'], $timestamp );
		}
		$totals['count'] = self::capNotificationCount( $totals['count'] );
		$result[EchoAttributeManager::ALL] = $totals;
		return $result;
	}

	/**
	 * Compute the global counts and timestamps for each section.
	 *
	 * This calls getCountsAndTimestamps() to get data about local notifications, which may end up
	 * calling computeLocalCountsAndTimestamps() if there's a cache miss.
	 * @return array[] [ 'alert' => [ 'count' => N, 'timestamp' => TS ], ... ]
	 */
	protected function computeGlobalCountsAndTimestamps() {
		$localData = $this->getCountsAndTimestamps()['local'];
		$result = [];
		$totals = [ 'count' => 0, 'timestamp' => -1 ];
		foreach ( EchoAttributeManager::$sections as $section ) {
			$localCount = $localData[$section]['count'];
			$globalCount = self::capNotificationCount( $localCount + $this->getForeignCount( $section ) );
			$result[$section]['count'] = $globalCount;
			$totals['count'] += $globalCount;

			$localTimestamp = $localData[$section]['timestamp'];
			$foreignTimestamp = $this->getForeignTimestamp( $section );
			$globalTimestamp = max(
				$localTimestamp,
				$foreignTimestamp ? $foreignTimestamp->getTimestamp( TS_MW ) : -1
			);
			$result[$section]['timestamp'] = $globalTimestamp;
			$totals['timestamp'] = max( $totals['timestamp'], $globalTimestamp );
		}
		$totals['count'] = self::capNotificationCount( $totals['count'] );
		$result[EchoAttributeManager::ALL] = $totals;
		return $result;
	}

	/**
	 * Get the user's email notification format
	 * @return string
	 */
	public function getEmailFormat() {
		global $wgAllowHTMLEmail;

		if ( $wgAllowHTMLEmail ) {
			return $this->mUser->getOption( 'echo-email-format' );
		}

		return EchoEmailFormat::PLAIN_TEXT;
	}

	/**
	 * Build a cache key for local use (local to this wiki)
	 *
	 * @param string $key Key, typically prefixed with echo-notification-
	 * @return string Cache key
	 */
	protected function getMemcKey( $key ) {
		global $wgEchoCacheVersion;
		return $this->cache->makeKey( $key, $this->mUser->getId(), $wgEchoCacheVersion );
	}

	/**
	 * Build a cache key for global use
	 *
	 * @param string $key Key, typically prefixed with echo-notification-
	 * @return string|false Memcached key, or false if one could not be generated
	 */
	protected function getGlobalMemcKey( $key ) {
		global $wgEchoCacheVersion;
		$lookup = CentralIdLookup::factory();
		$globalId = $lookup->centralIdFromLocalUser( $this->mUser, CentralIdLookup::AUDIENCE_RAW );
		if ( !$globalId ) {
			return false;
		}
		return $this->cache->makeGlobalKey( $key, $globalId, $wgEchoCacheVersion );
	}

	/**
	 * Lazy-construct an EchoForeignNotifications instance. This instance is force-enabled, so it
	 * returns information about cross-wiki notifications even if the user has them disabled.
	 * @return EchoForeignNotifications
	 */
	protected function getForeignNotifications() {
		if ( !$this->foreignNotifications ) {
			$this->foreignNotifications = new EchoForeignNotifications( $this->mUser, true );
		}
		return $this->foreignNotifications;
	}

	/**
	 * Get the number of foreign notifications in a given section.
	 * @param string $section One of EchoAttributeManager::$sections
	 * @return int Number of foreign notifications
	 */
	protected function getForeignCount( $section = EchoAttributeManager::ALL ) {
		return self::capNotificationCount(
			$this->getForeignNotifications()->getCount( $section )
		);
	}

	/**
	 * Get the timestamp of the most recent foreign notification in a given section.
	 * @param string $section One of EchoAttributeManager::$sections
	 * @return MWTimestamp|false Timestamp of the most recent foreign notification, or false if
	 *  there aren't any
	 */
	protected function getForeignTimestamp( $section = EchoAttributeManager::ALL ) {
		return $this->getForeignNotifications()->getTimestamp( $section );
	}

	/**
	 * Helper function to produce the capped number of notifications
	 * based on the value of MWEchoNotifUser::MAX_BADGE_COUNT
	 *
	 * @param int $number Raw notification count to cap
	 * @return int Capped notification count
	 */
	public static function capNotificationCount( $number ) {
		return min( $number, self::MAX_BADGE_COUNT + 1 );
	}
}
