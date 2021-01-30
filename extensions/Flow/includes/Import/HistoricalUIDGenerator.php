<?php

namespace Flow\Import;

use MWCryptRand;
use RuntimeException;

/**
 * Modified version of UIDGenerator generates historical timestamped
 * uid's for use when importing older data.
 *
 * DO NOT USE for normal UID generation, this is likely to run into
 * id collisions.
 *
 * The import process needs to identify collision failures reported by
 * the database and re-try importing that item with another generated
 * uid.
 */
class HistoricalUIDGenerator {
	private const COUNTER_MAX = 1023; // 2^10 - 1

	public static function historicalTimestampedUID88( $timestamp, $base = 10 ) {
		static $counter = false;
		if ( $counter === false ) {
			$counter = mt_rand( 0, self::COUNTER_MAX );
		}

		// (seconds, milliseconds)
		$time = [
			wfTimestamp( TS_UNIX, $timestamp ),
			mt_rand( 0, 999 )
		];
		++$counter;

		// Take the 46 LSBs of "milliseconds since epoch"
		$id_bin = self::millisecondsSinceEpochBinary( $time );
		// Add a 10 bit counter resulting in 56 bits total
		$id_bin .= str_pad( decbin( $counter % ( self::COUNTER_MAX + 1 ) ), 10, '0', STR_PAD_LEFT );
		// Add the 32 bit node ID resulting in 88 bits total
		$id_bin .= self::newNodeId();
		if ( strlen( $id_bin ) !== 88 ) {
			throw new RuntimeException( "Detected overflow for millisecond timestamp." );
		}

		return \Wikimedia\base_convert( $id_bin, 2, $base );
	}

	/**
	 * @param array $time Array of second and millisecond integers
	 * @return string 46 LSBs of "milliseconds since epoch" in binary (rolls over in 4201)
	 * @throws RuntimeException
	 */
	protected static function millisecondsSinceEpochBinary( array $time ) {
		list( $sec, $msec ) = $time;
		$ts = 1000 * $sec + $msec;
		if ( $ts > 2 ** 52 ) {
			throw new RuntimeException(
				__METHOD__ . ': sorry, this function doesn\'t work after the year 144680'
			);
		}

		return substr( \Wikimedia\base_convert( (string)$ts, 10, 2, 46 ), -46 );
	}

	/**
	 * Rotate the nodeId to a random one. The stable node is best for
	 * generating "now" uid's on a cluster of servers, but repeated
	 * creation of historical uid's with one or a smaller number of
	 * machines requires use of a random node id.
	 *
	 * @return string String of 32 binary digits
	 */
	protected static function newNodeId() {
		// 4 bytes = 32 bits

		return \Wikimedia\base_convert( MWCryptRand::generateHex( 8 ), 16, 2, 32 );
	}
}
