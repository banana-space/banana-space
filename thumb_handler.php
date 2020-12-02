<?php
/**
 * The web entry point to be used as 404 handler behind a web server rewrite
 * rule for media thumbnails, internally handled via thumb.php.
 *
 * This script will interpret a request URL like
 * `/w/images/thumb/a/a9/Example.jpg/50px-Example.jpg` and treat it as
 * if it was a request to thumb.php with the relevant query parameters filled
 * out. See also $wgGenerateThumbnailOnParse.
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
 * @ingroup entrypoint
 * @ingroup Media
 */

define( 'THUMB_HANDLER', true );
define( 'MW_ENTRY_POINT', 'thumb_handler' );

// Execute thumb.php, having set THUMB_HANDLER so that
// it knows to extract params from a thumbnail file URL.
require __DIR__ . '/thumb.php';
