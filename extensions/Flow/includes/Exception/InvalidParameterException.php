<?php

namespace Flow\Exception;

/**
 * Exception for missing or invalid parameters to method calls, when not traced directly to
 * user input.
 *
 * This deliberately does not extend InvalidInputException, and must be loggable
 */
class InvalidParameterException extends FlowException {
	public function __construct( $message ) {
		parent::__construct( $message, 'invalid-parameter' );
	}

	protected function getErrorCodeList() {
		// flow-error-invalid-parameter
		return [ 'invalid-parameter' ];
	}
}
