<?php

namespace CirrusSearch\Profile;

/**
 * Error occurring while managing search profiles.
 * May occur when attempting to load an invalid profile or because
 * the underlying profile repository is broken.
 * (It's likely a programming error that needs to be fixed.)
 */
class SearchProfileException extends \RuntimeException {

	/**
	 * @param string $message
	 * @param \Exception|null $cause
	 */
	public function __construct( $message, \Exception $cause = null ) {
		// flip $cause and $cause because it's most of our usecases
		// we have a cause but no particular error code.
		parent::__construct( $message, 0, $cause );
	}
}
