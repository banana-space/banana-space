<?php

/**
 * Manages what wikis a user has unread notifications on
 */
class EchoUnreadWikis {

	const DEFAULT_TS = '00000000000000';

	/**
	 * @var int
	 */
	private $id;

	/**
	 * @var MWEchoDbFactory
	 */
	private $dbFactory;

	/**
	 * @param int $id Central user id
	 */
	public function __construct( $id ) {
		$this->id = $id;
		$this->dbFactory = MWEchoDbFactory::newFromDefault();
	}

	/**
	 * Use the user id provided by the CentralIdLookup
	 *
	 * @param User $user
	 * @return EchoUnreadWikis|false
	 */
	public static function newFromUser( User $user ) {
		$lookup = CentralIdLookup::factory();
		$id = $lookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
		if ( !$id ) {
			return false;
		}

		return new self( $id );
	}

	/**
	 * @param int $index DB_* constant
	 * @return bool|\Wikimedia\Rdbms\IDatabase
	 */
	private function getDB( $index ) {
		return $this->dbFactory->getSharedDb( $index );
	}

	/**
	 * @return array[][]
	 */
	public function getUnreadCounts() {
		$dbr = $this->getDB( DB_REPLICA );
		if ( $dbr === false ) {
			return [];
		}

		$rows = $dbr->select(
			'echo_unread_wikis',
			[
				'euw_wiki',
				'euw_alerts', 'euw_alerts_ts',
				'euw_messages', 'euw_messages_ts',
			],
			[ 'euw_user' => $this->id ],
			__METHOD__
		);

		$wikis = [];
		foreach ( $rows as $row ) {
			if ( !$row->euw_alerts && !$row->euw_messages ) {
				// This shouldn't happen, but lets be safe...
				continue;
			}
			$wikis[$row->euw_wiki] = [
				EchoAttributeManager::ALERT => [
					'count' => $row->euw_alerts,
					'ts' => $row->euw_alerts_ts,
				],
				EchoAttributeManager::MESSAGE => [
					'count' => $row->euw_messages,
					'ts' => $row->euw_messages_ts,
				],
			];
		}

		return $wikis;
	}

	/**
	 * @param string $wiki Wiki code
	 * @param int $alertCount Number of alerts
	 * @param MWTimestamp|bool $alertTime Timestamp of most recent unread alert, or
	 *   false meaning no timestamp because there are no unread alerts.
	 * @param int $msgCount Number of messages
	 * @param MWTimestamp|bool $msgTime Timestamp of most recent message, or
	 *   false meaning no timestamp because there are no unread messages.
	 */
	public function updateCount( $wiki, $alertCount, $alertTime, $msgCount, $msgTime ) {
		$dbw = $this->getDB( DB_MASTER );
		if ( $dbw === false || $dbw->isReadOnly() ) {
			return;
		}

		$conditions = [
			'euw_user' => $this->id,
			'euw_wiki' => $wiki,
		];

		if ( $alertCount || $msgCount ) {
			$values = [
				'euw_alerts' => $alertCount,
				'euw_alerts_ts' => $alertTime
					? $alertTime->getTimestamp( TS_MW )
					: static::DEFAULT_TS,
				'euw_messages' => $msgCount,
				'euw_messages_ts' => $msgTime
					? $msgTime->getTimestamp( TS_MW )
					: static::DEFAULT_TS,
			];

			// when there is unread alert(s) and/or message(s), upsert the row
			$dbw->upsert(
				'echo_unread_wikis',
				$conditions + $values,
				[ [ 'euw_user', 'euw_wiki' ] ],
				$values,
				__METHOD__
			);
		} else {
			// No unread notifications, delete the row
			$dbw->delete(
				'echo_unread_wikis',
				$conditions,
				__METHOD__
			);
		}
	}
}
