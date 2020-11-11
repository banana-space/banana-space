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
	var VL;

	/**
	 * Tracks how long users are viewing images for
	 *
	 * @class mw.mmv.logging.ViewLogger
	 * @extends mw.Api
	 * @constructor
	 * @param {mw.mmv.Config} config mw.mmv.Config object
	 * @param {Object} windowObject Browser window object
	 * @param {mw.mmv.logging.ActionLogger} actionLogger ActionLogger object
	 */
	function ViewLogger( config, windowObject, actionLogger ) {
		/**
		 * Was the last image view logged or was logging skipped?
		 * @property {boolean}
		 */
		this.wasLastViewLogged = false;

		/**
		 * Record when the user started looking at the current image
		 * @property {number}
		 */
		this.viewStartTime = 0;

		/**
		 * How long the user has been looking at the current image
		 * @property {number}
		 */
		this.viewDuration = 0;

		/**
		 * The image URL to record a virtual view for
		 * @property {string}
		 */
		this.url = '';

		/**
		 * If set, URI to send the beacon request to in order to record the virtual view
		 * @property {string}
		 */
		this.recordVirtualViewBeaconURI = config.recordVirtualViewBeaconURI();

		/**
		 * Browser window
		 * @property {Object}
		 */
		this.window = windowObject;

		/**
		 * Action logger
		 * @property {mw.mmv.logging.ActionLogger}
		 */
		this.actionLogger = actionLogger;
	}

	VL = ViewLogger.prototype;

	/**
	 * Tracks the unview event of the current image if appropriate
	 */
	VL.unview = function () {
		if ( !this.wasLastViewLogged ) {
			return;
		}

		this.wasLastViewLogged = false;
		this.actionLogger.log( 'image-unview', true );
	};

	/**
	 * Starts recording a viewing window for the current image
	 */
	VL.startViewDuration = function () {
		this.viewStartTime = $.now();
	};

	/**
	 * Stops recording the viewing window for the current image
	 */
	VL.stopViewDuration = function () {
		if ( this.viewStartTime ) {
			this.viewDuration += $.now() - this.viewStartTime;
			this.viewStartTime = 0;
		}
	};

	/**
	 * Records the amount of time the current image has been viewed
	 */
	VL.recordViewDuration = function () {
		var uri;

		this.stopViewDuration();

		if ( this.recordVirtualViewBeaconURI ) {
			uri = new mw.Uri( this.recordVirtualViewBeaconURI );
			uri.extend( { duration: this.viewDuration,
				uri: this.url } );

			try {
				navigator.sendBeacon( uri.toString() );
			} catch ( e ) {
				$.ajax( {
					type: 'HEAD',
					url: uri.toString()
				} );
			}

			mw.log( 'Image has been viewed for ', this.viewDuration );
		}

		this.viewDuration = 0;

		this.unview();
	};

	/**
	 * Sets up the view tracking for the current image
	 *
	 * @param {string} url URL of the image to record a virtual view for
	 */
	VL.attach = function ( url ) {
		var view = this;

		this.url = url;
		this.startViewDuration();

		$( this.window )
			.off( '.mmv-view-logger' )
			.on( 'beforeunload.mmv-view-logger', function () {
				view.recordViewDuration();
			} )
			.on( 'focus.mmv-view-logger', function () {
				view.startViewDuration();
			} )
			.on( 'blur.mmv-view-logger', function () {
				view.stopViewDuration();
			} );
	};

	/*
	 * Stops listening to events
	 */
	VL.unattach = function () {
		$( this.window ).off( '.mmv-view-logger' );
		this.stopViewDuration();
	};

	/**
	 * Tracks whether or not the image view event was logged or not (i.e. was it in the logging sample)
	 *
	 * @param {boolean} wasEventLogged Whether the image view event was logged
	 */
	VL.setLastViewLogged = function ( wasEventLogged ) {
		this.wasLastViewLogged = wasEventLogged;
	};

	mw.mmv.logging.ViewLogger = ViewLogger;
}( mediaWiki, jQuery ) );
