<?php

namespace Flow\Data\Utils;

/**
 * This assists in performing client-side 1-to-1 joins. It collects the foreign key
 * from a multi-dimensional array, queries a callable for the foreign key values and
 * then returns the source data with related data merged in.
 */
class Merger {

	/**
	 * @param array[] $source input two dimensional array
	 * @param string $fromKey Key in nested arrays of $source containing foreign key
	 * @param callable $callable Callable receiving array of foreign keys returning map
	 *  from foreign key to its value
	 * @param string|null $name Name to merge loaded foreign data as. If null uses $fromKey.
	 * @param string $default Value to use when no matching foreign value can be located
	 * @return array $source array with all found foreign key values merged
	 */
	public static function merge( array $source, $fromKey, $callable, $name = null, $default = '' ) {
		if ( $name === null ) {
			$name = $fromKey;
		}
		$ids = [];
		foreach ( $source as $row ) {
			$id = $row[$fromKey];
			if ( $id !== null ) {
				$ids[] = $id;
			}
		}
		if ( !$ids ) {
			return $source;
		}
		$res = $callable( $ids );
		if ( $res === false ) {
			return [];
		}
		foreach ( $source as $idx => $row ) {
			$id = $row[$fromKey];
			if ( $id === null ) {
				continue;
			}
			$source[$idx][$name] = $res[$id] ?? $default;
		}
		return $source;
	}

	/**
	 * Same as self::merge, but for 3-dimensional source arrays
	 *
	 * @param array $multiSource input three dimensonal array
	 * @param string $fromKey
	 * @param callable $callable Callable receiving array of foreign keys returning map
	 *  from foreign key to its value
	 * @param string|null $name Name to merge loaded foreign data as. If null uses $fromKey.
	 * @param string $default Value to use when no matching foreign value can be located
	 * @return array $multiSource array with all found foreign key values merged
	 */
	public static function mergeMulti( array $multiSource, $fromKey, $callable, $name = null, $default = '' ) {
		if ( $name === null ) {
			$name = $fromKey;
		}
		$ids = [];
		foreach ( $multiSource as $source ) {
			if ( $source === null ) {
				continue;
			}
			foreach ( $source as $row ) {
				$id = $row[$fromKey];
				if ( $id !== null ) {
					$ids[] = $id;
				}
			}
		}
		if ( !$ids ) {
			return $multiSource;
		}
		$res = $callable( array_unique( $ids ) );
		if ( $res === false ) {
			return [];
		}
		foreach ( $multiSource as $i => $source ) {
			if ( $source === null ) {
				continue;
			}
			foreach ( $source as $j => $row ) {
				$id = $row[$fromKey];
				if ( $id === null ) {
					continue;
				}
				$multiSource[$i][$j][$name] = $res[$id] ?? $default;
			}
		}
		return $multiSource;
	}
}
