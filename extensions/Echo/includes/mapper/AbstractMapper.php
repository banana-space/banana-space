<?php

/**
 * Abstract mapper for model
 */
abstract class EchoAbstractMapper {

	/**
	 * Echo database factory
	 * @var MWEchoDbFactory
	 */
	protected $dbFactory;

	/**
	 * Event listeners for method like insert/delete
	 * @var array[]
	 */
	protected $listeners;

	/**
	 * @param MWEchoDbFactory|null $dbFactory
	 */
	public function __construct( MWEchoDbFactory $dbFactory = null ) {
		if ( $dbFactory === null ) {
			$dbFactory = MWEchoDbFactory::newFromDefault();
		}
		$this->dbFactory = $dbFactory;
	}

	/**
	 * Attach a listener
	 *
	 * @param string $method Method name
	 * @param string $key Identification of the callable
	 * @param callable $callable
	 * @throws MWException
	 */
	public function attachListener( $method, $key, $callable ) {
		if ( !method_exists( $this, $method ) ) {
			throw new MWException( $method . ' does not exist in ' . get_class( $this ) );
		}
		if ( !isset( $this->listeners[$method] ) ) {
			$this->listeners[$method] = [];
		}

		$this->listeners[$method][$key] = $callable;
	}

	/**
	 * Detach a listener
	 *
	 * @param string $method Method name
	 * @param string $key identification of the callable
	 */
	public function detachListener( $method, $key ) {
		if ( isset( $this->listeners[$method] ) ) {
			unset( $this->listeners[$method][$key] );
		}
	}

	/**
	 * Get the listener for a method
	 *
	 * @param string $method
	 * @return callable[]
	 * @throws MWException
	 */
	public function getMethodListeners( $method ) {
		if ( !method_exists( $this, $method ) ) {
			throw new MWException( $method . ' does not exist in ' . get_class( $this ) );
		}

		return $this->listeners[$method] ?? [];
	}

}
