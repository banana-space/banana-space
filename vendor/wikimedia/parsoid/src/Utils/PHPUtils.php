<?php

namespace Wikimedia\Parsoid\Utils;

use Exception;
use Wikimedia\Assert\Assert;

/**
 * This file contains Parsoid-independent PHP helper functions.
 * Over time, more functions can be migrated out of various other files here.
 * @module
 */

class PHPUtils {
	/**
	 * Convert a counter to a Base64 encoded string.
	 * Padding is stripped. \,+ are replaced with _,- respectively.
	 * Warning: Max integer is 2^31 - 1 for bitwise operations.
	 * @param int $n
	 * @return string
	 */
	public static function counterToBase64( int $n ): string {
		$str = '';
		do {
			$str = chr( $n & 0xff ) . $str;
			$n = $n >> 8;
		} while ( $n > 0 );
		return rtrim( strtr( base64_encode( $str ), '+/', '-_' ), '=' );
	}

	/**
	 * Return accurate system time
	 * @return float time in seconds since Jan 1 1970 GMT accurate to the microsecond
	 */
	public static function getStartHRTime(): float {
		return microtime( true );
	}

	/**
	 * Return millisecond accurate system time differential
	 * @param float $previousTime
	 * @return float milliseconds
	 */
	public static function getHRTimeDifferential( float $previousTime ): float {
		return ( microtime( true ) - $previousTime ) * 1000;
	}

	/**
	 * json_encode wrapper function
	 * - unscapes slashes and unicode
	 *
	 * @param mixed $o
	 * @return string
	 */
	public static function jsonEncode( $o ): string {
		$str = json_encode( $o, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( $str === false ) {
			// Do this manually until JSON_THROW_ON_ERROR is available
			throw new Exception( 'JSON encoding failed.' );
		}
		return $str;
	}

	/**
	 * json_decode wrapper function
	 * @param string $str String to decode into the json object
	 * @param bool $assoc Controls whether to parse as an an associative array - defaults to true
	 * @return mixed
	 */
	public static function jsonDecode( string $str, bool $assoc = true ) {
		return json_decode( $str, $assoc );
	}

	/**
	 * Convert array to associative array usable as a read-only Set.
	 *
	 * @param array $a
	 * @return array
	 */
	public static function makeSet( array $a ): array {
		return array_fill_keys( $a, true );
	}

	/**
	 * PORT-FIXME: To be removed once all uses of this have disappeared
	 * Convert array to associative array usable as a key-value Map
	 *
	 * Instead of
	 *
	 *     $var = PHPUtils::makeMap( [
	 *         [ 'key1', 'value1' ],
	 *         [ 'key2', 'value2' ],
	 *     ] );
	 *
	 * just do
	 *
	 *     $var = [
	 *         'key1' => 'value1',
	 *         'key2' => 'value2',
	 *     ];
	 *
	 * Unlike JS objects, PHP's associative arrays already preserve order.
	 *
	 * @param array $a
	 * @return array
	 */
	public static function makeMap( array $a ) {
		throw new \BadMethodCallException(
			'Don\'t use this, just declare your associative array directly'
		);
	}

	/**
	 * Helper to get last item of the array
	 * @param mixed[] $a
	 * @return mixed
	 */
	public static function lastItem( array $a ) {
		// Tim Starling recommends not using end() for perf reasons
		// since apparently it can be O(n) where the refcount on the
		// array is > 1.
		//
		// Note that end() is usable in non-array scenarios. But, in our case,
		// we are almost always dealing with arrays, so this helper probably
		// better for cases where we aren't sure the array isn't shared.
		return $a[count( $a ) - 1] ?? null;
	}

	/**
	 * Append an array to an accumulator using the most efficient method
	 * available. Makes sure that accumulation is O(n).
	 *
	 * @param array &$dest Destination array
	 * @param array $source Array to merge
	 */
	public static function pushArray( array &$dest, array $source ): void {
		if ( count( $dest ) < count( $source ) ) {
			$dest = array_merge( $dest, $source );
		} else {
			foreach ( $source as $item ) {
				$dest[] = $item;
			}
		}
	}

	/**
	 * Return a substring, asserting that it is valid UTF-8.
	 * By default we assume the full string was valid UTF-8, which allows
	 * us to look at the first and last bytes to make this check.
	 * You can check the entire string if you are feeling paranoid; it
	 * will take O(N) time (where N is the length of the substring) but
	 * so does the substring operation.
	 *
	 * If the substring would start beyond the end of the string or
	 * end before the start of the string, then this function will
	 * return the empty string (as would JavaScript); note that the
	 * native `substr` would return `false` in this case.
	 *
	 * Using this helper instead of native `substr` is
	 * useful during the PHP port to verify that we don't break up
	 * Unicode codepoints by the switch from JavaScript UCS-2 offsets
	 * to PHP UTF-8 byte offsets.
	 *
	 * @param string $s The (sub)string to check
	 * @param int $start The starting offset (in bytes). If negative, the
	 *  offset is counted from the end of the string.
	 * @param int|null $length (optional) The maximum length of the returned
	 *  string. If negative, the end position is counted from the end of
	 *  the string.
	 * @param bool $checkEntireString Whether to do a slower verification
	 *   of the entire string, not just the edges. Defaults to false.
	 * @return string The checked substring
	 */
	public static function safeSubstr(
		string $s, int $start, ?int $length = null,
		bool $checkEntireString = false
	): string {
		if ( $length === null ) {
			$ss = substr( $s, $start );
		} else {
			$ss = substr( $s, $start, $length );
		}
		if ( $ss === false ) {
			$ss = '';
		}
		if ( strlen( $ss ) === 0 ) {
			return $ss;
		}
		$firstChar = ord( $ss );
		Assert::invariant(
			( $firstChar & 0xC0 ) !== 0x80,
			'Bad UTF-8 at start of string'
		);
		$i = 0;
		// This next loop won't step off the front of the string because we've
		// already asserted that the first character is not 10xx xxxx
		do {
			$i--;
			Assert::invariant(
				$i > -5,
				// This should never happen, assuming the original string
				// was valid UTF-8
				'Bad UTF-8 at end of string (>4 byte sequence)'
			);
			$lastChar = ord( $ss[$i] );
		} while ( ( $lastChar & 0xC0 ) === 0x80 );
		if ( ( $lastChar & 0x80 ) === 0 ) {
			Assert::invariant(
				// This shouldn't happen, assuming original string was valid
				$i === -1, 'Bad UTF-8 at end of string (1 byte sequence)'
			);
		} elseif ( ( $lastChar & 0xE0 ) === 0xC0 ) {
			Assert::invariant(
				$i === -2, 'Bad UTF-8 at end of string (2 byte sequence)'
			);
		} elseif ( ( $lastChar & 0xF0 ) === 0xE0 ) {
			Assert::invariant(
				$i === -3, 'Bad UTF-8 at end of string (3 byte sequence)'
			);
		} elseif ( ( $lastChar & 0xF8 ) === 0xF0 ) {
			Assert::invariant(
				$i === -4, 'Bad UTF-8 at end of string (4 byte sequence)'
			);
		} else {
			self::unreachable(
				// This shouldn't happen, assuming original string was valid
				'Bad UTF-8 at end of string'
			);
		}
		if ( $checkEntireString ) {
			// We did the head/tail checks first because they give better
			// diagnostics in the common case where we broke UTF-8 by
			// the substring operation.
			self::assertValidUTF8( $ss );
		}
		return $ss;
	}

	/**
	 * Helper for verifying a valid UTF-8 encoding.  Using
	 * safeSubstr() is a more efficient way of doing this check in
	 * most places, where you can assume that the original string was
	 * valid UTF-8.  This function does a complete traversal of the
	 * string, in time proportional to the length of the string.
	 *
	 * @param string $s The string to check.
	 */
	public static function assertValidUTF8( string $s ): void {
		// Slow complete O(N) check for UTF-8 validity
		$r = preg_match( "/^(?:
			[\\x00-\\x7F] |
			[\\xC0-\\xDF][\\x80-\\xBF] |
			[\\xE0-\\xEF][\\x80-\\xBF]{2} |
			[\\xF0-\\xF7][\\x80-\\xBF]{3}
		)*+$/xSD", $s );
		Assert::invariant(
			$r === 1,
			'Bad UTF-8 (full string verification)'
		);
	}

	/**
	 * Helper for joining pieces of regular expressions together.  This
	 * safely strips delimiters from regular expression strings, while
	 * ensuring that the result is safely escaped for the new delimiter
	 * you plan to use (see the `$delimiter` argument to `preg_quote`).
	 * Note that using a meta-character for the new delimiter can lead to
	 * unexpected results; for example, if you use `!` then escaping
	 * `(?!foo)` will break the regular expression.
	 *
	 * @param string $re The regular expression to strip
	 * @param string|null $newDelimiter Optional delimiter which will be
	 *   used when recomposing this stripped regular expression into a
	 *   new regular expression.
	 * @return string The regular expression without delimiters or flags
	 */
	public static function reStrip( string $re, ?string $newDelimiter = null ): string {
		static $delimiterPairs = [
			'(' => ')',
			'[' => ']',
			'{' => '}',
			'<' => '>',
		];
		// Believe it or not, PHP allows leading whitespace in the $re
		// tested with C's "isspace", which is [ \f\n\r\t\v]
		$re = preg_replace( '/^[ \f\n\r\t\v]+/', '', $re );
		Assert::invariant( strlen( $re ) > 0, "empty regexp" );
		$startDelimiter = $re[0];
		// PHP actually supports balanced delimiters (ie open paren on left
		// and close paren on right).
		$endDelimiter = $delimiterPairs[$startDelimiter] ?? $startDelimiter;
		$endDelimiterPos = strrpos( $re, $endDelimiter );
		Assert::invariant(
			$endDelimiterPos !== false && $endDelimiterPos > 0,
			"can't find end delimiter"
		);
		$flags = substr( $re, $endDelimiterPos + 1 );
		Assert::invariant(
			preg_match( '/^[imsxADSUXJu \n]*$/D', $flags ) === 1,
			"unexpected flags"
		);
		$stripped = substr( $re, 1, $endDelimiterPos - 1 );
		if (
			$newDelimiter === null ||
			$startDelimiter === $newDelimiter ||
			$endDelimiter === $newDelimiter
		) {
			return $stripped; // done!
		}
		$newCloseDelimiter = $delimiterPairs[$startDelimiter] ?? $startDelimiter;
		// escape the new delimiter
		preg_match_all( '/[^\\\\]|\\\\./s', $stripped, $matches );
		return implode( '', array_map( function ( $c ) use ( $newDelimiter, $newCloseDelimiter ) {
			return ( $c === $newDelimiter || $c === $newCloseDelimiter )
				? ( '\\' . $c ) : $c;
		}, $matches[0] ) );
	}

	/**
	 * JS-compatible encodeURIComponent function
	 * FIXME: See T221147 (for a post-port update)
	 *
	 * @param string $str
	 * @return string
	 */
	public static function encodeURIComponent( string $str ): string {
		$revert = [ '%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')' ];
		return strtr( rawurlencode( $str ), $revert );
	}

	/**
	 * Simulate the JS || operator.
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function coalesce( ...$args ) {
		foreach ( $args as $arg ) {
			// != '' has the same semantics as JS truthiness: false for null, false,
			// 0, 0.0, '', true for everything else
			if ( $arg != '' ) {
				return $arg;
			}
		}
		return end( $args );
	}

	/**
	 * Convert an array to an object. Workaround for
	 * T228346 / https://bugs.php.net/bug.php?id=78379
	 *
	 * PHP 7 introduced "efficient" casting of arrays to objects by taking a
	 * reference instead of duplicating the array. However, this was not
	 * properly accounted for in the garbage collector. The garbage collector
	 * would free the array while it was still referred to by live objects.
	 *
	 * The workaround here is to manually duplicate the array. It's not
	 * necessary to do a deep copy since only the top-level array is referenced
	 * by the new object.
	 *
	 * It's only necessary to call this for potentially shared arrays, such as
	 * compile-time constants. Arrays that have a reference count of 1 can be
	 * cast to objects in the usual way. For example, array literals containing
	 * variables are typically unshared.
	 *
	 * @param array $array
	 * @return \stdClass
	 */
	public static function arrayToObject( $array ) {
		// FIXME: remove this workaround (T254519)
		return (object)array_combine( array_keys( $array ), array_values( $array ) );
	}

	/**
	 * Sort keys in an array, recursively, for better reproducibility.
	 * (This is especially useful before serializing as JSON.)
	 *
	 * @param mixed &$array
	 */
	public static function sortArray( &$array ): void {
		if ( !is_array( $array ) ) {
			return;
		}
		ksort( $array );
		foreach ( $array as $k => $v ) {
			self::sortArray( $array[$k] );
		}
	}

	/**
	 * Indicate that the code which calls this function is intended to be
	 * unreachable.
	 *
	 * This is a workaround for T247093; hopefully we can move this
	 * function upstream into wikimedia/assert.
	 *
	 * @param string $reason
	 */
	public static function unreachable( string $reason = "should never happen" ) {
		// @phan-suppress-next-line PhanImpossibleCondition
		Assert::invariant( false, $reason );
	}

}
