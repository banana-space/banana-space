<?php

namespace Flow;

use Flow\Exception\DataModelException;
use Title;
use User;
use Wikimedia\Rdbms\IDatabase;

/**
 * Is there a core object for retrieving multiple watchlist items?
 */
class WatchedTopicItems {

	protected $user;
	protected $watchListDb;
	protected $overrides = [];

	public function __construct( User $user, IDatabase $watchListDb ) {
		$this->user = $user;
		$this->watchListDb = $watchListDb;
	}

	/**
	 * Helps prevent reading our own writes.  If we have explicitly
	 * watched this title in this request set it here instead of
	 * querying a replica and possibly not noticing due to replica lag.
	 * @param Title $title
	 */
	public function addOverrideWatched( Title $title ) {
		$this->overrides[$title->getNamespace()][$title->getDBkey()] = true;
	}

	/**
	 * @param string[] $titles Array of UUID strings
	 * @return array
	 * @throws \Flow\Exception\DataModelException
	 */
	public function getWatchStatus( array $titles ) {
		$titles = array_unique( $titles );
		$result = array_fill_keys( $titles, false );

		if ( !$this->user->getId() ) {
			return $result;
		}

		$queryTitles = [];
		foreach ( $titles as $id ) {
			$obj = Title::makeTitleSafe( NS_TOPIC, $id );
			if ( $obj ) {
				$key = $obj->getDBkey();
				if ( isset( $this->overrides[$obj->getNamespace()][$key] ) ) {
					$result[strtolower( $key )] = true;
				} else {
					$queryTitles[$key] = $obj->getDBkey();
				}
			}
		}

		if ( !$queryTitles ) {
			return $result;
		}

		$res = $this->watchListDb->select(
			[ 'watchlist' ],
			[ 'wl_title' ],
			[
				'wl_user' => $this->user->getId(),
				'wl_namespace' => NS_TOPIC,
				'wl_title' => $queryTitles
			],
			__METHOD__
		);
		if ( !$res ) {
			throw new DataModelException( 'query failure', 'process-data' );
		}
		foreach ( $res as $row ) {
			$result[strtolower( $row->wl_title )] = true;
		}
		return $result;
	}

	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @return IDatabase
	 */
	public function getWatchlistDb() {
		return $this->watchListDb;
	}
}
