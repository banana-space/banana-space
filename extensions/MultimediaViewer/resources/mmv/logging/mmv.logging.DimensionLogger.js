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
	var DL;

	/**
	 * Writes EventLogging entries for size measurements related to thumbnail size selection
	 * (bucket size vs. display size).
	 *
	 * @class mw.mmv.logging.DimensionLogger
	 * @extends mw.mmv.logging.Logger
	 * @constructor
	 */
	function DimensionLogger() {}

	oo.inheritClass( DimensionLogger, mw.mmv.logging.Logger );

	DL = DimensionLogger.prototype;

	/**
	 * @override
	 * @inheritdoc
	 */
	DL.samplingFactor = mw.config.get( 'wgMultimediaViewer' ).dimensionSamplingFactor;

	/**
	 * @override
	 * @inheritdoc
	 */
	DL.schema = 'MultimediaViewerDimensions';

	/**
	 * Logs dimension data.
	 *
	 * @param {mw.mmv.model.ThumbnailWidth} imageWidths Widths of the image that will be displayed
	 * @param {Object} canvasDimensions Canvas width and height in CSS pixels
	 * @param {string} context Reason for requesting the image, one of 'show', 'resize', 'preload'
	 */
	DL.logDimensions = function ( imageWidths, canvasDimensions, context ) {
		var data;

		data = {
			screenWidth: screen.width,
			screenHeight: screen.height,
			viewportWidth: $( window ).width(),
			viewportHeight: $( window ).height(),
			canvasWidth: canvasDimensions.width,
			canvasHeight: canvasDimensions.height,
			devicePixelRatio: $.devicePixelRatio(),
			imgWidth: imageWidths.cssWidth,
			imageAspectRatio: imageWidths.cssWidth / imageWidths.cssHeight,
			thumbWidth: imageWidths.real,
			context: context,
			samplingFactor: this.samplingFactor
		};

		if ( this.isEnabled() ) {
			mw.log( 'mw.mmw.logger.DimensionLogger', data );
		}

		this.log( data );
	};

	mw.mmv.logging.DimensionLogger = DimensionLogger;
	mw.mmv.dimensionLogger = new DimensionLogger();
}( mediaWiki, jQuery, OO ) );
