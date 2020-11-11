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
	var DP;

	/**
	 * Represents the file download dialog and the link to open it.
	 *
	 * @class mw.mmv.ui.download.Dialog
	 * @extends mw.mmv.ui.Dialog
	 * @param {jQuery} $container the element to which the dialog will be appended
	 * @param {jQuery} $openButton the button which opens the dialog. Only used for positioning.
	 * @param {mw.mmv.Config} config
	 */
	function Dialog( $container, $openButton, config ) {
		mw.mmv.ui.Dialog.call( this, $container, $openButton, config );

		this.loadDependencies.push( 'mmv.ui.download.pane' );

		this.$dialog.addClass( 'mw-mmv-download-dialog' );

		this.eventPrefix = 'download';
	}

	oo.inheritClass( Dialog, mw.mmv.ui.Dialog );
	DP = Dialog.prototype;

	/**
	 * Registers listeners.
	 */
	DP.attach = function () {
		var dialog = this;

		this.handleEvent( 'mmv-download-open', $.proxy( this.handleOpenCloseClick, this ) );

		this.handleEvent( 'mmv-reuse-open', $.proxy( this.closeDialog, this ) );
		this.handleEvent( 'mmv-options-open', $.proxy( this.closeDialog, this ) );

		this.$container.on( 'mmv-download-cta-open', function () {
			dialog.$warning.hide();
		} );
		this.$container.on( 'mmv-download-cta-close', function () {
			if ( dialog.$dialog.hasClass( 'mw-mmv-warning-visible' ) ) {
				dialog.$warning.show();
			}
		} );
	};

	/**
	 * Clears listeners.
	 */
	DP.unattach = function () {
		this.$container.off( 'mmv-download-cta-open mmv-download-cta-close' );
	};

	/**
	 * Sets data needed by contaned tabs and makes dialog launch link visible.
	 *
	 * @param {mw.mmv.model.Image} image
	 * @param {mw.mmv.model.Repo} repo
	 */
	DP.set = function ( image, repo ) {
		if ( this.download ) {
			this.download.set( image, repo );
			this.showImageWarnings( image );
		} else {
			this.setValues = {
				image: image,
				repo: repo
			};
		}
	};

	/**
	 * @event mmv-download-opened
	 * Fired when the dialog is opened.
	 */
	/**
	 * Opens a dialog with information about file download.
	 */
	DP.openDialog = function () {
		if ( !this.download ) {
			this.download = new mw.mmv.ui.download.Pane( this.$dialog );
			this.download.attach();
		}

		if ( this.setValues ) {
			this.download.set( this.setValues.image, this.setValues.repo );
			this.showImageWarnings( this.setValues.image );
			this.setValues = undefined;
		}

		mw.mmv.ui.Dialog.prototype.openDialog.call( this );

		$( document ).trigger( 'mmv-download-opened' );
	};

	/**
	 * @event mmv-download-closed
	 * Fired when the dialog is closed.
	 */
	/**
	 * Closes the download dialog.
	 */
	DP.closeDialog = function () {
		mw.mmv.ui.Dialog.prototype.closeDialog.call( this );

		$( document ).trigger( 'mmv-download-closed' );
	};

	mw.mmv.ui.download.Dialog = Dialog;
}( mediaWiki, jQuery, OO ) );
