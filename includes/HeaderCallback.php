<?php

namespace MediaWiki;

/**
 * @since 1.29
 */
class HeaderCallback {
	private static $headersSentException;
	private static $messageSent = false;

	/**
	 * Register a callback to be called when headers are sent. There can only
	 * be one of these handlers active, so all relevant actions have to be in
	 * here.
	 *
	 * @since 1.29
	 */
	public static function register() {
		header_register_callback( [ __CLASS__, 'callback' ] );
	}

	/**
	 * The callback, which is called by the transport
	 *
	 * @since 1.29
	 */
	public static function callback() {
		// Prevent caching of responses with cookies (T127993)
		$headers = [];
		foreach ( headers_list() as $header ) {
			$header = explode( ':', $header, 2 );

			// Note: The code below (currently) does not care about value-less headers
			if ( isset( $header[1] ) ) {
				$headers[ strtolower( trim( $header[0] ) ) ][] = trim( $header[1] );
			}
		}

		if ( isset( $headers['set-cookie'] ) ) {
			$cacheControl = isset( $headers['cache-control'] )
				? implode( ', ', $headers['cache-control'] )
				: '';

			if ( !preg_match( '/(?:^|,)\s*(?:private|no-cache|no-store)\s*(?:$|,)/i',
				$cacheControl )
			) {
				header( 'Expires: Thu, 01 Jan 1970 00:00:00 GMT' );
				header( 'Cache-Control: private, max-age=0, s-maxage=0' );
				\MediaWiki\Logger\LoggerFactory::getInstance( 'cache-cookies' )->warning(
					'Cookies set on {url} with Cache-Control "{cache-control}"', [
						'url' => \WebRequest::getGlobalRequestURL(),
						'set-cookie' => self::sanitizeSetCookie( $headers['set-cookie'] ),
						'cache-control' => $cacheControl ?: '<not set>',
					]
				);
			}
		}

		// Set the request ID on the response, so edge infrastructure can log it.
		// FIXME this is not an ideal place to do it, but the most reliable for now.
		if ( !isset( $headers['x-request-id'] ) ) {
			header( 'X-Request-Id: ' . \WebRequest::getRequestId() );
		}

		// Save a backtrace for logging in case it turns out that headers were sent prematurely
		self::$headersSentException = new \Exception( 'Headers already sent from this point' );
	}

	/**
	 * Log a warning message if headers have already been sent. This can be
	 * called before flushing the output.
	 *
	 * @since 1.29
	 */
	public static function warnIfHeadersSent() {
		if ( headers_sent() && !self::$messageSent ) {
			self::$messageSent = true;
			\MWDebug::warning( 'Headers already sent, should send headers earlier than ' .
				wfGetCaller( 3 ) );
			$logger = \MediaWiki\Logger\LoggerFactory::getInstance( 'headers-sent' );
			$logger->error( 'Warning: headers were already sent from the location below', [
				'exception' => self::$headersSentException,
				'detection-trace' => new \Exception( 'Detected here' ),
			] );
		}
	}

	/**
	 * Sanitize Set-Cookie headers for logging.
	 * @param array $values List of header values.
	 * @return string
	 */
	public static function sanitizeSetCookie( array $values ) {
		$sanitizedValues = [];
		foreach ( $values as $value ) {
			// Set-Cookie header format: <cookie-name>=<cookie-value>; <non-sensitive attributes>
			$parts = explode( ';', $value );
			list( $name, $value ) = explode( '=', $parts[0], 2 );
			if ( strlen( $value ) > 8 ) {
				$value = substr( $value, 0, 8 ) . '...';
				$parts[0] = "$name=$value";
			}
			$sanitizedValues[] = implode( ';', $parts );
		}
		return implode( "\n", $sanitizedValues );
	}
}
