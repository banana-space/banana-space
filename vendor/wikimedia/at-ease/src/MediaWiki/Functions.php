<?php
/**
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
 */

namespace MediaWiki;

/**
 * Reference-counted warning suppression
 *
 * @param bool $end Whether to restore warnings
 */
function suppressWarnings( $end = false ) {
	\Wikimedia\suppressWarnings( $end );
}

/**
 * Restore error level to previous value
 */
function restoreWarnings() {
	\Wikimedia\suppressWarnings( true );
}

/**
 * Call the callback given by the first parameter, suppressing any warnings.
 *
 * @param callable $callback Function to call
 * @return mixed
 */
function quietCall( callable $callback /*, parameters... */ ) {
	$args = array_slice( func_get_args(), 1 );
	suppressWarnings();
	$rv = call_user_func_array( $callback, $args );
	restoreWarnings();
	return $rv;
}
