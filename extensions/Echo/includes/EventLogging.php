<?php

/**
 * Static class for handling all kinds of event logging
 */
class MWEchoEventLogging {

	private static $revisionIds = [
		'Echo' => 7731316,
		'EchoMail' => 5467650,
		// Keep in sync with client-side revision
		// in extension.json
		'EchoInteraction' => 15823738
	];

	/**
	 * This is the only function that interacts with EventLogging
	 *
	 * Adds common fields, and logs if logging is enabled for the given $schema.
	 *
	 * @param string $schema
	 * @param array $data
	 */
	protected static function logEvent( $schema, array $data ) {
		global $wgEchoEventLoggingSchemas, $wgEchoEventLoggingVersion;

		$schemaConfig = $wgEchoEventLoggingSchemas[$schema];
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' )
			|| !$schemaConfig['enabled']
		) {
			// If logging for this schema is disabled, it's a no-op.
			return;
		}

		$revision = self::$revisionIds[$schema];
		$data['version'] = $wgEchoEventLoggingVersion;

		EventLogging::logEvent( $schema, $revision, $data );
	}

	/**
	 * Function for logging the event for Schema:Echo
	 * @param User $user User being notified.
	 * @param EchoEvent $event Event to log detail about.
	 * @param string $deliveryMethod 'web' or 'email'
	 */
	public static function logSchemaEcho( User $user, EchoEvent $event, $deliveryMethod ) {
		global $wgEchoNotifications;

		// Notifications under system category should have -1 as sender id
		if ( $event->getCategory() === 'system' ) {
			$sender = -1;
		} else {
			$agent = $event->getAgent();
			if ( $agent ) {
				$sender = $agent->isAnon() ? $agent->getName() : $agent->getId();
			} else {
				$sender = -1;
			}
		}

		if ( isset( $wgEchoNotifications[$event->getType()]['group'] ) ) {
			$group = $wgEchoNotifications[$event->getType()]['group'];
		} else {
			$group = 'neutral';
		}
		$data = [
			'eventId' => (int)$event->getId(),
			'notificationType' => $event->getType(),
			'notificationGroup' => $group,
			'sender' => (string)$sender,
			'recipientUserId' => $user->getId(),
			'recipientEditCount' => (int)$user->getEditCount()
		];
		// Add the source if it exists. (This is mostly for the Thanks extension.)
		$extra = $event->getExtra();
		if ( isset( $extra['source'] ) ) {
			$data['eventSource'] = (string)$extra['source'];
		}
		if ( $deliveryMethod === 'email' ) {
			$data['deliveryMethod'] = 'email';
		} else {
			// whitelist valid delivery methods so it is always valid
			$data['deliveryMethod'] = 'web';
		}
		// Add revision ID if it exists
		$rev = $event->getRevision();
		if ( $rev ) {
			$data['revisionId'] = $rev->getId();
		}

		self::logEvent( 'Echo', $data );
	}

	/**
	 * Function for logging the event for Schema:EchoEmail
	 * @param User $user
	 * @param string $emailDeliveryMode 'single' (default), 'daily_digest', or 'weekly_digest'
	 */
	public static function logSchemaEchoMail( User $user, $emailDeliveryMode = 'single' ) {
		$data = [
			'recipientUserId' => $user->getId(),
			'emailDeliveryMode' => $emailDeliveryMode
		];

		self::logEvent( 'EchoMail', $data );
	}

	/**
	 * @param User $user
	 * @param string $skinName
	 */
	public static function logSpecialPageVisit( User $user, $skinName ) {
		self::logEvent(
			'EchoInteraction',
			[
				'context' => 'archive',
				'action' => 'special-page-visit',
				'userId' => (int)$user->getId(),
				'editCount' => (int)$user->getEditCount(),
				'notifWiki' => wfWikiID(),
				// Hack: Figure out if we are in the mobile skin
				'mobile' => $skinName === 'minerva',
			]
		);
	}

}
