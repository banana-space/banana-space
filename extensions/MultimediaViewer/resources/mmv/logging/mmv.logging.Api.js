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
	 * Runs performance analysis on requests via mw.mmv.logging.PerformanceLogger
	 *
	 * @class mw.mmv.logging.Api
	 * @extends mw.Api
	 * @constructor
	 * @param {string} type The type of the requests to be made through this API.
	 * @param {Object} options See mw.Api#defaultOptions
	 */
	function Api( type, options ) {
		mw.Api.call( this, options );

		/** @property {mw.mmv.logging.PerformanceLogger} performance Used to record performance data. */
		this.performance = new mw.mmv.logging.PerformanceLogger();

		/** @property {string} type Type of requests being sent via this API. */
		this.type = type;
	}

	OO.inheritClass( Api, mw.Api );

	/**
	 * Runs an AJAX call to the server.
	 *
	 * @override
	 * @param {Object} parameters
	 * @param {Object} [ajaxOptions]
	 * @return {jQuery.Promise} Done: API response data. Fail: Error code.
	 */
	Api.prototype.ajax = function ( parameters, ajaxOptions ) {
		var start = ( new Date() ).getTime(),
			api = this;

		return mw.Api.prototype.ajax.call( this, parameters, ajaxOptions ).done( function ( result, jqxhr ) {
			api.performance.recordJQueryEntryDelayed( api.type, ( new Date() ).getTime() - start, jqxhr );
		} );
	};

	mw.mmv.logging.Api = Api;
}() );
