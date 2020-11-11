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
	var L;

	/**
	 * Abstract class providing common code for EventLogging loggers
	 *
	 * @class mw.mmv.logging.Logger
	 * @abstract
	 */
	function Logger() {
		this.Geo = undefined;
		this.eventLog = undefined;
	}

	L = Logger.prototype;

	/**
	 * Sampling factor key-value map.
	 *
	 * Makes the logger sample log events instead of recording each one if > 0. Disables logging if === 0.
	 * @property {number}
	 */
	L.samplingFactor = 0;

	/**
	 * EventLogging schema
	 * @property {string}
	 */
	L.schema = '';

	/**
	 * Sets the Geo object providing country information about the visitor
	 *
	 * @param {Object} Geo object containing country GeoIP information about the user
	 */
	L.setGeo = function ( Geo ) {
		this.Geo = Geo;
	};

	/**
	 * Sets the eventLog object providing a facility to record events
	 *
	 * @param {mw.eventLog} eventLog EventLogging instance
	 */
	L.setEventLog = function ( eventLog ) {
		this.eventLog = eventLog;
	};

	/**
	 * Loads the dependencies that allow us to log events
	 *
	 * @return {jQuery.Promise}
	 */
	L.loadDependencies = function () {
		var self = this,
			waitForEventLog = $.Deferred();

		// Waits for dom readiness because we don't want to have these dependencies loaded in the head
		$( function () {
			// window.Geo is currently defined in components that are loaded independently, there is no cheap
			// way to load just that information. Either we piggy-back on something that already loaded it
			// or we just don't have it
			if ( window.Geo ) {
				self.setGeo( window.Geo );
			}

			try {
				mw.loader.using( [ 'ext.eventLogging', 'schema.' + self.schema ], function () {
					self.setEventLog( mw.eventLog );
					waitForEventLog.resolve();
				} );
			} catch ( e ) {
				waitForEventLog.reject();
			}
		} );

		return waitForEventLog;
	};

	/**
	 * Returns whether or not we should measure this request
	 *
	 * @return {boolean} True if this request needs to be sampled
	 */
	L.isInSample = function () {
		if ( !$.isNumeric( this.samplingFactor ) || this.samplingFactor < 1 ) {
			return false;
		}

		return Math.floor( Math.random() * this.samplingFactor ) === 0;
	};

	/**
	 * Returns whether logging this event is enabled. This is intended for console logging, which
	 * (in debug mode) should be done even if the request is not being sampled, as long as logging
	 * is enabled for some sample.
	 *
	 * @return {boolean} True if this logging is enabled
	 */
	L.isEnabled = function () {
		return $.isNumeric( this.samplingFactor ) && this.samplingFactor >= 1;
	};

	/**
	 * True if the schema has a country field. Broken out in a separate function so it's easy to mock.
	 *
	 * @return {boolean}
	 */
	L.schemaSupportsCountry = function () {
		return this.eventLog && this.eventLog.schemas && // don't die if eventLog is a mock
			this.schema in this.eventLog.schemas && // don't die if schema is not loaded
			'country' in this.eventLog.schemas[ this.schema ].schema.properties;
	};

	/**
	 * Logs EventLogging data while including Geo data if any
	 *
	 * @param {Object} data
	 * @return {jQuery.Promise}
	 */
	L.log = function ( data ) {
		var self = this;

		if ( self.isInSample() ) {
			return this.loadDependencies().then( function () {
				// Add Geo information if there's any
				if (
					self.Geo && self.Geo.country !== undefined &&
					self.schemaSupportsCountry()
				) {
					data.country = self.Geo.country;
				}

				self.eventLog.logEvent( self.schema, data );
			} );
		} else {
			return $.Deferred().resolve();
		}
	};

	mw.mmv.logging = {};
	mw.mmv.logging.Logger = Logger;
}( mediaWiki, jQuery ) );
