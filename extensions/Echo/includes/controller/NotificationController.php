<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionStore;

/**
 * This class represents the controller for notifications
 */
class EchoNotificationController {

	/**
	 * Echo maximum number of users to cache
	 *
	 * @var int $maxRecipientCacheSize
	 */
	protected static $maxRecipientCacheSize = 200;

	/**
	 * Max number of users for which we in-process cache titles.
	 *
	 * @var int
	 */
	protected static $maxUsersTitleCacheSize = 200;

	/**
	 * Echo event agent per user blacklist
	 *
	 * @var MapCacheLRU
	 */
	protected static $blacklistByUser;

	/**
	 * Echo event agent per page linked event title mute list.
	 *
	 * @var MapCacheLRU
	 */
	protected static $mutedPageLinkedTitlesCache;

	/**
	 * Echo event agent per wiki blacklist
	 *
	 * @var EchoContainmentList|null
	 */
	protected static $wikiBlacklist;

	/**
	 * Echo event agent per user whitelist, this overwrites $blacklistByUser
	 *
	 * @var MapCacheLRU
	 */
	protected static $whitelistByUser;

	/**
	 * Returns the count passed in, or MWEchoNotifUser::MAX_BADGE_COUNT + 1,
	 * whichever is less.
	 *
	 * @param int $count
	 * @return int Notification count, with ceiling applied
	 */
	public static function getCappedNotificationCount( $count ) {
		if ( $count <= MWEchoNotifUser::MAX_BADGE_COUNT ) {
			return $count;
		} else {
			return MWEchoNotifUser::MAX_BADGE_COUNT + 1;
		}
	}

	/**
	 * Format the notification count as a string.  This should only be used for an
	 * isolated string count, e.g. as displayed in personal tools or returned by the API.
	 *
	 * If using it in sentence context, pass the value from getCappedNotificationCount
	 * into a message and use PLURAL.  Example: notification-bundle-header-page-linked
	 *
	 * @param int $count Notification count
	 * @return string Formatted count, after applying cap then formatting to string
	 */
	public static function formatNotificationCount( $count ) {
		$cappedCount = self::getCappedNotificationCount( $count );

		return wfMessage( 'echo-badge-count' )->numParams( $cappedCount )->text();
	}

	/**
	 * Processes notifications for a newly-created EchoEvent
	 *
	 * @param EchoEvent $event
	 * @param bool $defer Defer to job queue or not
	 */
	public static function notify( $event, $defer = true ) {
		// Defer to job queue if defer to job queue is requested and
		// this event should use job queue
		if ( $defer && $event->getUseJobQueue() ) {
			// defer job insertion till end of request when all primary db transactions
			// have been committed
			DeferredUpdates::addCallableUpdate( function () use ( $event ) {
				self::enqueueEvent( $event );
			} );

			return;
		}

		// Check if the event object has valid event type.  Events with invalid
		// event types left in the job queue should not be processed
		if ( !$event->isEnabledEvent() ) {
			return;
		}

		$type = $event->getType();
		$notifyTypes = self::getEventNotifyTypes( $type );
		$userIds = [];
		$userIdsCount = 0;
		foreach ( self::getUsersToNotifyForEvent( $event ) as $user ) {
			$userIds[$user->getId()] = $user->getId();
			$userNotifyTypes = $notifyTypes;
			// Respect the enotifminoredits preference
			// @todo should this be checked somewhere else?
			if (
				!$user->getOption( 'enotifminoredits' ) &&
				self::hasMinorRevision( $event )
			) {
				$notifyTypes = array_diff( $notifyTypes, [ 'email' ] );
			}
			Hooks::run( 'EchoGetNotificationTypes', [ $user, $event, &$userNotifyTypes ] );

			// types such as web, email, etc
			foreach ( $userNotifyTypes as $type ) {
				self::doNotification( $event, $user, $type );
			}

			$userIdsCount++;
			// Process 1000 users per NotificationDeleteJob
			if ( $userIdsCount > 1000 ) {
				self::enqueueDeleteJob( $userIds, $event );
				$userIds = [];
				$userIdsCount = 0;
			}
		}

		// process the userIds left in the array
		if ( $userIds ) {
			self::enqueueDeleteJob( $userIds, $event );
		}
	}

	/**
	 * Check if an event is associated with a minor revision.
	 *
	 * @param EchoEvent $event
	 * @return bool
	 */
	private static function hasMinorRevision( EchoEvent $event ) {
		$revId = $event->getExtraParam( 'revid' );
		if ( !$revId ) {
			return false;
		}

		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$rev = $revisionStore->getRevisionById( $revId, RevisionStore::READ_LATEST );
		if ( !$rev ) {
			$logger = LoggerFactory::getInstance( 'Echo' );
			$logger->debug(
				'Notifying for event {eventId}. Revision \'{revId}\' not found.',
				[
					'eventId' => $event->getId(),
					'revId' => $revId,
				]
			);
			return false;
		}

		return $rev->isMinor();
	}

	/**
	 * Schedule a job to check and delete older notifications
	 *
	 * @param int[] $userIds
	 * @param EchoEvent $event
	 */
	public static function enqueueDeleteJob( array $userIds, EchoEvent $event ) {
		// Do nothing if there is no user
		if ( !$userIds ) {
			return;
		}

		$job = new EchoNotificationDeleteJob(
			$event->getTitle() ?: Title::newMainPage(),
			[
				'userIds' => $userIds
			]
		);
		JobQueueGroup::singleton()->push( $job );
	}

	/**
	 * Get the notify types for this event, eg, web/email
	 *
	 * @param string $eventType Event type
	 * @return string[] List of notify types that apply for
	 *  this event type
	 */
	public static function getEventNotifyTypes( $eventType ) {
		$attributeManager = EchoAttributeManager::newFromGlobalVars();

		$category = $attributeManager->getNotificationCategory( $eventType );

		return array_keys( array_filter(
			$attributeManager->getNotifyTypeAvailabilityForCategory( $category )
		) );
	}

	/**
	 * Push $event onto the mediawiki job queue
	 *
	 * @param EchoEvent $event
	 */
	public static function enqueueEvent( EchoEvent $event ) {
		$job = new EchoNotificationJob(
			$event->getTitle() ?: Title::newMainPage(),
			[
				'eventId' => $event->getId(),
			]
		);
		JobQueueGroup::singleton()->push( $job );
	}

	/**
	 * Implements blacklist per active wiki expected to be initialized
	 * from InitializeSettings.php
	 *
	 * @param EchoEvent $event The event to test for exclusion
	 * @param User $user recipient of the notification for per-user blacklists
	 * @return bool True when the event agent is blacklisted
	 */
	public static function isBlacklistedByUser( EchoEvent $event, User $user ) {
		global $wgEchoAgentBlacklist, $wgEchoPerUserBlacklist;

		if ( !$event->getAgent() ) {
			return false;
		}

		// Ensure we have a list of blacklists
		if ( self::$blacklistByUser === null ) {
			self::$blacklistByUser = new MapCacheLRU( self::$maxRecipientCacheSize );
		}

		// Ensure we have a blacklist for the user
		if ( !self::$blacklistByUser->has( (string)$user->getId() ) ) {
			$blacklist = new EchoContainmentSet( $user );

			// Add the config setting
			$blacklist->addArray( $wgEchoAgentBlacklist );

			// Add wiki-wide blacklist
			$wikiBlacklist = self::getWikiBlacklist();
			if ( $wikiBlacklist !== null ) {
				$blacklist->add( $wikiBlacklist );
			}

			// Add to blacklist from user preference
			if ( $wgEchoPerUserBlacklist ) {
				$blacklist->addFromUserOption( 'echo-notifications-blacklist' );
			}

			// Add user's blacklist to dictionary if user wasn't already there
			self::$blacklistByUser->set( (string)$user->getId(), $blacklist );
		} else {
			// Just get the user's blacklist if it's already there
			$blacklist = self::$blacklistByUser->get( (string)$user->getId() );
		}
		return $blacklist->contains( $event->getAgent()->getName() ) ||
			( $wgEchoPerUserBlacklist &&
				$event->getType() === 'page-linked' &&
				self::isPageLinkedTitleMutedByUser( $event->getTitle(), $user ) );
	}

	/**
	 * Check if a title is in the user's page-linked event blacklist.
	 *
	 * @param Title $title
	 * @param User $user
	 * @return bool
	 */
	public static function isPageLinkedTitleMutedByUser( Title $title, User $user ) {
		if ( self::$mutedPageLinkedTitlesCache === null ) {
			self::$mutedPageLinkedTitlesCache = new MapCacheLRU( self::$maxUsersTitleCacheSize );
		}
		if ( !self::$mutedPageLinkedTitlesCache->has( (string)$user->getId() ) ) {
			$pageLinkedTitleMutedList = new EchoContainmentSet( $user );
			$pageLinkedTitleMutedList->addTitleIDsFromUserOption(
				'echo-notifications-page-linked-title-muted-list'
			);
			self::$mutedPageLinkedTitlesCache->set( (string)$user->getId(), $pageLinkedTitleMutedList );
		} else {
			$pageLinkedTitleMutedList = self::$mutedPageLinkedTitlesCache->get( (string)$user->getId() );
		}
		return $pageLinkedTitleMutedList->contains( (string)$title->getArticleID() );
	}

	/**
	 * @return EchoContainmentList|null
	 */
	protected static function getWikiBlacklist() {
		global $wgEchoOnWikiBlacklist;
		if ( !$wgEchoOnWikiBlacklist ) {
			return null;
		}
		if ( self::$wikiBlacklist === null ) {
			$clusterCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			self::$wikiBlacklist = new EchoCachedList(
				$clusterCache,
				$clusterCache->makeKey( "echo_on_wiki_blacklist" ),
				new EchoOnWikiList( NS_MEDIAWIKI, $wgEchoOnWikiBlacklist )
			);
		}

		return self::$wikiBlacklist;
	}

	/**
	 * Implements per-user whitelist sourced from a user wiki page
	 *
	 * @param EchoEvent $event The event to test for inclusion in whitelist
	 * @param User $user The user that owns the whitelist
	 * @return bool True when the event agent is in the user whitelist
	 */
	public static function isWhitelistedByUser( EchoEvent $event, User $user ) {
		$clusterCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		global $wgEchoPerUserWhitelistFormat;

		if ( $wgEchoPerUserWhitelistFormat === null || !$event->getAgent() ) {
			return false;
		}

		$userId = $user->getId();
		if ( $userId === 0 ) {
			return false; // anonymous user
		}

		// Ensure we have a list of whitelists
		if ( self::$whitelistByUser === null ) {
			self::$whitelistByUser = new MapCacheLRU( self::$maxRecipientCacheSize );
		}

		// Ensure we have a whitelist for the user
		if ( !self::$whitelistByUser->has( (string)$userId ) ) {
			$whitelist = new EchoContainmentSet( $user );
			self::$whitelistByUser->set( (string)$userId, $whitelist );
			$whitelist->addOnWiki(
				NS_USER,
				sprintf( $wgEchoPerUserWhitelistFormat, $user->getName() ),
				$clusterCache,
				$clusterCache->makeKey( "echo_on_wiki_whitelist_" . $userId )
			);
		} else {
			// Just get the user's whitelist
			$whitelist = self::$whitelistByUser->get( (string)$userId );
		}
		return $whitelist->contains( $event->getAgent()->getName() );
	}

	/**
	 * Processes a single notification for an EchoEvent
	 *
	 * @param EchoEvent $event
	 * @param User $user The user to be notified.
	 * @param string $type The type of notification delivery to process, e.g. 'email'.
	 * @throws MWException
	 */
	public static function doNotification( $event, $user, $type ) {
		global $wgEchoNotifiers;

		if ( !isset( $wgEchoNotifiers[$type] ) ) {
			throw new MWException( "Invalid notification type $type" );
		}

		// Don't send any notifications to anonymous users
		if ( $user->isAnon() ) {
			throw new MWException( "Cannot notify anonymous user: {$user->getName()}" );
		}

		( $wgEchoNotifiers[$type] )( $user, $event );
	}

	/**
	 * Returns an array each element of which is the result of a
	 * user-locator|user-filters attached to the event type.
	 *
	 * @param EchoEvent $event
	 * @param string $locator Either EchoAttributeManager::ATTR_LOCATORS or EchoAttributeManager::ATTR_FILTERS
	 * @return array
	 */
	public static function evaluateUserCallable( EchoEvent $event, $locator = EchoAttributeManager::ATTR_LOCATORS ) {
		$attributeManager = EchoAttributeManager::newFromGlobalVars();
		$type = $event->getType();
		$result = [];
		foreach ( $attributeManager->getUserCallable( $type, $locator ) as $callable ) {
			// locator options can be set per-event by using an array with
			// name as first parameter.
			if ( is_array( $callable ) ) {
				$options = $callable;
				$spliced = array_splice( $options, 0, 1, [ $event ] );
				$callable = reset( $spliced );
			} else {
				$options = [ $event ];
			}
			if ( is_callable( $callable ) ) {
				$result[] = $callable( ...$options );
			} else {
				wfDebugLog( __CLASS__, __FUNCTION__ . ": Invalid $locator returned for $type" );
			}
		}

		return $result;
	}

	/**
	 * Retrieves an array of User objects to be notified for an EchoEvent.
	 *
	 * @param EchoEvent $event
	 * @return Iterator values are User objects
	 */
	public static function getUsersToNotifyForEvent( EchoEvent $event ) {
		$notify = new EchoFilteredSequentialIterator;
		foreach ( self::evaluateUserCallable( $event, EchoAttributeManager::ATTR_LOCATORS ) as $users ) {
			$notify->add( $users );
		}

		// Hook for injecting more users.
		// @deprecated
		$users = [];
		Hooks::run( 'EchoGetDefaultNotifiedUsers', [ $event, &$users ] );
		// @phan-suppress-next-line PhanImpossibleCondition May be set by hook
		if ( $users ) {
			$notify->add( $users );
		}

		// Exclude certain users
		foreach ( self::evaluateUserCallable( $event, EchoAttributeManager::ATTR_FILTERS ) as $users ) {
			// the result of the callback can be both an iterator or array
			$users = is_array( $users ) ? $users : iterator_to_array( $users );
			$notify->addFilter( function ( User $user ) use ( $users ) {
				// we need to check if $user is in $users, but they're not
				// guaranteed to be the same object, so I'll compare ids.
				$userId = $user->getId();
				$userIds = array_map( function ( User $user ) {
					return $user->getId();
				}, $users );
				return !in_array( $userId, $userIds );
			} );
		}

		// Filter non-User, anon and duplicate users
		$seen = [];
		$fname = __METHOD__;
		$notify->addFilter( function ( $user ) use ( &$seen, $fname ) {
			if ( !$user instanceof User ) {
				wfDebugLog( $fname, 'Expected all User instances, received:' .
					( is_object( $user ) ? get_class( $user ) : gettype( $user ) )
				);

				return false;
			}
			if ( $user->isAnon() || isset( $seen[$user->getId()] ) ) {
				return false;
			}
			$seen[$user->getId()] = true;

			return true;
		} );

		// Don't notify the person who initiated the event unless the event allows it
		if ( !$event->canNotifyAgent() && $event->getAgent() ) {
			$agentId = $event->getAgent()->getId();
			$notify->addFilter( function ( $user ) use ( $agentId ) {
				return $user->getId() != $agentId;
			} );
		}

		// Apply blacklists and whitelists.
		$notify->addFilter( function ( $user ) use ( $event ) {
			$title = $event->getTitle();

			if ( self::isBlacklistedByUser( $event, $user ) &&
				(
					$title === null ||
					!(
						// Still notify for posts anywhere in
						// user's talk space
						$title->getRootText() === $user->getName() &&
						$title->getNamespace() === NS_USER_TALK
					)
				)
			) {
				return self::isWhitelistedByUser( $event, $user );
			}

			return true;
		} );

		return $notify->getIterator();
	}

	/**
	 * INTERNAL.  Must be public to be callable by the php error handling methods.
	 *
	 * Converts E_RECOVERABLE_ERROR, such as passing null to a method expecting
	 * a non-null object, into exceptions.
	 * @param int $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int $errline
	 * @return bool
	 * @throws EchoCatchableFatalErrorException
	 */
	public static function formatterErrorHandler( $errno, $errstr, $errfile, $errline ) {
		if ( $errno !== E_RECOVERABLE_ERROR ) {
			return false;
		}

		throw new EchoCatchableFatalErrorException( $errno, $errstr, $errfile, $errline );
	}
}
