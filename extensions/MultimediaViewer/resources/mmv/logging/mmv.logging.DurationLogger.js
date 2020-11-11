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

( function ( mw, $, oo ) {
	var L;

	/**
	 * Writes EventLogging entries for duration measurements
	 *
	 * @class mw.mmv.logging.DurationLogger
	 * @extends mw.mmv.logging.Logger
	 * @constructor
	 */
	function DurationLogger() {
		this.starts = {};
		this.stops = {};
	}

	oo.inheritClass( DurationLogger, mw.mmv.logging.Logger );

	L = DurationLogger.prototype;

	/**
	 * @override
	 * @inheritdoc
	 */
	L.samplingFactor = mw.config.get( 'wgMultimediaViewer' ).durationSamplingFactor;

	// If a sampling factor specific to loggedin users is set and we're logged in, apply it
	if ( mw.config.get( 'wgMultimediaViewer' ).durationSamplingFactorLoggedin && !mw.user.isAnon() ) {
		L.samplingFactor = mw.config.get( 'wgMultimediaViewer' ).durationSamplingFactorLoggedin;
	}

	/**
	 * @override
	 * @inheritdoc
	 */
	L.schema = 'MultimediaViewerDuration';

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Saves the start of a duration
	 *
	 * @param {string|string[]} typeOrTypes Type(s) of duration being measured.
	 * @chainable
	 */
	L.start = function ( typeOrTypes ) {
		var i,
			start = $.now();

		if ( !typeOrTypes ) {
			throw new Error( 'Must specify type' );
		}

		if ( !$.isArray( typeOrTypes ) ) {
			typeOrTypes = [ typeOrTypes ];
		}

		for ( i = 0; i < typeOrTypes.length; i++ ) {
			// Don't overwrite an existing value
			if ( !this.starts.hasOwnProperty( typeOrTypes[ i ] ) ) {
				this.starts[ typeOrTypes[ i ] ] = start;
			}
		}

		return this;
	};

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Saves the stop of a duration
	 *
	 * @param {string} type Type of duration being measured.
	 * @param {number} start Start timestamp to substitute the one coming from start()
	 * @chainable
	 */
	L.stop = function ( type, start ) {
		var stop = $.now();

		if ( !type ) {
			throw new Error( 'Must specify type' );
		}

		// Don't overwrite an existing value
		if ( !this.stops.hasOwnProperty( type ) ) {
			this.stops[ type ] = stop;
		}

		// Don't overwrite an existing value
		if ( start !== undefined && !this.starts.hasOwnProperty( type ) ) {
			this.starts[ type ] = start;
		}

		return this;
	};

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Records the duration log event
	 *
	 * @param {string} type Type of duration being measured.
	 * @param {Object} extraData Extra information to add to the log event data
	 * @chainable
	 */
	L.record = function ( type, extraData ) {
		var e, duration;

		if ( !type ) {
			throw new Error( 'Must specify type' );
		}

		if ( !this.starts.hasOwnProperty( type ) || this.starts[ type ] === undefined ) {
			return;
		}

		if ( !this.stops.hasOwnProperty( type ) || this.stops[ type ] === undefined ) {
			return;
		}

		duration = this.stops[ type ] - this.starts[ type ];

		e = {
			type: type,
			duration: duration,
			loggedIn: !mw.user.isAnon(),
			samplingFactor: this.samplingFactor
		};

		if ( extraData ) {
			$.each( extraData, function ( key, value ) {
				e[ key ] = value;
			} );
		}

		if ( this.isEnabled() ) {
			mw.log( 'mw.mmw.logger.DurationLogger', e );
		}

		this.log( e );

		delete this.starts[ type ];
		delete this.stops[ type ];

		return this;
	};

	mw.mmv.durationLogger = new DurationLogger();
}( mediaWiki, jQuery, OO ) );
