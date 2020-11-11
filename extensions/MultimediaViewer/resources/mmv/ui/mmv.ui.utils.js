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
	var RUP;

	/**
	 * A helper class for reuse logic.
	 *
	 * @class mw.mmv.ui.Utils
	 * @constructor
	 */
	function Utils() {
		/** @property {mw.mmv.HtmlUtils} htmlUtils - */
		this.htmlUtils = new mw.mmv.HtmlUtils();
	}

	RUP = Utils.prototype;

	/**
	 * Creates pulldown menu from given options.
	 *
	 * @param {string[]} options
	 * @param {string[]} classes
	 * @param {string} def
	 * @return {OO.ui.DropdownWidget}
	 */
	RUP.createPulldownMenu = function ( options, classes, def ) {
		var dropdown, i, option,
			items = [],
			choices = {};

		dropdown = new oo.ui.DropdownWidget( {
			classes: classes
		} );

		for ( i = 0; i < options.length; i++ ) {
			option = options[ i ];

			choices[ option ] = new oo.ui.MenuOptionWidget( {
				data: {
					name: option,
					height: null,
					width: null
				},
				label: this.getDimensionsMessageHtml( option, 0, 0 ),
				autoFitLabel: false
			} );

			items.push( choices[ option ] );
		}

		dropdown.getMenu()
			.addItems( items )
			.chooseItem( choices[ def ] );

		return dropdown;
	};

	/**
	 * Gets a promise for the large thumbnail URL. This is needed because thumbnail URLs cannot
	 * be reliably guessed, even if we know the full size of the image - most of the time replacing
	 * the size in another thumbnail URL works (as long as the new size is not larger than the full
	 * size), but if the file name is very long and with the larger size the URL length would
	 * exceed a certain threshold, a different schema is used instead.
	 *
	 * @param {number} width
	 *
	 * @return {jQuery.Promise.<string>}
	 */
	RUP.getThumbnailUrlPromise = function ( width ) {
		return $( document ).triggerHandler( 'mmv-request-thumbnail', width ) ||
			$.Deferred().reject();
	};

	/**
	 * Updates the menu options based on calculated sizes.
	 *
	 * @private
	 * @param {Object} sizes
	 * @param {OO.ui.MenuOptionWidget[]} options
	 */
	RUP.updateMenuOptions = function ( sizes, options ) {
		var i, option, data, $label;

		for ( i = 0; i < options.length; i++ ) {
			option = options[ i ];
			data = option.getData();

			if ( sizes[ data.name ] ) {
				option.setDisabled( false );

				// These values are later used when the item is selected
				data.width = sizes[ data.name ].width;
				data.height = sizes[ data.name ].height;

				$label = $( '<span>' ).html( this.getDimensionsMessageHtml( data.name, data.width, data.height ) );

				option.setLabel( $label );
			} else {
				option.setDisabled( true );
			}
		}
	};

	/**
	 * Calculates possible image sizes for html snippets. It returns up to
	 * three possible snippet frame sizes (small, medium, large) plus the
	 * original image size.
	 *
	 * @param {number} width
	 * @param {number} height
	 * @return {Object}
	 * @return {Object} return.small
	 * @return {Object} return.medium
	 * @return {Object} return.large
	 * @return {Object} return.original
	 */
	RUP.getPossibleImageSizesForHtml = function ( width, height ) {
		var i, bucketName,
			currentGuess, dimensions,
			bucketWidth, bucketHeight,
			buckets = {
				small: { width: 220, height: 145 },
				medium: { width: 640, height: 480 },
				large: { width: 1200, height: 900 }
			},
			sizes = {},
			bucketNames = Object.keys( buckets ),
			widthToHeight = height / width,
			heightToWidth = width / height;

		for ( i = 0; i < bucketNames.length; i++ ) {
			bucketName = bucketNames[ i ];
			dimensions = buckets[ bucketName ];
			bucketWidth = dimensions.width;
			bucketHeight = dimensions.height;

			if ( width > bucketWidth ) {
				// Width fits in the current bucket
				currentGuess = bucketWidth;

				if ( currentGuess * widthToHeight > bucketHeight ) {
					// Constrain in height, resize width accordingly
					sizes[ bucketName ] = {
						width: Math.round( bucketHeight * heightToWidth ),
						height: bucketHeight
					};
				} else {
					sizes[ bucketName ] = {
						width: currentGuess,
						height: Math.round( currentGuess * widthToHeight )
					};
				}
			} else if ( height > bucketHeight ) {
				// Height fits in the current bucket, resize width accordingly
				sizes[ bucketName ] = {
					width: Math.round( bucketHeight * heightToWidth ),
					height: bucketHeight
				};
			}
		}

		sizes.original = { width: width, height: height };

		return sizes;
	};

	/**
	 * Generates an i18n message for a label, given a size label and image dimensions
	 *
	 * @param {string} sizeLabel
	 * @param {number} width
	 * @param {number} height
	 * @return {string} i18n label html
	 */
	RUP.getDimensionsMessageHtml = function ( sizeLabel, width, height ) {
		var dimensions = this.htmlUtils.jqueryToHtml( $( '<span>' )
			.addClass( 'mw-mmv-embed-dimensions' )
			.text(
				mw.message(
					'multimediaviewer-embed-dimensions-separated',
					mw.message(
						'multimediaviewer-embed-dimensions',
						width, height ).text()
				).text()
			) );

		return mw.message(
			'multimediaviewer-' + sizeLabel + '-embed-dimensions',
			dimensions
		).text();
	};

	mw.mmv.ui.Utils = Utils;
}( mediaWiki, jQuery, OO ) );
