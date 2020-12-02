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
	/**
	 * Base class for API-based data providers.
	 *
	 * @class mw.mmv.provider.Api
	 * @abstract
	 * @constructor
	 * @param {mw.Api} api
	 * @param {Object} [options]
	 * @cfg {number} [maxage] cache expiration time, in seconds
	 *  Will be used for both client-side cache (maxage) and reverse proxies (s-maxage)
	 */
	function Api( api, options ) {
		/**
		 * API object for dependency injection.
		 *
		 * @property {mw.Api}
		 */
		this.api = api;

		/**
		 * Options object; the exact format and meaning is unspecified and could be different
		 * from subclass to subclass.
		 *
		 * @property {Object}
		 */
		this.options = options || {};

		/**
		 * API call cache.
		 *
		 * @property {Object.<string, jQuery.Promise>} cache
		 * @protected
		 */
		this.cache = {};
	}

	/**
	 * Wraps a caching layer around a function returning a promise; if getCachedPromise has been
	 * called with the same key already, it will return the previous result.
	 *
	 * Since it is the promise and not the API response that gets cached, this method can ensure
	 * that there are no race conditions and multiple calls to the same resource: even if the
	 * request is still in progress, separate calls (with the same key) to getCachedPromise will
	 * share on the same promise object.
	 * The promise is cached even if it is rejected, so if the API request fails, all later calls
	 * to getCachedPromise will fail immediately without retrying the request.
	 *
	 * @param {string} key cache key
	 * @param {function(): jQuery.Promise} getPromise a function to get the promise on cache miss
	 * @return {jQuery.Promise}
	 */
	Api.prototype.getCachedPromise = function ( key, getPromise ) {
		var provider = this;

		if ( !this.cache[ key ] ) {
			this.cache[ key ] = getPromise();
			this.cache[ key ].fail( function ( error ) {
				// constructor.name is usually not reliable in inherited classes, but OOjs fixes that
				mw.log( provider.constructor.name + ' provider failed to load: ', error );
			} );
		}
		return this.cache[ key ];
	};

	/**
	 * Calls mw.Api.get, with caching parameters.
	 *
	 * @param {Object} params Parameters to the API query.
	 * @param {Object} [ajaxOptions] ajaxOptions argument for mw.Api.get
	 * @param {number|null} [maxage] Cache the call for this many seconds.
	 *  Sets both the maxage (client-side) and smaxage (proxy-side) caching parameters.
	 *  Null means no caching. Undefined means the default caching period is used.
	 * @return {jQuery.Promise} the return value from mw.Api.get
	 */
	Api.prototype.apiGetWithMaxAge = function ( params, ajaxOptions, maxage ) {
		if ( maxage === undefined ) {
			maxage = this.options.maxage;
		}
		if ( maxage ) {
			params.maxage = params.smaxage = maxage;
		}

		return this.api.get( params, ajaxOptions );
	};

	/**
	 * Pulls an error message out of an API response.
	 *
	 * @param {Object} data
	 * @param {Object} data.error
	 * @param {string} data.error.code
	 * @param {string} data.error.info
	 * @return {string} From data.error.code + ': ' + data.error.info, or 'unknown error'
	 */
	Api.prototype.getErrorMessage = function ( data ) {
		var errorCode, errorMessage;
		errorCode = data.error && data.error.code;
		errorMessage = data.error && data.error.info || 'unknown error';
		if ( errorCode ) {
			errorMessage = errorCode + ': ' + errorMessage;
		}
		return errorMessage;
	};

	/**
	 * Returns a fixed a title based on the API response.
	 * The title of the returned file might be different from the requested title, e.g.
	 * if we used a namespace alias. If that happens the old and new title will be set in
	 * data.query.normalized; this method creates an updated title based on that.
	 *
	 * @param {mw.Title} title
	 * @param {Object} data
	 * @return {mw.Title}
	 */
	Api.prototype.getNormalizedTitle = function ( title, data ) {
		var i, normalized, length;
		if ( data && data.query && data.query.normalized ) {
			for ( normalized = data.query.normalized, length = normalized.length, i = 0; i < length; i++ ) {
				if ( normalized[ i ].from === title.getPrefixedText() ) {
					title = new mw.Title( normalized[ i ].to );
					break;
				}
			}
		}
		return title;
	};

	/**
	 * Returns a promise with the specified field from the API result.
	 * This is intended to be used as a .then() callback for action=query APIs.
	 *
	 * @param {string} field
	 * @param {Object} data
	 * @return {jQuery.Promise} when successful, the first argument will be the field data,
	 *     when unsuccessful, it will be an error message. The second argument is always
	 *     the full API response.
	 */
	Api.prototype.getQueryField = function ( field, data ) {
		if ( data && data.query && data.query[ field ] ) {
			return $.Deferred().resolve( data.query[ field ], data );
		} else {
			return $.Deferred().reject( this.getErrorMessage( data ), data );
		}
	};

	/**
	 * Returns a promise with the specified page from the API result.
	 * This is intended to be used as a .then() callback for action=query&prop=(...) APIs.
	 *
	 * @param {mw.Title} title
	 * @param {Object} data
	 * @return {jQuery.Promise} when successful, the first argument will be the page data,
	 *     when unsuccessful, it will be an error message. The second argument is always
	 *     the full API response.
	 */
	Api.prototype.getQueryPage = function ( title, data ) {
		var pageName, pageData = null;
		if ( data && data.query && data.query.pages ) {
			title = this.getNormalizedTitle( title, data );
			pageName = title.getPrefixedText();

			// pages is an associative array indexed by pageid,
			// we need to iterate through to find the right page
			// eslint-disable-next-line no-jquery/no-each-util
			$.each( data.query.pages, function ( id, page ) {
				if ( page.title === pageName ) {
					pageData = page;
					return false;
				}
			} );

			if ( pageData ) {
				return $.Deferred().resolve( pageData, data );
			}
		}

		// If we got to this point either the pages array is missing completely, or we iterated
		// through it and the requested page was not found. Neither is supposed to happen
		// (if the page simply did not exist, there would still be a record for it).
		return $.Deferred().reject( this.getErrorMessage( data ), data );
	};

	mw.mmv.provider = {};
	mw.mmv.provider.Api = Api;
}() );
