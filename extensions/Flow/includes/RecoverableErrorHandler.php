<?php

namespace Flow;

use Flow\Exception\CatchableFatalErrorException;

/**
 * Catches E_RECOVERABLE_ERROR and converts into exceptions
 * instead of fataling.
 *
 * Usage:
 *  set_error_handler( new RecoverableErrorHandler, E_RECOVERABLE_ERROR );
 *  try {
 *      ...
 *  } catch ( CatchableFatalErrorException $fatal ) {
 *
 *  } finally {}
 *      restore_error_handler();
 *  }
 */
class RecoverableErrorHandler {
	public function __invoke( $errno, $errstr, $errfile, $errline ) {
		if ( $errno !== E_RECOVERABLE_ERROR ) {
			return false;
		}

		throw new CatchableFatalErrorException( $errstr, 0, $errno, $errfile, $errline );
	}
}
