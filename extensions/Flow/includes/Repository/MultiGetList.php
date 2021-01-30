<?php

namespace Flow\Repository;

use Flow\Data\FlowObjectCache;
use Flow\Exception\InvalidParameterException;
use Flow\Model\UUID;

class MultiGetList {

	/**
	 * @var FlowObjectCache
	 */
	protected $cache;

	/**
	 * @param FlowObjectCache $cache
	 */
	public function __construct( FlowObjectCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * @param string $treeType
	 * @param array $ids
	 * @param callable $loadCallback
	 * @return array
	 * @throws InvalidParameterException
	 */
	public function get( $treeType, array $ids, $loadCallback ) {
		$cacheKeys = [];
		foreach ( $ids as $id ) {
			if ( $id instanceof UUID ) {
				$cacheId = $id;
			} elseif ( is_scalar( $id ) ) {
				$cacheId = UUID::create( $id );
			} else {
				$type = is_object( $id ) ? get_class( $id ) : gettype( $id );
				throw new InvalidParameterException( "Not scalar: $type" );
			}
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			$cacheKeys[ TreeCacheKey::build( $treeType, $cacheId ) ] = $id;
		}
		return $this->getByKey( $cacheKeys, $loadCallback );
	}

	/**
	 * @param array $cacheKeys
	 * @param callable $loadCallback
	 * @return array
	 */
	public function getByKey( array $cacheKeys, $loadCallback ) {
		if ( !$cacheKeys ) {
			return [];
		}
		$result = [];
		$multiRes = $this->cache->getMulti( array_keys( $cacheKeys ) );
		// Memcached BagOStuff only returns found keys, but the redis bag
		// returns false for not found keys.
		$multiRes = array_filter(
			$multiRes,
			function ( $val ) {
				return $val !== false;
			}
		);
		foreach ( $multiRes as $key => $value ) {
			$idx = $cacheKeys[$key];
			if ( $idx instanceof UUID ) {
				$idx = $idx->getAlphadecimal();
			}
			$result[$idx] = $value;
			unset( $cacheKeys[$key] );
		}

		if ( count( $cacheKeys ) === 0 ) {
			return $result;
		}
		$res = $loadCallback( array_values( $cacheKeys ) );
		if ( !$res ) {
			// storage failure of some sort
			return $result;
		}
		$invCacheKeys = [];
		foreach ( $cacheKeys as $cacheKey => $id ) {
			if ( $id instanceof UUID ) {
				$id = $id->getAlphadecimal();
			}
			$invCacheKeys[$id] = $cacheKey;
		}
		foreach ( $res as $id => $row ) {
			$this->cache->set( $invCacheKeys[$id], $row );
			$result[$id] = $row;
		}

		return $result;
	}
}
