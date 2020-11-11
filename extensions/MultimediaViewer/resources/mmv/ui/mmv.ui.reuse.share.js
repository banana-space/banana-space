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

		/**
		 * @property {mw.mmv.routing.Router} router -
		 */
		this.router = new mw.mmv.routing.Router();

		this.init();
	}
	oo.inheritClass( Share, mw.mmv.ui.reuse.Tab );
	SP = Share.prototype;

	SP.init = function () {
		var pane = this;

		this.$pane.addClass( 'mw-mmv-share-pane' )
			.appendTo( this.$container );

		this.pageInput = new oo.ui.TextInputWidget( {
			classes: [ 'mw-mmv-share-page' ],
			readOnly: true
		} );

		this.pageInput.$element.find( 'input' )
			.prop( 'placeholder', mw.message( 'multimediaviewer-reuse-loading-placeholder' ).text() );

		this.pageInput.$input.on( 'copy', function () {
			mw.mmv.actionLogger.log( 'share-link-copied' );
		} );

		this.$pageLink = $( '<a>' )
			.addClass( 'mw-mmv-share-page-link' )
			.prop( 'alt', mw.message( 'multimediaviewer-link-to-page' ).text() )
			.prop( 'target', '_blank' )
			.html( '&nbsp;' )
			.appendTo( this.$pane )
			.click( function () {
				mw.mmv.actionLogger.log( 'share-page' );
			} );

		this.$copyButton = $( '<button>' )
			.addClass( 'mw-mmv-button mw-mmv-dialog-copy' )
			.click( function () {
				// Select the text, and then try to copy the text.
				// If the copy fails or is not supported, continue as if nothing had happened.
				pane.pageInput.$input.select();
				try {
					if ( document.queryCommandSupported &&
						document.queryCommandSupported( 'copy' ) ) {
						document.execCommand( 'copy' );
					}
				} catch ( e ) {
					// queryCommandSupported in Firefox pre-41 can throw errors when used with
					// clipboard commands. We catch and ignore these and other copy-command-related
					// errors here.
				}
			} )
			.prop( 'title', mw.msg( 'multimediaviewer-reuse-copy-share' ) )
			.text( mw.msg( 'multimediaviewer-reuse-copy-share' ) )
			.tipsy( {
				delayIn: mw.config.get( 'wgMultimediaViewer' ).tooltipDelay,
				gravity: this.correctEW( 'se' )
			} )
			.appendTo( this.$pane );

		this.pageInput.$element.appendTo( this.$pane );

		this.$explanation = $( '<div>' )
			.addClass( 'mw-mmv-shareembed-explanation' )
			.text( mw.message( 'multimediaviewer-share-explanation' ).text() )
			.appendTo( this.$pane );

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
		var route = new mw.mmv.routing.ThumbnailRoute( image.title ),
			url = this.router.createHashedUrl( route, image.descriptionUrl );

		this.pageInput.setValue( url );

		this.select();

		this.$pageLink.prop( 'href', url );
	};

	/**
	 * @inheritdoc
	 */
	SP.empty = function () {
		this.pageInput.setValue( '' );
		this.$pageLink.prop( 'href', null );
	};

	/**
	 * @inheritdoc
	 */
	SP.attach = function () {
		var $input = this.pageInput.$element.find( 'input' );

		$input.on( 'focus', this.selectAllOnEvent );
		// Disable partial text selection inside the textbox
		$input.on( 'mousedown click', this.onlyFocus );
	};

	/**
	 * @inheritdoc
	 */
	SP.unattach = function () {
		var $input = this.pageInput.$element.find( 'input' );

		mw.mmv.ui.reuse.Tab.prototype.unattach.call( this );

		$input.off( 'focus mousedown click' );
	};

	/**
	 * Selects the text in the readonly textbox by triggering a focus event.
	 */
	SP.select = function () {
		this.pageInput.$element.focus();
	};

	mw.mmv.ui.reuse.Share = Share;
}( mediaWiki, jQuery, OO ) );
