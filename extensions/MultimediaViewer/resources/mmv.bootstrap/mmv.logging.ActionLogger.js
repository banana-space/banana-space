/*
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
 */

( function () {
	var L;

	/**
	 * Writes log entries
	 *
	 * @class mw.mmv.logging.ActionLogger
	 * @extends mw.mmv.logging.Logger
	 * @constructor
	 */
	function ActionLogger() {}

	OO.inheritClass( ActionLogger, mw.mmv.logging.Logger );

	L = ActionLogger.prototype;

	/**
	 * Sampling factor key-value map.
	 *
	 * The map's keys are the action identifiers and the values are the sampling factor for each action type.
	 * There is a "default" key defined providing a default sampling factor for actions that aren't explicitly
	 * set in the map.
	 *
	 * @property {Object.<string, number>}
	 * @static
	 */
	L.samplingFactorMap = mw.config.get( 'wgMultimediaViewer' ).actionLoggingSamplingFactorMap;

	/**
	 * @override
	 * @inheritdoc
	 */
	L.schema = 'MediaViewer';

	/**
	 * Possible log actions, and their associated English developer log strings.
	 *
	 * These events are not de-duped. Eg. if the user opens the same site link
	 * in 10 tabs, there will be 10 file-description-page events. If they view the
	 * same image 10 times by hitting the prev/next buttons, there will be 10
	 * image-view events, etc.
	 *
	 * @property {Object}
	 * @static
	 */
	L.logActions = {
		thumbnail: 'User clicked on a thumbnail to open Media Viewer.',
		enlarge: 'User clicked on an enlarge link to open Media Viewer.',
		fullscreen: 'User entered fullscreen mode.',
		defullscreen: 'User exited fullscreen mode.',
		close: 'User closed Media Viewer.',
		'view-original-file': 'User clicked on the direct link to the original file',
		'file-description-page': 'User opened the file description page.',
		'file-description-page-abovefold': 'User opened the file description page via the above-the-fold button.',
		'use-this-file-open': 'User opened the dialog to use this file.',
		'image-view': 'User viewed an image.',
		'metadata-open': 'User opened the metadata panel.',
		'metadata-close': 'User closed the metadata panel.',
		'metadata-scroll-open': 'User opened the metadata panel by scrolling.',
		'metadata-scroll-close': 'User closed the metadata panel by scrolling.',
		'next-image': 'User viewed the next image.',
		'prev-image': 'User viewed the previous image.',
		'terms-open': 'User opened the usage terms.',
		'license-page': 'User opened the license page.',
		'author-page': 'User opened the author page.',
		'source-page': 'User opened the source page.',
		'hash-load': 'User loaded the image via a hash on pageload.',
		'history-navigation': 'User navigated with the browser history.',
		'optout-loggedin': 'opt-out (via quick link at bottom of metadata panel) by logged-in user',
		'optout-anon': 'opt-out by anonymous user',
		'optin-loggedin': 'opt-in (via quick link at bottom of metadata panel) by logged-in user',
		'optin-anon': 'opt-in by anonymous user',
		'about-page': 'User opened the about page.',
		'discuss-page': 'User opened the discuss page.',
		'help-page': 'User opened the help page.',
		'location-page': 'User opened the location page.',
		'download-select-menu-original': 'User selected the original size in the download dropdown menu.',
		'download-select-menu-small': 'User selected the small size in the download dropdown menu.',
		'download-select-menu-medium': 'User selected the medium size in the download dropdown menu.',
		'download-select-menu-large': 'User selected the large size in the download dropdown menu.',
		download: 'User clicked on the button to download a file.',
		'download-view-in-browser': 'User clicked on the link to view the image in the browser in the download tab.',
		'right-click-image': 'User right-clicked on the image.',
		'share-page': 'User opened the link to the current image.',
		'share-link-copied': 'User copied the share link.',
		'embed-html-copied': 'User copied the HTML embed code.',
		'embed-wikitext-copied': 'User copied the wikitext embed code.',
		'embed-switched-to-html': 'User switched to the HTML embed code.',
		'embed-switched-to-wikitext': 'User switched to the wikitext embed code.',
		'embed-select-menu-wikitext-default': 'User switched to the default thumbnail size on wikitext.',
		'embed-select-menu-wikitext-small': 'User switched to the small thumbnail size on wikitext.',
		'embed-select-menu-wikitext-medium': 'User switched to the medium thumbnail size on wikitext.',
		'embed-select-menu-wikitext-large': 'User switched to the large thumbnail size on wikitext.',
		'embed-select-menu-html-original': 'User switched to the original thumbnail size on html.',
		'embed-select-menu-html-small': 'User switched to the small thumbnail size on html.',
		'embed-select-menu-html-medium': 'User switched to the medium thumbnail size on html.',
		'embed-select-menu-html-large': 'User switched to the large thumbnail size on html.',
		'use-this-file-close': 'User closed the dialog to use this file.',
		'download-open': 'User opened the dialog to download this file.',
		'download-close': 'User closed the dialog to download this file.',
		'options-open': 'User opened the enable/disable dialog.',
		'options-close': 'User either canceled an enable/disable action or closed a confirmation window.',
		'disable-about-link': 'User clicked on the "Learn more" link in the disable window.',
		'enable-about-link': 'User clicked on the "Learn more" link in the enable window.',
		'image-unview': 'User stopped looking at the current image.'
	};

	/**
	 * Logs an action
	 *
	 * @param {string} action The key representing the action
	 * @param {boolean} forceEventLog True if we want the action to be logged regardless of the sampling factor
	 * @return {jQuery.Promise}
	 */
	L.log = function ( action, forceEventLog ) {
		var actionText = this.logActions[ action ] || action,
			self = this;

		if ( this.isEnabled( action ) ) {
			mw.log( actionText );
		}

		if ( forceEventLog || self.isInSample( action ) ) {
			return this.loadDependencies().then( function () {
				self.eventLog.logEvent( self.schema, {
					action: action,
					samplingFactor: self.getActionFactor( action )
				} );

				return true;
			} );
		} else {
			return $.Deferred().resolve( false );
		}
	};

	/**
	 * Returns the sampling factor for a given action
	 *
	 * @param {string} action The key representing the action
	 * @return {number} Sampling factor
	 */
	L.getActionFactor = function ( action ) {
		return this.samplingFactorMap[ action ] || this.samplingFactorMap.default;
	};

	/**
	 * Returns whether or not we should measure this request for this action
	 *
	 * @param {string} action The key representing the action
	 * @return {boolean} True if this request needs to be sampled
	 */
	L.isInSample = function ( action ) {
		var factor = this.getActionFactor( action );

		if ( typeof factor !== 'number' || factor < 1 ) {
			return false;
		}
		return Math.floor( Math.random() * factor ) === 0;
	};

	/**
	 * Returns whether logging this event is enabled. This is intended for console logging, which
	 * (in debug mode) should be done even if the request is not being sampled, as long as logging
	 * is enabled for some sample.
	 *
	 * @param {string} action The key representing the action
	 * @return {boolean} True if this logging is enabled
	 */
	L.isEnabled = function ( action ) {
		var factor = this.getActionFactor( action );
		return typeof factor === 'number' && factor >= 1;
	};

	mw.mmv.logging.ActionLogger = ActionLogger;
	mw.mmv.actionLogger = new ActionLogger();
}() );
