<?php

class EchoCatchableFatalErrorException extends MWException {

	public function __construct( $errno, $errstr, $errfile, $errline ) {
		parent::__construct( "Catchable fatal error: $errstr", $errno );
		// inherited protected variables from Exception
		$this->file = $errfile;
		$this->line = $errline;
	}

}
