<?php

namespace Flow\Data\Utils;

/**
 * Value class wraps sql to be passed into queries.  Values
 * that are not wrapped in the RawSql class are escaped to
 * plain strings.
 */
class RawSql {
	protected $sql;

	public function __construct( $sql ) {
		$this->sql = $sql;
	}

	public function getSQL( $db ) {
		if ( is_callable( $this->sql ) ) {
			return ( $this->sql )( $db );
		}

		return $this->sql;
	}
}
