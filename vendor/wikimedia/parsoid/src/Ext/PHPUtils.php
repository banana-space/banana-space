<?php

namespace Wikimedia\Parsoid\Ext;

/**
 * This class contains sundry helpers unrelated to core Parsoid
 */
class PHPUtils {
	/**
	 * Convert an array to an object. Workaround for https://bugs.php.net/bug.php?id=78379
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
		return (object)array_combine( array_keys( $array ), array_values( $array ) );
	}

	/**
	 * json_encode wrapper function
	 * - unscapes slashes and unicode
	 *
	 * @param mixed $o
	 * @return string
	 */
	public static function jsonEncode( $o ): string {
		return \Wikimedia\Parsoid\Utils\PHPUtils::jsonEncode( $o );
	}
}
