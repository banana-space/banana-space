<?php

/**
 * Cache class that maps article id to Title object
 */
class EchoTitleLocalCache extends EchoLocalCache {

	/**
	 * @var EchoTitleLocalCache
	 */
	private static $instance;

	/**
	 * @return EchoTitleLocalCache
	 */
	public static function create() {
		if ( !self::$instance ) {
			self::$instance = new EchoTitleLocalCache();
		}

		return self::$instance;
	}

	/**
	 * @inheritDoc
	 */
	protected function resolve( array $lookups ) {
		if ( $lookups ) {
			$titles = Title::newFromIDs( $lookups );
			foreach ( $titles as $title ) {
				yield $title->getArticleID() => $title;
			}
		}
	}

}
