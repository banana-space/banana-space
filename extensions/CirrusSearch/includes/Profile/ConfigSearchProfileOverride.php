<?php

namespace CirrusSearch\Profile;

/**
 * Overrider that gets its name using an entry in a Config object
 */
class ConfigSearchProfileOverride implements SearchProfileOverride {

	/**
	 * @var \Config
	 */
	private $config;

	/**
	 * @var string
	 */
	private $configEntry;

	/**
	 * @var int
	 */
	private $priority;

	/**
	 * @param \Config $config
	 * @param string $configEntry the name of the config entry holding the name of the overridden profile
	 * @param int $priority
	 */
	public function __construct( \Config $config, $configEntry, $priority = SearchProfileOverride::CONFIG_PRIO ) {
		$this->config = $config;
		$this->configEntry = $configEntry;
		$this->priority = $priority;
	}

	/**
	 * Get the overridden name or null if it cannot be overridden.
	 * @param string[] $contextParams
	 * @return string|null
	 */
	public function getOverriddenName( array $contextParams ) {
		if ( $this->config->has( $this->configEntry ) ) {
			return $this->config->get( $this->configEntry );
		}
		return null;
	}

	/**
	 * The priority of this override, lower wins
	 * @return int
	 */
	public function priority() {
		return $this->priority;
	}

	/**
	 * @return array
	 */
	public function explain(): array {
		return [
			'type' => 'config',
			'priority' => $this->priority(),
			'configEntry' => $this->configEntry,
			'value' => $this->getOverriddenName( [] )
		];
	}
}
