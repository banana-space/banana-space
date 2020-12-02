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
	 * Writes EventLogging entries for duration measurements
	 *
	 * @class mw.mmv.logging.DurationLogger
	 * @extends mw.mmv.logging.Logger
	 * @constructor
	 */
	function DurationLogger() {
		this.starts = Object.create( null );
		this.stops = Object.create( null );
	}

	OO.inheritClass( DurationLogger, mw.mmv.logging.Logger );

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

	/**
	 * Saves the start of a duration
	 *
	 * @param {string|string[]} typeOrTypes Type(s) of duration being measured.
	 * @chainable
	 */
	L.start = function ( typeOrTypes ) {
		var i,
			start = ( new Date() ).getTime();

		if ( !typeOrTypes ) {
			throw new Error( 'Must specify type' );
		}

		if ( !Array.isArray( typeOrTypes ) ) {
			typeOrTypes = [ typeOrTypes ];
		}

		for ( i = 0; i < typeOrTypes.length; i++ ) {
			// Don't overwrite an existing value
			if ( !( typeOrTypes[ i ] in this.starts ) ) {
				this.starts[ typeOrTypes[ i ] ] = start;
			}
		}

		return this;
	};

	/**
	 * Saves the stop of a duration
	 *
	 * @param {string} type Type of duration being measured.
	 * @param {number} start Start timestamp to substitute the one coming from start()
	 * @chainable
	 */
	L.stop = function ( type, start ) {
		var stop = ( new Date() ).getTime();

		if ( !type ) {
			throw new Error( 'Must specify type' );
		}

		// Don't overwrite an existing value
		if ( !( type in this.stops ) ) {
			this.stops[ type ] = stop;
		}

		// Don't overwrite an existing value
		if ( start !== undefined && !( type in this.starts ) ) {
			this.starts[ type ] = start;
		}

		return this;
	};

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

		if ( !( type in this.starts ) || this.starts[ type ] === undefined ) {
			return;
		}

		if ( !( type in this.stops ) || this.stops[ type ] === undefined ) {
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
			// eslint-disable-next-line no-jquery/no-each-util
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
}() );
