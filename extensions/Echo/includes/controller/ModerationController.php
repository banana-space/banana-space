<?php

use MediaWiki\MediaWikiServices;

/**
 * This class represents the controller for moderating notifications
 */
class EchoModerationController {

	/**
	 * Moderate or unmoderate events
	 *
	 * @param int[] $eventIds
	 * @param bool $moderate Whether to moderate or unmoderate the events
	 * @throws MWException
	 */
	public static function moderate( array $eventIds, $moderate ) {
		if ( !$eventIds ) {
			return;
		}

		$eventMapper = new EchoEventMapper();
		$notificationMapper = new EchoNotificationMapper();

		$affectedUserIds = $notificationMapper->fetchUsersWithNotificationsForEvents( $eventIds );
		$eventMapper->toggleDeleted( $eventIds, $moderate );

		$fname = __METHOD__;

		DeferredUpdates::addCallableUpdate( function () use ( $affectedUserIds, $fname ) {
			// This update runs after the main transaction round commits.
			// Wait for the event deletions to be propagated to replica DBs
			$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
			$lbFactory->waitForReplication( [ 'timeout' => 5 ] );
			$lbFactory->flushReplicaSnapshots( $fname );
			// Recompute the notification count for the
			// users whose notifications have been moderated.
			foreach ( $affectedUserIds as $userId ) {
				$user = User::newFromId( $userId );
				MWEchoNotifUser::newFromUser( $user )->resetNotificationCount();
			}
		} );
	}
}
