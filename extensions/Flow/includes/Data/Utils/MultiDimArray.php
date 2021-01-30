<?php

namespace Flow\Data\Utils;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

/**
 * This object can be used to easily set keys in a multi-dimensional array.
 *
 * Usage:
 *
 *   $arr = new Flow\Data\MultiDimArray;
 *   $arr[array(1,2,3)] = 4;
 *   $arr[array(2,3,4)] = 5;
 *   var_export( $arr->all() );
 *
 *   array (
 *     1 => array (
 *       2 => array (
 *         3 => 4,
 *       ),
 *     ),
 *     2 => array (
 *       3 => array (
 *         4 => 5,
 *       ),
 *     ),
 *   )
 */
class MultiDimArray implements \ArrayAccess {
	protected $data = [];

	public function all() {
		return $this->data;
	}

	/**
	 * Probably not what you want.  primary key value is lost, you only
	 * receive the final key in a composite key set.
	 * @return RecursiveIteratorIterator
	 */
	public function getIterator() {
		$it = new RecursiveArrayIterator( $this->data );
		return new RecursiveIteratorIterator( $it );
	}

	public function offsetSet( $offset, $value ) {
		$data =& $this->data;
		foreach ( (array)$offset as $key ) {
			if ( !isset( $data[$key] ) ) {
				$data[$key] = [];
			}
			$data =& $data[$key];
		}
		$data = $value;
	}

	public function offsetGet( $offset ) {
		$data =& $this->data;
		foreach ( (array)$offset as $key ) {
			if ( !isset( $data[$key] ) ) {
				throw new \OutOfBoundsException( 'Does not exist' );
			} elseif ( !is_array( $data ) ) {
				throw new \OutOfBoundsException( "Requested offset {$key} (full offset " . implode( ':', $offset ) .
					"), but $data is not an array." );
			}
			$data =& $data[$key];
		}
		return $data;
	}

	public function offsetUnset( $offset ) {
		$offset = (array)$offset;
		// while loop is required to not leave behind empty arrays
		$first = true;
		while ( $offset ) {
			$end = array_pop( $offset );
			$data =& $this->data;
			foreach ( $offset as $key ) {
				if ( !isset( $data[$key] ) ) {
					return;
				}
				$data =& $data[$key];
			}
			if ( $first === true || ( is_array( $data[$end] ) && !count( $data[$end] ) ) ) {
				unset( $data[$end] );
				$first = false;
			}
		}
	}

	public function offsetExists( $offset ) {
		$data =& $this->data;
		foreach ( (array)$offset as $key ) {
			if ( !isset( $data[$key] ) ) {
				return false;
			} elseif ( !is_array( $data ) ) {
				throw new \OutOfBoundsException( "Requested offset {$key} (full offset " . implode( ':', $offset ) .
					"), but $data is not an array." );
			}
			$data =& $data[$key];
		}
		return true;
	}
}
