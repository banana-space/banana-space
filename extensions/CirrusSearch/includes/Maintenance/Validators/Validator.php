<?php

namespace CirrusSearch\Maintenance\Validators;

use CirrusSearch\Maintenance\Printer;

abstract class Validator {
	/**
	 * @var Printer
	 */
	protected $out;

	/**
	 * @var bool
	 */
	protected $printDebugCheckConfig = false;

	/**
	 * @param Printer $out to relay output to.
	 */
	public function __construct( Printer $out ) {
		$this->out = $out;
	}

	/**
	 * @return \Status
	 */
	abstract public function validate();

	/**
	 * @param bool $print
	 */
	public function printDebugCheckConfig( $print = true ) {
		$this->printDebugCheckConfig = (bool)$print;
	}

	/**
	 * @param mixed $actual
	 * @param array $required
	 * @param string|null $indent
	 * @return bool
	 */
	protected function checkConfig( $actual, array $required, $indent = null ) {
		foreach ( $required as $key => $value ) {
			$this->debugCheckConfig( "\n$indent$key: " );
			if ( !array_key_exists( $key, $actual ) ) {
				$this->debugCheckConfig( "not found..." );
				return false;
			}
			if ( is_array( $value ) ) {
				$this->debugCheckConfig( "descend..." );
				if ( !is_array( $actual[ $key ] ) ) {
					$this->debugCheckConfig( "other not array..." );
					return false;
				}
				if ( !$this->checkConfig( $actual[ $key ], $value, $indent . "\t" ) ) {
					return false;
				}
				continue;
			}

			$actual[ $key ] = $this->normalizeConfigValue( $actual[ $key ] );
			$value = $this->normalizeConfigValue( $value );
			$this->debugCheckConfig( $actual[ $key ] . " ?? $value..." );
			// Note that I really mean !=, not !==.  Coercion is cool here.
			// print $actual[ $key ] . "  $value\n";
			if ( $actual[ $key ] != $value ) {
				$this->debugCheckConfig( 'different...' );
				return false;
			}
		}
		return true;
	}

	/**
	 * Normalize a config value for comparison.  Elasticsearch will accept all kinds
	 * of config values but it tends to through back 'true' for true and 'false' for
	 * false so we normalize everything.  Sometimes, oddly, it'll through back false
	 * for false....
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function normalizeConfigValue( $value ) {
		if ( $value === true ) {
			return 'true';
		} elseif ( $value === false ) {
			return 'false';
		}
		return $value;
	}

	/**
	 * @param string $string
	 */
	private function debugCheckConfig( $string ) {
		if ( $this->printDebugCheckConfig ) {
			$this->output( $string );
		}
	}

	/**
	 * @param string $message
	 * @param mixed|null $channel
	 */
	protected function output( $message, $channel = null ) {
		if ( $this->out ) {
			$this->out->output( $message, $channel );
		}
	}

	/**
	 * @param string $message
	 */
	protected function outputIndented( $message ) {
		if ( $this->out ) {
			$this->out->outputIndented( $message );
		}
	}
}
