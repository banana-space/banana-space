<?php

use Flow\Exception\FlowException;
use Psr\Log\LogLevel;

class MaintenanceDebugLogger extends Psr\Log\AbstractLogger {
	/**
	 * @var Maintenance The maintenance script to perform output through
	 */
	protected $maintenance;

	/**
	 * @var int The maximum logLevelPosition to output to the
	 *  maintenance object. Defaults to LogLevel::INFO
	 */
	protected $maxLevel = 7;

	/**
	 * @var int[] Map from LogLevel constant to its position relative
	 *  to other constants.
	 */
	protected $logLevelPosition;

	public function __construct( Maintenance $maintenance ) {
		$this->maintenance = $maintenance;
		$this->logLevelPosition = [
			LogLevel::EMERGENCY => 1,
			LogLevel::ALERT => 2,
			LogLevel::CRITICAL => 3,
			LogLevel::ERROR => 4,
			LogLevel::WARNING => 5,
			LogLevel::NOTICE => 6,
			LogLevel::INFO => 7,
			LogLevel::DEBUG => 8
		];
	}

	/**
	 * @param string $level A LogLevel constant. Logged messages less
	 *  severe than this level will not be output.
	 */
	public function setMaximumLevel( $level ) {
		if ( !isset( $this->logLevelPosition[$level] ) ) {
			throw new FlowException( "Invalid LogLevel: $level" );
		}
		$this->maxLevel = $this->logLevelPosition[$level];
	}

	/**
	 * @inheritDoc
	 */
	public function log( $level, $message, array $context = [] ) {
		$position = $this->logLevelPosition[$level];
		if ( $position > $this->maxLevel ) {
			return;
		}

		// TS_DB is used as it is a consistent length every time
		$ts = '[' . wfTimestamp( TS_DB ) . ']';
		$this->maintenance->outputChanneled( "$ts $message" );
	}
}
