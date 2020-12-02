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
	var AL;

	/**
	 * Writes EventLogging entries for duration measurements
	 *
	 * @class mw.mmv.logging.AttributionLogger
	 * @extends mw.mmv.logging.Logger
	 * @constructor
	 */
	function AttributionLogger() {}

	OO.inheritClass( AttributionLogger, mw.mmv.logging.Logger );

	AL = AttributionLogger.prototype;

	/**
	 * @override
	 * @inheritdoc
	 */
	AL.samplingFactor = mw.config.get( 'wgMultimediaViewer' ).attributionSamplingFactor;

	/**
	 * @override
	 * @inheritdoc
	 */
	AL.schema = 'MultimediaViewerAttribution';

	/**
	 * Logs attribution data
	 *
	 * @param {mw.mmv.model.Image} image Image data
	 */
	AL.logAttribution = function ( image ) {
		var data;

		data = {
			authorPresent: !!image.author,
			sourcePresent: !!image.source,
			licensePresent: !!image.license,
			loggedIn: !mw.user.isAnon(),
			samplingFactor: this.samplingFactor
		};

		if ( this.isEnabled() ) {
			mw.log( 'author: ' + ( data.authorPresent ? 'present' : 'absent' ) +
				', source: ' + ( data.sourcePresent ? 'present' : 'absent' ) +
				', license: ' + ( data.licensePresent ? 'present' : 'absent' ) );
		}

		this.log( data );
	};

	mw.mmv.logging.AttributionLogger = AttributionLogger;
	mw.mmv.attributionLogger = new AttributionLogger();
}() );
