<?php

use MediaWiki\MediaWikiServices;

class EchoNotification extends EchoAbstractEntity implements Bundleable {

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var EchoEvent
	 */
	protected $event;

	/**
	 * The target page object for the notification if there is one. Null means
	 * the information has not been loaded.
	 *
	 * @var EchoTargetPage[]|null
	 */
	protected $targetPages;

	/**
	 * @var string
	 */
	protected $timestamp;

	/**
	 * @var string
	 */
	protected $readTimestamp;

	/**
	 * The hash used to determine if a set of event could be bundled
	 * @var string
	 */
	protected $bundleHash = '';

	/**
	 * @var EchoNotification[]
	 */
	protected $bundledNotifications;

	/**
	 * Do not use this constructor.
	 */
	protected function __construct() {
	}

	/**
	 * Creates an EchoNotification object based on event and user
	 * @param array $info The following keys are required:
	 * - 'event' The EchoEvent being notified about.
	 * - 'user' The User being notified.
	 * @throws MWException
	 * @return EchoNotification
	 */
	public static function create( array $info ) {
		$obj = new EchoNotification();
		static $validFields = [ 'event', 'user' ];

		foreach ( $validFields as $field ) {
			if ( isset( $info[$field] ) ) {
				$obj->$field = $info[$field];
			} else {
				throw new MWException( "Field $field is required" );
			}
		}

		if ( !$obj->user instanceof User ) {
			throw new InvalidArgumentException( 'Invalid user parameter, expected: User object' );
		}

		if ( !$obj->event instanceof EchoEvent ) {
			throw new InvalidArgumentException( 'Invalid event parameter, expected: EchoEvent object' );
		}

		// Notification timestamp should be the same as event timestamp
		$obj->timestamp = $obj->event->getTimestamp();
		// Safe fallback
		if ( !$obj->timestamp ) {
			$obj->timestamp = wfTimestampNow();
		}

		// @Todo - Database insert logic should not be inside the model
		$obj->insert();

		return $obj;
	}

	/**
	 * Adds this new notification object to the backend storage.
	 */
	protected function insert() {
		global $wgEchoNotifications;

		$notifMapper = new EchoNotificationMapper();

		// Get the bundle key for this event if web bundling is enabled
		$bundleKey = '';
		if ( !empty( $wgEchoNotifications[$this->event->getType()]['bundle']['web'] ) ) {
			Hooks::run( 'EchoGetBundleRules', [ $this->event, &$bundleKey ] );
		}

		// @phan-suppress-next-line PhanImpossibleCondition May be set by hook
		if ( $bundleKey ) {
			$hash = md5( $bundleKey );
			$this->bundleHash = $hash;
		}

		$notifUser = MWEchoNotifUser::newFromUser( $this->user );

		// Add listener to refresh notification count upon insert
		$notifMapper->attachListener( 'insert', 'refresh-notif-count',
			function () use ( $notifUser ) {
				$notifUser->resetNotificationCount();
			}
		);

		$notifMapper->insert( $this );

		if ( $this->event->getCategory() === 'edit-user-talk' ) {
			MediaWikiServices::getInstance()
				->getTalkPageNotificationManager()
				->setUserHasNewMessages( $this->user );
		}
		Hooks::run( 'EchoCreateNotificationComplete', [ $this ] );
	}

	/**
	 * Load a notification record from std class
	 * @param stdClass $row
	 * @param EchoTargetPage[]|null $targetPages An array of EchoTargetPage instances, or null if not loaded.
	 * @return EchoNotification|false False if failed to load/unserialize
	 */
	public static function newFromRow( $row, array $targetPages = null ) {
		$notification = new EchoNotification();

		if ( property_exists( $row, 'event_type' ) ) {
			$notification->event = EchoEvent::newFromRow( $row );
		} else {
			$notification->event = EchoEvent::newFromID( $row->notification_event );
		}

		if ( $notification->event === false ) {
			return false;
		}

		$notification->targetPages = $targetPages;
		$notification->user = User::newFromId( $row->notification_user );
		// Notification timestamp should never be empty
		$notification->timestamp = wfTimestamp( TS_MW, $row->notification_timestamp );
		// Only convert to MW format if it is not empty, otherwise
		// wfTimestamp would use current timestamp for empty cases
		if ( $row->notification_read_timestamp ) {
			$notification->readTimestamp = wfTimestamp( TS_MW, $row->notification_read_timestamp );
		}
		$notification->bundleHash = $row->notification_bundle_hash;

		return $notification;
	}

	/**
	 * Convert object property to database row array
	 * @return array
	 */
	public function toDbArray() {
		return [
			'notification_event' => $this->event->getId(),
			'notification_user' => $this->user->getId(),
			'notification_timestamp' => $this->timestamp,
			'notification_read_timestamp' => $this->readTimestamp,
			'notification_bundle_hash' => $this->bundleHash,
		];
	}

	/**
	 * Getter method
	 * @return EchoEvent The event for this notification
	 */
	public function getEvent() {
		return $this->event;
	}

	/**
	 * Getter method
	 * @return User The recipient of this notification
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Getter method
	 * @return string Notification creation timestamp
	 */
	public function getTimestamp() {
		return $this->timestamp;
	}

	/**
	 * Getter method
	 * @return string|null Notification read timestamp
	 */
	public function getReadTimestamp() {
		return $this->readTimestamp;
	}

	public function isRead() {
		return $this->getReadTimestamp() !== null;
	}

	/**
	 * Getter method
	 * @return string|null Notification bundle hash
	 */
	public function getBundleHash() {
		return $this->bundleHash;
	}

	/**
	 * Getter method.  Returns an array of EchoTargetPages, or null if they have
	 * not been loaded.
	 *
	 * @return EchoTargetPage[]|null
	 */
	public function getTargetPages() {
		return $this->targetPages;
	}

	public function setBundledNotifications( array $notifications ) {
		$this->bundledNotifications = $notifications;
	}

	public function getBundledNotifications() {
		return $this->bundledNotifications;
	}

	/**
	 * @inheritDoc
	 */
	public function canBeBundled() {
		return !$this->isRead();
	}

	/**
	 * @inheritDoc
	 */
	public function getBundlingKey() {
		return $this->getBundleHash();
	}

	/**
	 * @inheritDoc
	 */
	public function setBundledElements( array $bundleables ) {
		$this->setBundledNotifications( $bundleables );
	}

	/**
	 * @inheritDoc
	 */
	public function getSortingKey() {
		return ( $this->isRead() ? '0' : '1' ) . '_' . $this->getTimestamp();
	}

	/**
	 * Return the list of fields that should be selected to create
	 * a new event with EchoNotification::newFromRow
	 * @return string[]
	 */
	public static function selectFields() {
		return array_merge( EchoEvent::selectFields(), [
			'notification_event',
			'notification_user',
			'notification_timestamp',
			'notification_read_timestamp',
			'notification_bundle_hash',
		] );
	}
}
