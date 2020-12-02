<?php

namespace Wikimedia\Parsoid\Tools;

trait ExtendedOptsProcessor {
	private $optionDefaults = [];

	/**
	 * @param string $name
	 * @param mixed $default
	 */
	public function setOptionDefault( string $name, $default ) {
		$this->optionDefaults[$name] = $default;
	}

	/** @inheritDoc */
	public function getOption( string $name, $default = null ) {
		return parent::getOption(
			$name, $default ?? $this->optionDefaults[$name] ?? null
		);
	}

	/**
	 * Return all known CLI options in an associative array
	 * @return array
	 */
	public function optionsToArray(): array {
		$options = [];
		// Set CLI args
		foreach ( $this->getOptions() as $name => $value ) {
			$options[$name] = $value;
		}
		// Add in defaults
		foreach ( $this->optionDefaults as $name => $value ) {
			if ( !isset( $options[$name] ) ) {
				$options[$name] = $value;
			}
		}
		return $options;
	}
}
