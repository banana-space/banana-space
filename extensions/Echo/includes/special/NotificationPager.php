<?php

/**
 * This pager is used by Special:Notifications (NO-JS).
 * The heavy-lifting is done by IndexPager (grand-parent to this class).
 * It paginates on notification_event for a specific user, only for the enabled event types.
 */
class NotificationPager extends ReverseChronologicalPager {
	/**
	 * @param IContextSource $context
	 */
	public function __construct( IContextSource $context ) {
		$dbFactory = MWEchoDbFactory::newFromDefault();
		$this->mDb = $dbFactory->getEchoDb( DB_REPLICA );

		parent::__construct( $context );
	}

	public function formatRow( $row ) {
		throw new Exception( "This pager does not support row formatting. " .
			"Use 'getNotifications()' to get a list of EchoNotification objects." );
	}

	public function getQueryInfo() {
		$attributeManager = EchoAttributeManager::newFromGlobalVars();
		$eventTypes = $attributeManager->getUserEnabledEvents( $this->getUser(), 'web' );

		return [
			'tables' => [ 'echo_notification', 'echo_event' ],
			'fields' => EchoNotification::selectFields(),
			'conds' => [
				'notification_user' => $this->getUser()->getId(),
				'event_type' => $eventTypes,
				'event_deleted' => 0,
			],
			'options' => [],
			'join_conds' =>
				[ 'echo_event' =>
					[
						'JOIN',
						'notification_event=event_id',
					],
				],
		];
	}

	public function getNotifications() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}

		$notifications = [];
		foreach ( $this->mResult as $row ) {
			$notifications[] = EchoNotification::newFromRow( $row );
		}

		// get rid of the overfetched
		if ( count( $notifications ) > $this->getLimit() ) {
			array_pop( $notifications );
		}

		if ( $this->mIsBackwards ) {
			$notifications = array_reverse( $notifications );
		}

		return $notifications;
	}

	public function getIndexField() {
		return 'notification_event';
	}
}
