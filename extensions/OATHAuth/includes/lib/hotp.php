<?php
/**
 * HOTP Class
 * Based on the work of OAuth, and the sample implementation of HMAC OTP
 * http://tools.ietf.org/html/draft-mraihi-oath-hmac-otp-04#appendix-D
 * @author Jakob Heuser (firstname)@felocity.com
 * @copyright 2011
 * @license BSD-3-Clause
 * @version 1.0
 */
class HOTP {
	/**
	 * Generate a HOTP key based on a counter value (event based HOTP)
	 * @param string $key the key to use for hashing
	 * @param int $counter the number of attempts represented in this hashing
	 * @return HOTPResult a HOTP Result which can be truncated or output
	 */
	public static function generateByCounter( $key, $counter ) {
		// the counter value can be more than one byte long,
		// so we need to pack it down properly.
		$cur_counter = array( 0, 0, 0, 0, 0, 0, 0, 0 );
		for ( $i = 7; $i >= 0; $i-- ) {
			$cur_counter[$i] = pack( 'C*', $counter );
			$counter = $counter >> 8;
		}

		$bin_counter = implode( $cur_counter );

		// Pad to 8 chars
		if ( strlen( $bin_counter ) < 8 ) {
			$bin_counter = str_repeat( "\0", 8 - strlen( $bin_counter ) ) . $bin_counter;
		}

		// HMAC
		$hash = hash_hmac( 'sha1', $bin_counter, $key );

		return new HOTPResult( $hash );
	}

	/**
	 * Generate a HOTP key based on a timestamp and window size
	 *
	 * @param string $key the key to use for hashing
	 * @param int $window the size of the window a key is valid for in seconds
	 * @param int|bool $timestamp a timestamp to calculate for, defaults to time()
	 *
	 * @return HOTPResult a HOTP Result which can be truncated or output
	 */
	public static function generateByTime( $key, $window, $timestamp = false ) {
		if ( !$timestamp && $timestamp !== 0 ) {
			$timestamp = HOTP::getTime();
		}

		$counter = (int)( $timestamp / $window );

		return HOTP::generateByCounter( $key, $counter );
	}

	/**
	 * Generate a HOTP key collection based on a timestamp and window size
	 * all keys that could exist between a start and end time will be included
	 * in the returned array
	 *
	 * @param string $key the key to use for hashing
	 * @param int $window the size of the window a key is valid for in seconds
	 * @param int $min the minimum window to accept before $timestamp
	 * @param int $max the maximum window to accept after $timestamp
	 * @param int|bool $timestamp a timestamp to calculate for, defaults to time()
	 *
	 * @return HOTPResult[]
	 */
	public static function generateByTimeWindow( $key, $window, $min = -1,
		$max = 1, $timestamp = false
	) {
		if ( !$timestamp && $timestamp !== 0 ) {
			$timestamp = HOTP::getTime();
		}

		$counter = (int)( $timestamp / $window );
		$window = range( $min, $max );

		$out = array();
		$length = count( $window );
		for ( $i = 0; $i < $length; $i++ ) {
			$shift_counter = $counter + $window[$i];
			$out[$shift_counter] = HOTP::generateByCounter($key, $shift_counter);
		}

		return $out;
	}

	/**
	 * Gets the current time
	 * Ensures we are operating in UTC for the entire framework
	 * Restores the timezone on exit.
	 * @return int the current time
	 */
	public static function getTime() {
		return time(); // PHP's time is always UTC
	}
}

/**
 * The HOTPResult Class converts an HOTP item to various forms
 * Supported formats include hex, decimal, string, and HOTP
 * @author Jakob Heuser (firstname)@felocity.com
 */
class HOTPResult {
	protected $hash;
	protected $binary;
	protected $decimal;
	protected $hex;

	/**
	 * Build an HOTP Result
	 * @param string $value the value to construct with
	 */
	public function __construct( $value ) {
		// store raw
		$this->hash = $value;

		// store calculate decimal
		$hmac_result = array();

		// Convert to decimal
		foreach ( str_split( $this->hash, 2 ) as $hex ) {
			$hmac_result[] = hexdec($hex);
		}

		$offset = $hmac_result[19] & 0xf;

		$this->decimal = (
			( ( $hmac_result[$offset+0] & 0x7f ) << 24 ) |
			( ( $hmac_result[$offset+1] & 0xff ) << 16 ) |
			( ( $hmac_result[$offset+2] & 0xff ) << 8 ) |
			( $hmac_result[$offset+3] & 0xff )
		);

		// calculate hex
		$this->hex = dechex( $this->decimal );
	}

	/**
	 * Returns the string version of the HOTP
	 * @return string
	 */
	public function toString() {
		return $this->hash;
	}

	/**
	 * Returns the hex version of the HOTP
	 * @return string
	 */
	public function toHex() {
		return $this->hex;
	}

	/**
	 * Returns the decimal version of the HOTP
	 * @return int
	 */
	public function toDec() {
		return $this->decimal;
	}

	/**
	 * Returns the truncated decimal form of the HOTP
	 * @param int $length the length of the HOTP to return
	 * @return string
	 */
	public function toHOTP( $length ) {
		$str = str_pad( $this->toDec(), $length, "0", STR_PAD_LEFT );
		$str = substr( $str, ( -1 * $length ) );

		return $str;
	}

}
