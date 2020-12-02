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
	// Shortcut for prototype later
	var SP;

	/**
	 * Represents the file reuse dialog and link to open it.
	 *
	 * @class mw.mmv.ui.reuse.Share
	 * @extends mw.mmv.ui.reuse.Tab
	 * @param {jQuery} $container
	 */
	function Share( $container ) {
		mw.mmv.ui.reuse.Tab.call( this, $container );
		this.init();
	}
	OO.inheritClass( Share, mw.mmv.ui.reuse.Tab );
	SP = Share.prototype;

	SP.init = function () {
		this.$pane.addClass( 'mw-mmv-share-pane' )
			.appendTo( this.$container );

		this.pageInput = new mw.widgets.CopyTextLayout( {
			help: mw.message( 'multimediaviewer-share-explanation' ).text(),
			helpInline: true,
			align: 'top',
			textInput: {
				placeholder: mw.message( 'multimediaviewer-reuse-loading-placeholder' ).text()
			},
			button: {
				label: '',
				title: mw.msg( 'multimediaviewer-reuse-copy-share' )
			}
		} );

		this.pageInput.on( 'copy', function () {
			mw.mmv.actionLogger.log( 'share-link-copied' );
		} );

		this.$pageLink = $( '<a>' )
			.addClass( 'mw-mmv-share-page-link' )
			.prop( 'alt', mw.message( 'multimediaviewer-link-to-page' ).text() )
			.prop( 'target', '_blank' )
			.html( '&nbsp;' )
			.appendTo( this.$pane )
			.on( 'click', function () {
				mw.mmv.actionLogger.log( 'share-page' );
			} );

		this.pageInput.$element.appendTo( this.$pane );

		this.$pane.appendTo( this.$container );
	};

	/**
	 * Shows the pane.
	 */
	SP.show = function () {
		mw.mmv.ui.reuse.Tab.prototype.show.call( this );
		this.select();
	};

	/**
	 * @inheritdoc
	 * @param {mw.mmv.model.Image} image
	 */
	SP.set = function ( image ) {
		var url = image.descriptionUrl + mw.mmv.getMediaHash( image.title );

		this.pageInput.textInput.setValue( url );

		this.select();

		this.$pageLink.prop( 'href', url );
	};

	/**
	 * @inheritdoc
	 */
	SP.empty = function () {
		this.pageInput.textInput.setValue( '' );
		this.$pageLink.prop( 'href', null );
	};

	/**
	 * Selects the text in the readonly textbox.
	 */
	SP.select = function () {
		this.pageInput.selectText();
	};

	mw.mmv.ui.reuse.Share = Share;
}() );
