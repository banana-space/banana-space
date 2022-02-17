<?php

namespace CirrusSearch\Test;

use CirrusSearch\Maintenance\Printer;

class NoopPrinter implements Printer {
	public function output( $message, $channel = null ) {
	}

	public function outputIndented( $message ) {
	}

	public function error( $err, $die = 0 ) {
		throw new \RuntimeException();
	}
}
