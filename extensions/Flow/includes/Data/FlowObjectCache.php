<?php

namespace Flow\Data;

use Flow\DbFactory;
use WANObjectCache;
use Wikimedia\Rdbms\Database;

class FlowObjectCache {
	/**
	 * @var WANObjectCache
	 */
	protected $cache;

	/**
	 * @var int
	 */
	protected $ttl = 0;

	/**
	 * @var array
	 */
	protected $setOptions;

	/**
	 * @param WANObjectCache $cache The cache implementation to back this buffer with
	 * @param DbFactory $dbFactory
	 * @param int $ttl The default length of time to cache data. 0 for LRU.
	 */
	public function __construct( WANObjectCache $cache, DbFactory $dbFactory, $ttl = 0 ) {
		$this->ttl = $ttl;
		$this->cache = $cache;
		$this->setOptions = Database::getCacheSetOptions( $dbFactory->getDB( DB_REPLICA ) );
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function get( $key ) {
		return $this->cache->get( $key );
	}

	/**
	 * @param array $keys
	 * @return array
	 */
	public function getMulti( array $keys ) {
		return $this->cache->getMulti( $keys );
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return bool
	 */
	public function set( $key, $value ) {
		return $this->cache->set( $key, $value, $this->ttl, $this->setOptions );
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function delete( $key ) {
		return $this->cache->delete( $key );
	}

	/**
	 * @param string $class
	 * @param string|int ...$components
	 * @return string
	 */
	public function makeGlobalKey( $class, ...$components ) {
		return $this->cache->makeGlobalKey( $class, ...$components );
	}
}
