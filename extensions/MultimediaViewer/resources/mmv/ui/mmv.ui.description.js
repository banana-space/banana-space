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
	/**
	 * Description element in the UI.
	 *
	 * @class mw.mmv.ui.Description
	 * @extends mw.mmv.ui.Element
	 * @constructor
	 * @inheritdoc
	 */
	function Description( $container ) {
		mw.mmv.ui.Element.call( this, $container );

		/** @property {mw.mmv.HtmlUtils} htmlUtils - */
		this.htmlUtils = new mw.mmv.HtmlUtils();

		this.$imageDescDiv = $( '<div>' )
			.addClass( 'mw-mmv-image-desc-div empty' )
			.appendTo( this.$container );

		this.$imageDesc = $( '<p>' )
			.addClass( 'mw-mmv-image-desc' )
			.appendTo( this.$imageDescDiv );
	}

	oo.inheritClass( Description, mw.mmv.ui.Element );

	/**
	 * Sets data on the element.
	 * This complements MetadataPanel.setTitle() - information shown there will not be shown here.
	 *
	 * @param {string|null} description The text of the description
	 * @param {string|null} caption The text of the caption
	 */
	Description.prototype.set = function ( description, caption ) {
		if ( caption && description ) { // panel header shows the caption - show description here
			this.$imageDesc.html( this.htmlUtils.htmlToTextWithTags( description ) );
			this.$imageDescDiv.removeClass( 'empty' );
		} else { // either there is no description or the paner header already shows it - nothing to do here
			this.empty();
		}
	};

	/**
	 * @inheritdoc
	 */
	Description.prototype.empty = function () {
		this.$imageDesc.empty();
		this.$imageDescDiv.addClass( 'empty' );
	};

	mw.mmv.ui.Description = Description;
}( mediaWiki, jQuery, OO ) );
