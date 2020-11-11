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

	/**
	 * Loads an image.
	 *
	 * @class mw.mmv.provider.Image
	 * @constructor
	 * @param {string} imageQueryParameter When defined, is a query parameter to add to every image request
	 */
	function Image( imageQueryParameter ) {
		/**
		 * @property {mw.mmv.logging.PerformanceLogger}
		 * @private
		 */
		this.performance = new mw.mmv.logging.PerformanceLogger();

		this.imageQueryParameter = imageQueryParameter;

		/**
		 * AJAX call cache.
		 * @property {Object.<string, jQuery.Promise>} cache
		 * @protected
		 */
		this.cache = {};
	}

	/**
	 * Loads an image and returns it. Includes performance metrics via mw.mmv.logging.PerformanceLogger.
	 * When the browser supports it, the image is loaded as an AJAX request.
	 *
	 * @param {string} url
	 * @param {jQuery.Deferred.<string>} extraStatsDeferred A promise which resolves to extra statistics.
	 * @return {jQuery.Promise.<HTMLImageElement>} A promise which resolves to the image object.
	 *  When loaded via AJAX, it has progress events, which return an array with the content loaded
	 *  so far and with the progress as a floating-point number between 0 and 100.
	 */
	Image.prototype.get = function ( url, extraStatsDeferred ) {
		var provider = this,
			cacheKey = url,
			extraParam = {},
			start,
			rawGet,
			uri;

		if ( this.imageQueryParameter ) {
			uri = new mw.Uri( url );
			extraParam[ this.imageQueryParameter ] = null;
			url = uri.extend( extraParam ).toString();
		}

		if ( !this.cache[ cacheKey ] ) {
			if ( this.imagePreloadingSupported() ) {
				rawGet = $.proxy( provider.rawGet, provider, url, true );
				this.cache[ cacheKey ] = this.performance.record( 'image', url, extraStatsDeferred ).then( rawGet, rawGet );
			} else {
				start = $.now();
				this.cache[ cacheKey ] = this.rawGet( url );
				this.cache[ cacheKey ].always( function () {
					provider.performance.recordEntry( 'image', $.now() - start, url, undefined, extraStatsDeferred );
				} );
			}
			this.cache[ cacheKey ].fail( function ( error ) {
				mw.log( provider.constructor.name + ' provider failed to load: ', error );
			} );
		}

		return this.cache[ cacheKey ];
	};

	/**
	 * Internal version of get(): no caching, no performance metrics.
	 *
	 * @param {string} url
	 * @param {boolean} [cors] if true, use CORS for preloading
	 * @return {jQuery.Promise.<HTMLImageElement>} a promise which resolves to the image object
	 */
	Image.prototype.rawGet = function ( url, cors ) {
		var img = new window.Image(),
			deferred = $.Deferred();

		// This attribute is necessary in Firefox, which needs it for the image request after
		// the XHR to hit the cache by being a proper CORS request. In IE11, however,
		// the presence of that attribute would cause the second image request to miss the cache,
		// because IE11 adds a no-cache request header to image CORS requests. As a result,
		// we call needsCrossOrigin to check if the current browser needs to set the attribute
		// or not in order to avoid loading the image twice.
		if ( cors && this.needsCrossOrigin() ) {
			img.crossOrigin = 'anonymous';
		}

		img.onload = function () {
			deferred.resolve( img );
		};
		img.onerror = function () {
			deferred.reject( 'could not load image from ' + url );
		};

		img.src = url;

		return deferred;
	};

	/**
	 * Checks whether the current browser supports AJAX preloading of images.
	 * This means that:
	 * - the browser supports CORS requests (large wiki farms usually host images on a
	 *   separate domain) and
	 * - either AJAX and normal image loading uses the same cache (when an image is used by a CORS
	 *   request, and then normally by setting img.src, it is only loaded once)
	 * - or (as is the case with Firefox) they are cached separately, but that can be changed by
	 *   setting the crossOrigin attribute
	 *
	 * @return {boolean}
	 */
	Image.prototype.imagePreloadingSupported = function () {
		// This checks if the browser supports CORS requests in XHRs
		return window.XMLHttpRequest !== undefined && 'withCredentials' in new XMLHttpRequest();
	};

	/**
	 * Checks whether the current browser needs to set crossOrigin on images to avoid
	 * doing a double load
	 *
	 * @return {boolean} Browser needs to set crossOrigin
	 */
	Image.prototype.needsCrossOrigin = function () {
		// Support: IE11
		// This check is essentially "is this browser anything but IE > 10?".
		// I couldn't find something more topical because IE11 does support the crossOrigin
		// attribute, just in a counter-productive way compared to all the other browsers
		// who also support it.
		return window.MSInputMethodContext === undefined;
	};

	mw.mmv.provider.Image = Image;
}( mediaWiki, jQuery ) );
