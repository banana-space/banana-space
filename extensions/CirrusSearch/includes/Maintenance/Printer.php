<?php

namespace CirrusSearch\Maintenance;

interface Printer {
	public function output( $message, $channel = null );

	public function outputIndented( $message );

	public function error( $err, $die = 0 );
}
