<?php

namespace MediaWiki\Extension\OATHAuth;

use Config;
use ExtensionRegistry;
use Wikimedia\Rdbms\LBFactory;

/**
 * This class serves as a utility class for this extension
 *
 * @package MediaWiki\Extension\OATHAuth
 */
class OATHAuth {
	public const AUTHENTICATED_OVER_2FA = 'OATHAuthAuthenticatedOver2FA';

	/**
	 * @var Config
	 */
	protected $config;
	/**
	 * @var LBFactory
	 */
	protected $dbLBFactory;

	/**
	 * @var array
	 */
	protected $modules = [];

	/**
	 * @param Config $config
	 * @param LBFactory $dbLBFactory
	 */
	public function __construct( $config, $dbLBFactory ) {
		$this->config = $config;
		$this->dbLBFactory = $dbLBFactory;
	}

	/**
	 * @param string $key
	 * @return IModule|null
	 */
	public function getModuleByKey( $key ) {
		$this->collectModules();
		if ( isset( $this->modules[$key] ) ) {
			$module = call_user_func_array( $this->modules[$key], [] );
			if ( $module instanceof IModule === false ) {
				return null;
			}
			return $module;
		}

		return null;
	}

	/**
	 * Get all modules registered on the wiki
	 *
	 * @return array
	 */
	public function getAllModules() {
		$this->collectModules();
		$modules = [];
		foreach ( $this->modules as $key => $callback ) {
			$module = $this->getModuleByKey( $key );
			if ( $module === null || !( $module instanceof IModule ) ) {
				continue;
			}
			$modules[$key] = $module;
		}
		return $modules;
	}

	private function collectModules() {
		$this->modules = ExtensionRegistry::getInstance()->getAttribute( 'OATHAuthModules' );
	}
}
