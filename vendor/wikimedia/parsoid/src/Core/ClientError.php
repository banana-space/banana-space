<?php

namespace Wikimedia\Parsoid\Core;

use Exception;

/**
 * Exception thrown on invalid client requests. Should result in a HTTP 400.
 */
class ClientError extends Exception {

	/**
	 * @param string $message
	 */
	public function __construct( string $message = 'Bad Request' ) {
		parent::__construct( $message );
	}

}
