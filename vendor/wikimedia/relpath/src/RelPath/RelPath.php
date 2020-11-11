<?php
/**
 * Copyright (c) 2015 Ori Livneh <ori@wikimedia.org>
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
 * @author Ori Livneh <ori@wikimedia.org>
 */

namespace RelPath;

use Wikimedia\RelPath;

/**
 * Split a path into path components.
 *
 * @param string $path File path.
 * @return array Array of path components.
 */
function splitPath( $path ) {
	return RelPath::splitPath( $path );
}

/**
 * Return a relative filepath to path either from the current directory or from
 * an optional start directory. Both paths must be absolute.
 *
 * @param string $path File path.
 * @param string $start Start directory. Optional; if not specified, the current
 *  working directory will be used.
 * @return string|bool Relative path, or false if input was invalid.
 */
function getRelativePath( $path, $start = null ) {
	return RelPath::getRelativePath( $path, $start );
}

/**
 * Join path components.
 *
 * @param string $base Base path.
 * @param string $path File path to join to base path.
 * @return string
 */
function joinPath( $base, $path ) {
	return RelPath::joinPath( $base, $path );
}
