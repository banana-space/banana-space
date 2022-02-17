<?php

namespace CirrusSearch\Test;

use Psr\Log\AbstractLogger;

class ArrayLogger extends AbstractLogger {
	private $logs = [];

	public function log( $level, $message, array $context = [] ) {
		$this->logs[] = [
			'level' => $level,
			'message' => $message,
			'context' => $context,
		];
	}

	public function getLogs() {
		return $this->logs;
	}
}
