<?php

namespace Flow\Data\Utils;

/**
 * Performs the equivalent of an SQL ORDER BY c1 ASC, c2 ASC...
 * Always sorts in ascending order.  array_reverse to get all descending.
 * For varied asc/desc needs implementation changes.
 *
 * usage: usort( $array, new SortArrayByKeys( array( 'c1', 'c2' ) ) );
 */
class SortArrayByKeys {
	protected $keys;
	protected $strict;

	public function __construct( array $keys, $strict = false ) {
		$this->keys = $keys;
		$this->strict = $strict;
	}

	public function __invoke( $a, $b ) {
		return self::compare( $a, $b, $this->keys, $this->strict );
	}

	public static function compare( $a, $b, array $keys, $strict = false ) {
		$key = array_shift( $keys );
		if ( !isset( $a[$key] ) ) {
			return isset( $b[$key] ) ? -1 : 0;
		} elseif ( !isset( $b[$key] ) ) {
			return 1;
		} elseif ( $strict ? $a[$key] === $b[$key] : $a[$key] == $b[$key] ) {
			return $keys ? self::compare( $a, $b, $keys, $strict ) : 0;
		} else { // is there such a thing as strict gt/lt ?
			return $a[$key] <=> $b[$key];
		}
	}
}
