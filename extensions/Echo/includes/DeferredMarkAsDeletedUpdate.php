<?php

use MediaWiki\Logger\LoggerFactory;

/**
 * Mark event notifications as deleted at the end of a request.  Used to queue up
 * individual events to mark due to formatting failures.
 */
class EchoDeferredMarkAsDeletedUpdate implements DeferrableUpdate {
	/**
	 * @var EchoEvent[]
	 */
	protected $events = [];

	/**
	 * @param EchoEvent $event
	 */
	public static function add( EchoEvent $event ) {
		static $update;
		if ( $update === null ) {
			$update = new self();
			DeferredUpdates::addUpdate( $update );
		}
		$update->addInternal( $event );
	}

	/**
	 * @param EchoEvent $event
	 */
	private function addInternal( EchoEvent $event ) {
		$this->events[] = $event;
	}

	private function filterEventsWithTitleDbLag() {
		return array_filter(
			$this->events,
			function ( EchoEvent $event ) {
				if ( !$event->getTitle() && $event->getTitle( true ) ) {
					// It is very likely this event was found
					// unreaderable because of replica lag.
					// Do not moderate it at this time.
					LoggerFactory::getInstance( 'Echo' )->debug(
						'EchoDeferredMarkAsDeletedUpdate: Event {eventId} was found unrenderable ' .
							' but its associated title exists on Master. Skipping.',
						[
							'eventId' => $event->getId(),
							'title' => $event->getTitle()->getPrefixedText(),
						]
					);
					return false;
				}
				return true;
			}
		);
	}

	/**
	 * Marks all queued notifications as read.
	 * Satisfies DeferrableUpdate interface
	 */
	public function doUpdate() {
		$events = $this->filterEventsWithTitleDbLag();

		$eventIds = array_map(
			function ( EchoEvent $event ) {
				return $event->getId();
			},
			$events
		);

		EchoModerationController::moderate( $eventIds, true );
		$this->events = [];
	}
}
