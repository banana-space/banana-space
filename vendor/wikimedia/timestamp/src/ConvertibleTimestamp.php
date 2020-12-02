<?php
/**
 * Timestamp
 *
 * Copyright (C) 2012 Tyler Romeo <tylerromeo@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Tyler Romeo <tylerromeo@gmail.com>
 */

namespace Wikimedia\Timestamp;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;

/**
 * Library for creating, parsing, and converting timestamps.
 */
class ConvertibleTimestamp {
	/**
	 * Standard gmdate() formats for the different timestamp types.
	 */
	private static $formats = [
		TS_UNIX => 'U',
		TS_MW => 'YmdHis',
		TS_DB => 'Y-m-d H:i:s',
		TS_ISO_8601 => 'Y-m-d\TH:i:s\Z',
		TS_ISO_8601_BASIC => 'Ymd\THis\Z',
		TS_EXIF => 'Y:m:d H:i:s', // This shouldn't ever be used, but is included for completeness
		TS_RFC2822 => 'D, d M Y H:i:s',
		TS_ORACLE => 'd-m-Y H:i:s.u', // Was 'd-M-y h.i.s A' . ' +00:00' before r51500
		TS_POSTGRES => 'Y-m-d H:i:s+00', // Formerly 'Y-m-d H:i:s' . ' GMT'
		TS_UNIX_MICRO => 'U.u',
	];

	/**
	 * Regexes for setTimestamp(). Named capture groups correspond to format codes for
	 * DateTime::createFromFormat(). Unnamed groups are ignored.
	 */
	private static $regexes = [
		// 'TS_DB' => subset of TS_ISO_8601 (with no 'T')
		'TS_MW' => '/^(?<Y>\d{4})(?<m>\d\d)(?<d>\d\d)(?<H>\d\d)(?<i>\d\d)(?<s>\d\d)$/D',
		'TS_ISO_8601' =>
			'/^(?<Y>\d{4})-(?<m>\d{2})-(?<d>\d{2})[T ]' .
				'(?<H>\d{2}):(?<i>\d{2}):(?<s>\d{2})(?:[.,](?<u>\d{1,6}))?' .
				'(?<O>Z|[+\-]\d{2}(?::?\d{2})?)?$/',
		'TS_ISO_8601_BASIC' =>
			'/^(?<Y>\d{4})(?<m>\d{2})(?<d>\d{2})T(?<H>\d{2})(?<i>\d{2})(?<s>\d{2})(?:[.,](?<u>\d{1,6}))?' .
				'(?<O>Z|[+\-]\d{2}(?::?\d{2})?)?$/',
		'TS_UNIX' => '/^(?<U>-?\d{1,13})$/D',
		'TS_UNIX_MICRO' => '/^(?<U>-?\d{1,13})\.(?<u>\d{1,6})$/D',
		'TS_ORACLE' =>
			'/^(?<d>\d{2})-(?<m>\d{2})-(?<Y>\d{4}) (?<H>\d{2}):(?<i>\d{2}):(?<s>\d{2})\.(?<u>\d{6})$/',
		// TS_POSTGRES is almost redundant to TS_ISO_8601 (with no 'T'), but accepts a space in place of
		// a `+` before the timezone.
		'TS_POSTGRES' =>
			'/^(?<Y>\d{4})-(?<m>\d\d)-(?<d>\d\d) (?<H>\d\d):(?<i>\d\d):(?<s>\d\d)(?:\.(?<u>\d{1,6}))?' .
				'(?<O>[\+\- ]\d\d)$/',
		'old TS_POSTGRES' =>
			'/^(?<Y>\d{4})-(?<m>\d\d)-(?<d>\d\d) (?<H>\d\d):(?<i>\d\d):(?<s>\d\d)(?:\.(?<u>\d{1,6}))? GMT$/',
		'TS_EXIF' => '/^(?<Y>\d{4}):(?<m>\d\d):(?<d>\d\d) (?<H>\d\d):(?<i>\d\d):(?<s>\d\d)$/D',

		'TS_RFC2822' =>
			# Day of week
			'/^[ \t\r\n]*(?:(?<D>[A-Z][a-z]{2}),[ \t\r\n]*)?' .
			# dd Mon yyyy
			'(?<d>\d\d?)[ \t\r\n]+(?<M>[A-Z][a-z]{2})[ \t\r\n]+(?<Y>\d{2,})' .
			# hh:mm:ss
			'[ \t\r\n]+(?<H>\d\d)[ \t\r\n]*:[ \t\r\n]*(?<i>\d\d)[ \t\r\n]*:[ \t\r\n]*(?<s>\d\d)' .
			# zone, optional for hysterical raisins
			'(?:[ \t\r\n]+(?<O>[+-]\d{4}|UT|GMT|[ECMP][SD]T|[A-IK-Za-ik-z]))?' .
			# optional trailing comment
			# See http://www.squid-cache.org/mail-archive/squid-users/200307/0122.html / r77171
			'(?:[ \t\r\n]*;|$)/S',

		'TS_RFC850' =>
			'/^(?<D>[A-Z][a-z]{5,8}), (?<d>\d\d)-(?<M>[A-Z][a-z]{2})-(?<y>\d{2}) ' .
			'(?<H>\d\d):(?<i>\d\d):(?<s>\d\d)' .
			# timezone optional for hysterical raisins. RFC just says "worldwide time zone abbreviations".
			# https://en.wikipedia.org/wiki/List_of_time_zone_abbreviations lists strings of up to 5
			# uppercase letters. PHP 7.2's DateTimeZone::listAbbreviations() lists strings of up to 4
			# letters.
			'(?: (?<O>[+\-]\d{2}(?::?\d{2})?|[A-Z]{1,5}))?$/',

		'asctime' => '/^(?<D>[A-Z][a-z]{2}) (?<M>[A-Z][a-z]{2}) +(?<d>\d{1,2}) ' .
			'(?<H>\d\d):(?<i>\d\d):(?<s>\d\d) (?<Y>\d{4})\s*$/',
	];

	/**
	 * @var callback|null
	 * @see setFakeTime()
	 */
	protected static $fakeTimeCallback = null;

	/**
	 * Get the current time in the same form that PHP's built-in time() function uses.
	 *
	 * This is used by now() get setTimestamp( false ) instead of the built in time() function.
	 * The output of this method can be overwritten for testing purposes by calling setFakeTime().
	 *
	 * @return int UNIX epoch
	 */
	public static function time() {
		return static::$fakeTimeCallback ? call_user_func( static::$fakeTimeCallback ) : \time();
	}

	/**
	 * Set a fake time value or clock callback.
	 *
	 * @param callable|string|int|false $fakeTime a fixed time string, or an integer Unix time, or
	 *   a callback() returning an int representing a UNIX epoch, or false to disable fake time and
	 *   go back to real time.
	 *
	 * @return callable|null the previous fake time callback, if any.
	 */
	public static function setFakeTime( $fakeTime ) {
		if ( is_string( $fakeTime ) ) {
			$fakeTime = (int)static::convert( TS_UNIX, $fakeTime );
		}

		if ( is_int( $fakeTime ) ) {
			$fakeTime = function () use ( $fakeTime ) {
				return $fakeTime;
			};
		}

		$old = static::$fakeTimeCallback;
		static::$fakeTimeCallback = $fakeTime ? $fakeTime : null;
		return $old;
	}

	/**
	 * The actual timestamp being wrapped (DateTime object).
	 * @var DateTime
	 */
	public $timestamp;

	/**
	 * Make a new timestamp and set it to the specified time,
	 * or the current time if unspecified.
	 *
	 * @param bool|string|int|float|DateTime $timestamp Timestamp to set, or false for current time
	 */
	public function __construct( $timestamp = false ) {
		if ( $timestamp instanceof DateTime ) {
			$this->timestamp = $timestamp;
		} else {
			$this->setTimestamp( $timestamp );
		}
	}

	/**
	 * Set the timestamp to the specified time, or the current time if unspecified.
	 *
	 * Parse the given timestamp into either a DateTime object or a Unix timestamp,
	 * and then store it.
	 *
	 * @param string|bool $ts Timestamp to store, or false for now
	 * @throws TimestampException
	 */
	public function setTimestamp( $ts = false ) {
		$format = null;

		// We want to catch 0, '', null... but not date strings starting with a letter.
		if ( !$ts || $ts === "\0\0\0\0\0\0\0\0\0\0\0\0\0\0" ) {
			$name = 'null';
			$strtime = (string)self::time();
			$format = 'U';
		} else {
			foreach ( self::$regexes as $name => $regex ) {
				if ( !preg_match( $regex, $ts, $m ) ) {
					continue;
				}

				// TS_POSTGRES's match for 'O' can begin with a space, which PHP doesn't accept
				if ( $name === 'TS_POSTGRES' && isset( $m['O'] ) && $m['O'][0] === ' ' ) {
					$m['O'][0] = '+';
				}

				if ( $name === 'TS_RFC2822' ) {
					// RFC 2822 rules for two- and three-digit years
					if ( $m['Y'] < 1000 ) {
						$m['Y'] += $m['Y'] < 50 ? 2000 : 1900;
					}

					// TS_RFC2822 timezone fixups
					if ( isset( $m['O'] ) ) {
						// obs-zone value not recognized by PHP
						if ( $m['O'] === 'UT' ) {
							$m['O'] = 'UTC';
						}

						// RFC 2822 says all these should be treated as +0000 due to an error in RFC 822
						if ( strlen( $m['O'] ) === 1 ) {
							$m['O'] = '+0000';
						}
					}
				}

				if ( $name === 'TS_UNIX_MICRO' && $m['U'] < 0 && $m['u'] > 0 ) {
					// createFromFormat()'s componentwise behavior is counterintuitive in this case, "-1.2" gets
					// interpreted as "-1 seconds + 200000 microseconds = -0.8 seconds" rather than as a decimal
					// "-1.2 seconds" like we want. So correct the values to match the componentwise
					// interpretation.
					$m['U']--;
					$m['u'] = 1000000 - str_pad( $m['u'], 6, '0' );
				}

				$filtered = [];
				foreach ( $m as $k => $v ) {
					if ( !is_int( $k ) && $v !== '' ) {
						$filtered[$k] = $v;
					}
				}
				$format = implode( ' ', array_keys( $filtered ) );
				$strtime = implode( ' ', array_values( $filtered ) );

				break;
			}
		}

		if ( $format === null ) {
			throw new TimestampException( __METHOD__ . ": Invalid timestamp - $ts" );
		}

		try {
			if ( $format[0] === 'U' && $strtime[0] === '-' ) {
				// Work around an HHVM bug, createFromFormat( 'U' ) doesn't work with
				// negative timestamps.
				list( $s, $us ) = $format === 'U u' ? explode( ' ', $strtime ) : [ $strtime, 0 ];
				$final = DateTime::createFromFormat( '!U u', "0 $us" );
				$final->sub( new DateInterval( 'PT' . abs( $s ) . 'S' ) );
			} else {
				$final = DateTime::createFromFormat( "!$format", $strtime, new DateTimeZone( 'UTC' ) );
			}
		} catch ( Exception $e ) {
			throw new TimestampException( __METHOD__ . ': Invalid timestamp format.', $e->getCode(), $e );
		}

		if ( $final === false ) {
			throw new TimestampException( __METHOD__ . ': Invalid timestamp format.' );
		}

		$this->timestamp = $final;
	}

	/**
	 * Converts any timestamp to the given string format.
	 * This is identical to `( new ConvertibleTimestamp() )->getTimestamp()`,
	 * except it returns false instead of throwing an exception.
	 *
	 * @param int $style Constant Output format for timestamp
	 * @param string|int|float|bool|DateTime $ts Timestamp
	 * @return string|false Formatted timestamp or false on failure
	 */
	public static function convert( $style, $ts ) {
		try {
			$ct = new static( $ts );
			return $ct->getTimestamp( $style );
		} catch ( TimestampException $e ) {
			return false;
		}
	}

	/**
	 * Get the current time in the given format
	 *
	 * @param int $style Constant Output format for timestamp
	 * @return string
	 */
	public static function now( $style = TS_MW ) {
		return static::convert( $style, false );
	}

	/**
	 * Get the timestamp represented by this object in a certain form.
	 *
	 * Convert the internal timestamp to the specified format and then
	 * return it.
	 *
	 * @param int $style Constant Output format for timestamp
	 * @throws TimestampException
	 * @return string The formatted timestamp
	 */
	public function getTimestamp( $style = TS_UNIX ) {
		if ( !isset( self::$formats[$style] ) ) {
			throw new TimestampException( __METHOD__ . ': Illegal timestamp output type.' );
		}

		// All our formats are in UTC, so make sure to use that timezone
		$timestamp = clone $this->timestamp;
		$timestamp->setTimezone( new DateTimeZone( 'UTC' ) );

		if ( $style === TS_UNIX_MICRO ) {
			$seconds = $timestamp->format( 'U' );
			$microseconds = $timestamp->format( 'u' );
			if ( $seconds < 0 && $microseconds > 0 ) {
				// Adjust components to properly create a decimal number for TS_UNIX_MICRO and negative
				// timestamps. See the comment in setTimestamp() for details.
				$seconds++;
				$microseconds = 1000000 - $microseconds;
			}
			return sprintf( "%d.%06d", $seconds, $microseconds );
		}

		$output = $timestamp->format( self::$formats[$style] );

		if ( $style == TS_RFC2822 ) {
			$output .= ' GMT';
		}

		if ( $style == TS_MW && strlen( $output ) !== 14 ) {
			throw new TimestampException( __METHOD__ . ': The timestamp cannot be represented in ' .
				'the specified format' );
		}

		return $output;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->getTimestamp();
	}

	/**
	 * Calculate the difference between two ConvertibleTimestamp objects.
	 *
	 * @param ConvertibleTimestamp $relativeTo Base time to calculate difference from
	 * @return DateInterval|bool The DateInterval object representing the
	 *   difference between the two dates or false on failure
	 */
	public function diff( ConvertibleTimestamp $relativeTo ) {
		return $this->timestamp->diff( $relativeTo->timestamp );
	}

	/**
	 * Set the timezone of this timestamp to the specified timezone.
	 *
	 * @param string $timezone Timezone to set
	 * @throws TimestampException
	 */
	public function setTimezone( $timezone ) {
		try {
			$this->timestamp->setTimezone( new DateTimeZone( $timezone ) );
		} catch ( Exception $e ) {
			throw new TimestampException( __METHOD__ . ': Invalid timezone.', $e->getCode(), $e );
		}
	}

	/**
	 * Get the timezone of this timestamp.
	 *
	 * @return DateTimeZone The timezone
	 */
	public function getTimezone() {
		return $this->timestamp->getTimezone();
	}

	/**
	 * Format the timestamp in a given format.
	 *
	 * @param string $format Pattern to format in
	 * @return string The formatted timestamp
	 */
	public function format( $format ) {
		return $this->timestamp->format( $format );
	}
}
