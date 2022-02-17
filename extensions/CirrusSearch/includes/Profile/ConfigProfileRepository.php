<?php

namespace CirrusSearch\Profile;

use Config;

/**
 * Profile repository backed by a Config object.
 */
class ConfigProfileRepository implements SearchProfileRepository {

	/**
	 * @var string
	 */
	private $type;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var string
	 */
	private $configEntry;

	/**
	 * @param string $type
	 * @param string $name
	 * @param string $configEntry the name of config key holding the list of profiles
	 * @param Config $config
	 */
	public function __construct( $type, $name, $configEntry, Config $config ) {
		$this->type = $type;
		$this->name = $name;
		$this->configEntry = $configEntry;
		$this->config = $config;
	}

	/**
	 *
	 * The repository type
	 * @return string
	 */
	public function repositoryType() {
		return $this->type;
	}

	/**
	 * The repository name
	 * @return string
	 */
	public function repositoryName() {
		return $this->name;
	}

	/**
	 * Load a profile named $name
	 * @param string $name
	 * @return array[]|null the profile data or null if not found
	 * @throws SearchProfileException
	 */
	public function getProfile( $name ) {
		$profiles = $this->extractProfiles();
		return $profiles[$name] ?? null;
	}

	/**
	 * Check if a profile named $name exists in this repository
	 * @param string $name
	 * @return bool
	 */
	public function hasProfile( $name ) {
		return isset( $this->extractProfiles()[$name] );
	}

	/**
	 * Get the list of profiles that we want to expose to the user.
	 *
	 * @return array[] list of profiles index by name
	 */
	public function listExposedProfiles() {
		return $this->extractProfiles();
	}

	private function extractProfiles() {
		if ( !$this->config->has( $this->configEntry ) ) {
			return [];
		}
		$profiles = $this->config->get( $this->configEntry );
		if ( !is_array( $profiles ) ) {
			throw new SearchProfileException( "Config entry {$this->configEntry} must be an array or unset" );
		}
		return $profiles;
	}
}
