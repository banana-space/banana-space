/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */
( function () {
	var TDP;

	/**
	 * A simple popup dialog that can be opened and closed and can contain some HTML.
	 * Due to the way tipsy works, there can be only one TipsyDialog and/or tipsy tooltip on the same element.
	 *
	 * @class mw.mmv.ui.TipsyDialog
	 * @extends mw.mmv.ui.Element
	 * @constructor
	 * @param {jQuery} $anchor the element to which the popup is anchored.
	 * @param {Object} options takes any tipsy option - see
	 *  https://github.com/jaz303/tipsy/blob/master/docs/src/index.html.erb#L298
	 */
	function TipsyDialog( $anchor, options ) {
		mw.mmv.ui.Element.call( this, null ); // tipsy does the element construction so we don't need a container

		/** @property {jQuery} $anchor - */
		this.$anchor = $anchor;

		/** @property {Object} options - */
		this.options = $.extend( {}, this.defaultOptions, options );

		/** @property {boolean} dirty Track whether tipsy settings changed and need to be reinitialized. */
		this.dirty = false;

		/** @property {string|null} contentTitle Title of the dialog (optional) */
		this.contentTitle = null;

		/** @property {string|null} contentBody Contents of the dialog */
		this.contentBody = null;

		/** @property {Function} closeProxy Proxied close function to be used as an event handler, so it can be
		 * identified for removal. */
		this.closeProxy = this.maybeCloseOnClick.bind( this );
	}

	OO.inheritClass( TipsyDialog, mw.mmv.ui.Element );
	TDP = TipsyDialog.prototype;

	/**
	 * @property {Object} defaultOptions Tipsy defaults - see
	 *  https://github.com/jaz303/tipsy/blob/master/docs/src/index.html.erb#L298
	 */
	TDP.defaultOptions = {
		// tipsy options
		trigger: 'manual',
		html: true,
		fade: false,
		offset: 0,
		gravity: 'sw'
	};

	/**
	 * @property {number} extraOffset offset adjustment to correct for the larger margins and tip size
	 *  compared to the standard tipsy style
	 */
	TDP.extraOffset = 10;

	/**
	 * @private
	 * @return {boolean}
	 */
	TDP.isInitialized = function () {
		return !!this.$anchor.tipsy( true );
	};

	/**
	 * Returns the preprocessed version of an options object:
	 * - directions are flipped on RTL documents
	 * - standard classnames are applied
	 * - HTML content is generated
	 * The original object is not changed.
	 *
	 * @private
	 * @param {Object} originalOptions
	 * @return {Object} Preprocessed options
	 */
	TDP.getPreprocessedOptions = function ( originalOptions ) {
		var options = $.extend( {}, originalOptions );

		if ( options.className ) {
			options.className += ' mw-mmv-tipsy-dialog';
		} else {
			options.className = ' mw-mmv-tipsy-dialog';
		}
		options.gravity = this.correctEW( options.gravity );
		options.offset += this.extraOffset;
		options.fallback = this.generateContent( this.contentTitle, this.contentBody );

		return options;
	};

	/**
	 * @private
	 */
	TDP.init = function () {
		var options;

		if ( !this.isInitialized() || this.dirty ) {
			options = this.getPreprocessedOptions( this.options );
			this.$anchor.tipsy( options );

			// add click handler to close the popup when clicking on X or outside
			// off is to make sure we won't end up with more then one - init() can be called multiple times
			this.$anchor.find( '.mw-mmv-tipsy-dialog-disable' ).add( document )
				.off( 'click.mmv-tipsy-dialog', this.closeProxy )
				.on( 'click.mmv-tipsy-dialog', this.closeProxy );

			this.dirty = false;
		}
	};

	/**
	 * Open the dialog
	 */
	TDP.open = function () {
		this.init();
		this.$anchor.tipsy( 'enable' ).tipsy( 'show' );
	};

	/**
	 * Close the dialog
	 */
	TDP.close = function () {
		if ( this.isInitialized() ) {
			this.$anchor.tipsy( 'hide' ).tipsy( 'disable' );
		}
	};

	/**
	 * Return the main popup element.
	 *
	 * @return {jQuery|null}
	 */
	TDP.getPopup = function () {
		var tipsyData = this.$anchor.tipsy( true );

		return tipsyData ? tipsyData.$tip : null;
	};

	/**
	 * Set dialog contents
	 *
	 * @param {string|null} title title of the dialog (plain text; escaping will be handled by TipsyDialog)
	 * @param {string|null} body content of the dialog (HTML; no escaping)
	 */
	TDP.setContent = function ( title, body ) {
		this.contentTitle = title;
		this.contentBody = body;
		this.dirty = true;
	};

	/**
	 * @private
	 * @param {string} [title]
	 * @param {string} [body]
	 * @return {string}
	 */
	TDP.generateContent = function ( title, body ) {
		body = body || '';
		if ( title ) {
			body = '<div class="mw-mmv-tipsy-dialog-title">' + mw.html.escape( title ) + '</div>' + body;
		}
		return '<div class="mw-mmv-tipsy-dialog-disable"></div>' + body;
	};

	/**
	 * Click handler to be set on the document.
	 *
	 * @private
	 * @param {jQuery.Event} event
	 */
	TDP.maybeCloseOnClick = function ( event ) {
		var $clickTarget = $( event.target );

		if (
			$clickTarget.closest( this.getPopup() ).length === 0 || // click was outside the dialog
			$clickTarget.closest( '.mw-mmv-tipsy-dialog-disable' ).length > 0 // click was on the close icon
		) {
			this.close();
		}
	};

	mw.mmv.ui.TipsyDialog = TipsyDialog;
}() );
