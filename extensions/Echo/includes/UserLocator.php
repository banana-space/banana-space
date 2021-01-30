<?php

use MediaWiki\MediaWikiServices;

class EchoUserLocator {
	/**
	 * Return all users watching the event title.
	 *
	 * The echo job queue must be enabled to prevent timeouts submitting to
	 * heavily watched pages when this is used.
	 *
	 * @param EchoEvent $event
	 * @param int $batchSize
	 * @return User[]|Iterator<User>
	 */
	public static function locateUsersWatchingTitle( EchoEvent $event, $batchSize = 500 ) {
		$title = $event->getTitle();
		if ( !$title ) {
			return [];
		}

		$it = new BatchRowIterator(
			wfGetDB( DB_REPLICA, 'watchlist' ),
			/* $table = */ 'watchlist',
			/* $primaryKeys = */ [ 'wl_user' ],
			$batchSize
		);
		$it->addConditions( [
			'wl_namespace' => $title->getNamespace(),
			'wl_title' => $title->getDBkey(),
		] );

		// flatten the result into a stream of rows
		$it = new RecursiveIteratorIterator( $it );

		// add callback to convert user id to user objects
		$it = new EchoCallbackIterator( $it, function ( $row ) {
			return User::newFromId( $row->wl_user );
		} );

		return $it;
	}

	/**
	 * If the event occurred on the talk page of a registered
	 * user return that user.
	 *
	 * @param EchoEvent $event
	 * @return User[]
	 */
	public static function locateTalkPageOwner( EchoEvent $event ) {
		$title = $event->getTitle();
		if ( !$title || $title->getNamespace() !== NS_USER_TALK ) {
			return [];
		}

		$user = User::newFromName( $title->getDBkey() );
		if ( $user && !$user->isAnon() ) {
			return [ $user->getId() => $user ];
		}

		return [];
	}

	/**
	 * Return the event agent
	 *
	 * @param EchoEvent $event
	 * @return User[]
	 */
	public static function locateEventAgent( EchoEvent $event ) {
		$agent = $event->getAgent();
		if ( $agent && !$agent->isAnon() ) {
			return [ $agent->getId() => $agent ];
		}

		return [];
	}

	/**
	 * Return the user that created the first revision of the
	 * associated title.
	 *
	 * @param EchoEvent $event
	 * @return User[]
	 */
	public static function locateArticleCreator( EchoEvent $event ) {
		$title = $event->getTitle();

		if ( !$title || $title->getArticleID() <= 0 ) {
			return [];
		}

		$dbr = wfGetDB( DB_REPLICA );
		$revQuery = MediaWikiServices::getInstance()->getRevisionStore()->getQueryInfo();
		$res = $dbr->selectRow(
			$revQuery['tables'],
			[ 'rev_user' => $revQuery['fields']['rev_user'] ],
			[ 'rev_page' => $title->getArticleID() ],
			__METHOD__,
			[ 'LIMIT' => 1, 'ORDER BY' => 'rev_timestamp, rev_id' ],
			$revQuery['joins']
		);
		if ( !$res || !$res->rev_user ) {
			return [];
		}

		$user = User::newFromId( $res->rev_user );
		if ( $user ) {
			return [ $user->getId() => $user ];
		}

		return [];
	}

	/**
	 * Fetch user ids from the event extra data.  Requires additional
	 * parameter.  Example $wgEchoNotifications parameter:
	 *
	 *   'user-locator' => array( array( 'event-extra', 'mentions' ) ),
	 *
	 * The above will look in the 'mentions' parameter for a user id or
	 * array of user ids.  It will return all these users as notification
	 * targets.
	 *
	 * @param EchoEvent $event
	 * @param string[] $keys one or more keys to check for user ids
	 * @return User[]
	 */
	public static function locateFromEventExtra( EchoEvent $event, array $keys ) {
		$users = [];
		foreach ( $keys as $key ) {
			$userIds = $event->getExtraParam( $key );
			if ( !$userIds ) {
				continue;
			}
			if ( !is_array( $userIds ) ) {
				$userIds = [ $userIds ];
			}
			foreach ( $userIds as $userId ) {
				// we shouldn't receive User instances, but allow
				// it for backward compatability
				if ( $userId instanceof User ) {
					if ( $userId->isAnon() ) {
						continue;
					}
					$user = $userId;
				} else {
					$user = User::newFromId( $userId );
				}
				$users[$user->getId()] = $user;
			}
		}

		return $users;
	}
}
