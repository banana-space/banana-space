<?php

use MediaWiki\MediaWikiServices;

/**
 * Cache class that maps revision id to RevisionStore object
 */
class EchoRevisionLocalCache extends EchoLocalCache {

	/**
	 * @var EchoRevisionLocalCache
	 */
	private static $instance;

	/**
	 * @return EchoRevisionLocalCache
	 */
	public static function create() {
		if ( !self::$instance ) {
			self::$instance = new EchoRevisionLocalCache();
		}

		return self::$instance;
	}

	/**
	 * @inheritDoc
	 */
	protected function resolve( array $lookups ) {
		$store = MediaWikiServices::getInstance()->getRevisionStore();
		$dbr = wfGetDB( DB_REPLICA );
		$revQuery = $store->getQueryInfo( [ 'page', 'user' ] );
		$res = $dbr->select(
			$revQuery['tables'],
			$revQuery['fields'],
			[ 'rev_id' => $lookups ],
			__METHOD__,
			[],
			$revQuery['joins']
		);
		foreach ( $res as $row ) {
			yield $row->rev_id => $store->newRevisionFromRow( $row );
		}
	}
}
