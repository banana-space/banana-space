<?php
/**
 * Copyright (c) 2016 Timo Tijhof <krinklemail@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @file
 */

namespace Wikimedia;

class WrappedStringList {
	/** @var string */
	protected $sep;

	/** @var WrappedString[] */
	protected $wraps;

	/**
	 * @param string $sep
	 * @param WrappedString[] $wraps
	 */
	public function __construct( $sep, array $wraps ) {
		$this->sep = $sep;
		$this->wraps = $wraps;
	}

	/**
	 * @param WrappedString[] $wraps
	 * @return WrappedStringList Combined list
	 */
	protected function extend( array $wraps ) {
		$list = clone $this;
		$list->wraps = array_merge( $list->wraps, $wraps );
		return $list;
	}

	/**
	 * Merge lists with the same separator.
	 *
	 * Does not modify the given array or any of the objects in it.
	 *
	 * @param array $lists Array of strings and/or WrappedStringList objects
	 * @param string $outerSep
	 * @return string[] Compacted list
	 */
	protected static function compact( array $lists, $outerSep ) {
		$consolidated = [];
		$prev = current( $lists );
		// Wrap single WrappedString objects in a list for easier merging
		if ( $prev instanceof WrappedString ) {
			$prev = new WrappedStringList( $outerSep, [ $prev ] );
		}
		while ( next( $lists ) !== false ) {
			$curr = current( $lists );
			if ( $curr instanceof WrappedString ) {
				$curr = new WrappedStringList( $outerSep, [ $curr ] );
			}
			if ( $prev instanceof WrappedStringList ) {
				if ( $curr instanceof WrappedStringList
					&& $prev->sep === $curr->sep
				) {
					// Merge into previous list, and keep looking.
					$prev = $prev->extend( $curr->wraps );
				} else {
					// Current list not mergeable. Commit previous one.
					$prev = implode( $prev->sep, WrappedString::compact( $prev->wraps ) );
					$consolidated[] = $prev;
					$prev = $curr;
				}
			} else {
				// Commit previous one
				$consolidated[] = $prev;
				$prev = $curr;
			}
		}

		// Commit last one
		if ( $prev instanceof WrappedStringList ) {
			$consolidated[] = implode( $prev->sep, WrappedString::compact( $prev->wraps ) );
		} else {
			$consolidated[] = $prev;
		}

		return $consolidated;
	}

	/**
	 * Join a several wrapped strings with a separator between each.
	 *
	 * @param string $sep
	 * @param array $lists Array of strings and/or WrappedStringList objects
	 * @return string
	 */
	public static function join( $sep, array $lists ) {
		return implode( $sep, self::compact( $lists, $sep ) );
	}

	/** @return string */
	public function __toString() {
		return self::join( $this->sep, [ $this ] );
	}
}
