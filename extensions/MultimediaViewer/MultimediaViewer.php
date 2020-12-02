<?php
/**
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author Mark Holmquist <mtraceur@member.fsf.org>
 * @copyright Copyright © 2013, Mark Holmquist
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'MultimediaViewer' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['MultimediaViewer'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for MultimediaViewer extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the MultimediaViewer extension requires MediaWiki 1.25+' );
}

// The following is for the purposes of IDEs and documentation. It is not
// executed.

/**
 * If set, records image load network performance via
 * EventLogging once per this many requests. False if unset.
 *
 * @var int|bool
 */
$wgMediaViewerNetworkPerformanceSamplingFactor = false;

/**
 * If set, records loading times via EventLogging. A value of 1000 means there will be an
 * 1:1000 chance to log the duration event.
 * False if unset.
 * @var int|bool
 */
$wgMediaViewerDurationLoggingSamplingFactor = false;

/**
 * If set, records loading times via EventLogging with factor specific to loggedin users.
 * A value of 1000 means there will be an 1:1000 chance to log the duration event.
 * False if unset.
 * @var int|bool
 */
$wgMediaViewerDurationLoggingLoggedinSamplingFactor = false;

/**
 * If set, records whether image attribution data was available.
 * A value of 1000 means there will be an 1:1000 chance to log the attribution event.
 * False if unset.
 * @var int|bool
 */
$wgMediaViewerAttributionLoggingSamplingFactor = false;

/**
 * If set, records whether image dimension data was available.
 * A value of 1000 means there will be an 1:1000 chance to log the dimension event.
 * False if unset.
 * @var int|bool
 */
$wgMediaViewerDimensionLoggingSamplingFactor = false;

/**
 * If set, records user actions via EventLogging and applies a sampling factor according
 * to the map. A "default" key in the map must be set.
 * False if unset.
 * @var array|bool
 */
$wgMediaViewerActionLoggingSamplingFactorMap = false;

/**
 * When this is enabled, MediaViewer will try to guess image URLs instead of making an
 * imageinfo API to get them from the server. This speeds up image loading, but will
 * result in 404s when $wgGenerateThumbnailOnParse (so the thumbnails are only generated
 * as a result of the API request). MediaViewer will catch such 404 errors and fall back
 * to the API request, but depending on how the site is set up, the 404 might get cached,
 * or redirected, causing the image load to fail. The safe way to use URL guessing is
 * with a 404 handler: https://www.mediawiki.org/wiki/Manual:Thumb.php#404_Handler
 *
 * @var bool
 */
$wgMediaViewerUseThumbnailGuessing = false;

/**
 * If true, Media Viewer will be turned on by default.
 * @var bool
 */
$wgMediaViewerEnableByDefault = true;

/**
 * Overrides $wgMediaViewerEnableByDefault for anonymous users. If
 * set to null, will fall back to value of $wgMediaViewerEnableByDefault
 * @var bool|null
 */
$wgMediaViewerEnableByDefaultForAnonymous = null;

/**
 * If set, adds a query parameter to image requests made by Media Viewer
 * @var string|bool
 */
$wgMediaViewerImageQueryParameter = false;

/**
 * If set, records a virtual view via the provided beacon URI.
 * @var string|bool
 */
$wgMediaViewerRecordVirtualViewBeaconURI = false;
