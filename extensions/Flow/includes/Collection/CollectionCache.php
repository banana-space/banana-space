<?php

namespace Flow\Collection;

use Flow\Data\Listener\AbstractListener;
use Flow\Model\AbstractRevision;
use MapCacheLRU;

/**
 * Cache any useful collection data. Listens to lifecycle events for
 * insert/update/remove to keep the internal cache up to date and reduce
 * requests deeper into the stack.
 */
class CollectionCache extends AbstractListener {

	/**
	 * Max to cache collection's last revision
	 */
	private const LAST_REV_CACHE_MAX = 50;

	/**
	 * The last revision for a collection
	 *
	 * @var MapCacheLRU
	 */
	protected $lastRevCache;

	/**
	 * Initialize any cache holder in here
	 */
	public function __construct() {
		$this->lastRevCache = new MapCacheLRU( self::LAST_REV_CACHE_MAX );
	}

	/**
	 * Get the last revision of a collection that the requested revision belongs to
	 * @param AbstractRevision $revision current revision
	 * @return AbstractRevision the last revision
	 */
	public function getLastRevisionFor( AbstractRevision $revision ) {
		$key = $this->getLastRevCacheKey( $revision );
		$lastRevision = $this->lastRevCache->get( $key );
		if ( $lastRevision === null ) {
			$lastRevision = $revision->getCollection()->getLastRevision();
			$this->lastRevCache->set( $key, $lastRevision );
		}

		return $lastRevision;
	}

	/**
	 * Cache key for last revision
	 *
	 * @param AbstractRevision $revision
	 * @return string
	 */
	protected function getLastRevCacheKey( AbstractRevision $revision ) {
		return $revision->getCollectionId()->getAlphadecimal() . '-' . $revision->getRevisionType() . '-last-rev';
	}

	public function onAfterClear() {
		$this->lastRevCache = new MapCacheLRU( self::LAST_REV_CACHE_MAX );
	}

	public function onAfterInsert( $object, array $new, array $metadata ) {
		if ( $object instanceof AbstractRevision ) {
			$this->lastRevCache->clear( $this->getLastRevCacheKey( $object ) );
		}
	}

	public function onAfterUpdate( $object, array $old, array $new, array $metadata ) {
		if ( $object instanceof AbstractRevision ) {
			$this->lastRevCache->clear( $this->getLastRevCacheKey( $object ) );
		}
	}

	public function onAfterRemove( $object, array $old, array $metadata ) {
		if ( $object instanceof AbstractRevision ) {
			$this->lastRevCache->clear( $this->getLastRevCacheKey( $object ) );
		}
	}
}
