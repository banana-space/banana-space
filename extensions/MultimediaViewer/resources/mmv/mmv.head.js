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

( function ( mw, $ ) {
	var $document = $( document ),
		start;

	if ( !mw.mmv.isBrowserSupported() ) {
		return;
	}

	// If the user disabled MediaViewer in his preferences, we do not set up click handling.
	// This is loaded before user JS so we cannot check wgMediaViewer.
	if (
		mw.config.get( 'wgMediaViewerOnClick' ) !== true ||
		mw.user.isAnon() && mw.storage.get( 'wgMediaViewerOnClick', '1' ) !== '1'
	) {
		return;
	}

	$document.on( 'click.mmv-head', 'a.image', function ( e ) {
		// Do not interfere with non-left clicks or if modifier keys are pressed.
		// Also, make sure we do not get in a loop.
		if ( ( e.button !== 0 && e.which !== 1 ) || e.altKey || e.ctrlKey || e.shiftKey || e.metaKey || e.replayed ) {
			return;
		}

		start = $.now();

		// We wait for document readiness because mw.loader.using writes to the DOM
		// which can cause a blank page if it happens before DOM readiness
		$( function () {
			mw.loader.using( [ 'mmv.bootstrap.autostart' ], function () {
				mw.mmv.bootstrap.whenThumbsReady().then( function () {
					mw.mmv.durationLogger.stop( 'early-click-to-replay-click', start ).record( 'early-click-to-replay-click' );

					// We have to copy the properties, passing e doesn't work. Probably because of preventDefault()
					$( e.target ).trigger( { type: 'click', which: 1, replayed: true } );
				} );
			} );
		} );

		e.preventDefault();
	} );
}( mediaWiki, jQuery ) );
