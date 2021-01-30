<?php

namespace Flow\Data;

use Flow\Container;
use Flow\Exception\DataModelException;

/**
 * A little glue code to allow passing around and manipulating multiple
 * ObjectManagers more conveniently.
 */
class ManagerGroup {
	/**
	 * @var Container
	 */
	protected $container;

	/**
	 * @var string[] Map from FQCN or short name to key in container that holds
	 *  the relevant ObjectManager
	 */
	protected $classMap;

	/**
	 * @var bool[] List of container keys that have been used
	 */
	protected $used = [];

	/**
	 * @param Container $container
	 * @param string[] $classMap Map from ObjectManager alias to container key
	 *  holding that object manager.
	 */
	public function __construct( Container $container, array $classMap ) {
		$this->container = $container;
		$this->classMap = $classMap;
	}

	/**
	 * Runs ObjectManager::clear on all managers that have been accessed since
	 * the last clear.
	 */
	public function clear() {
		foreach ( array_keys( $this->used ) as $key ) {
			$this->container[$key]->clear();
		}
		$this->used = [];
	}

	/**
	 * Purge all cached data related to this object
	 *
	 * @param object $object
	 */
	public function cachePurge( $object ) {
		$this->getStorage( get_class( $object ) )->cachePurge( $object );
	}

	/**
	 * @param string $className
	 * @return ObjectManager
	 * @throws DataModelException
	 */
	public function getStorage( $className ) {
		if ( !isset( $this->classMap[$className] ) ) {
			throw new DataModelException( "Request for '$className' is not in classmap: " .
				implode( ', ', array_keys( $this->classMap ) ), 'process-data' );
		}
		$key = $this->classMap[$className];
		$this->used[$key] = true;

		return $this->container[$key];
	}

	/**
	 * @param object $object
	 * @param array $metadata
	 * @throws DataModelException
	 */
	public function put( $object, array $metadata ) {
		$this->getStorage( get_class( $object ) )->put( $object, $metadata );
	}

	/**
	 * @param string $method
	 * @param array $objects
	 * @param array $metadata
	 * @throws DataModelException
	 */
	protected function multiMethod( $method, array $objects, array $metadata ) {
		$itemsByClass = [];

		foreach ( $objects as $object ) {
			$itemsByClass[ get_class( $object ) ][] = $object;
		}

		foreach ( $itemsByClass as $class => $myObjects ) {
			$this->getStorage( $class )->$method( $myObjects, $metadata );
		}
	}

	/**
	 * @param array $objects
	 * @param array $metadata
	 */
	public function multiPut( array $objects, array $metadata = [] ) {
		$this->multiMethod( 'multiPut', $objects, $metadata );
	}

	/**
	 * @param array $objects
	 * @param array $metadata
	 */
	public function multiRemove( array $objects, array $metadata = [] ) {
		$this->multiMethod( 'multiRemove', $objects, $metadata );
	}

	/**
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 * @throws DataModelException
	 */
	protected function call( $method, $args ) {
		$className = array_shift( $args );

		return $this->getStorage( $className )->$method( ...$args );
	}

	public function get( ...$args ) {
		return $this->call( __FUNCTION__, $args );
	}

	public function getMulti( ...$args ) {
		return $this->call( __FUNCTION__, $args );
	}

	public function find( ...$args ) {
		return $this->call( __FUNCTION__, $args );
	}

	public function findMulti( ...$args ) {
		return $this->call( __FUNCTION__, $args );
	}

	public function found( ...$args ) {
		return $this->call( __FUNCTION__, $args );
	}

	public function foundMulti( ...$args ) {
		return $this->call( __FUNCTION__, $args );
	}
}
