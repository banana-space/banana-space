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
	var ODP;

	/**
	 * Represents the viewing options dialog and the link to open it.
	 *
	 * @class mw.mmv.ui.OptionsDialog
	 * @extends mw.mmv.ui.Dialog
	 * @param {jQuery} $container the element to which the dialog will be appended
	 * @param {jQuery} $openButton the button which opens the dialog. Only used for positioning.
	 * @param {mw.mmv.Config} config
	 */
	function OptionsDialog( $container, $openButton, config ) {
		mw.mmv.ui.Dialog.call( this, $container, $openButton, config );

		this.$dialog.addClass( 'mw-mmv-options-dialog' );
		this.eventPrefix = 'options';

		this.initPanel();
	}

	oo.inheritClass( OptionsDialog, mw.mmv.ui.Dialog );
	ODP = OptionsDialog.prototype;

	ODP.attach = function () {
		this.handleEvent( 'mmv-options-open', $.proxy( this.handleOpenCloseClick, this ) );

		this.handleEvent( 'mmv-reuse-open', $.proxy( this.closeDialog, this ) );
		this.handleEvent( 'mmv-download-open', $.proxy( this.closeDialog, this ) );
	};

	/**
	 * Initialises UI elements.
	 */
	ODP.initPanel = function () {
		this.initEnableConfirmation();
		this.initDisableConfirmation();
		this.initEnableDiv();
		this.initDisableDiv();
	};

	/**
	 * Initialises the enable confirmation pane.
	 */
	ODP.initEnableConfirmation = function () {
		this.createConfirmationPane(
			'mw-mmv-enable-confirmation',
			'$enableConfirmation',
			[
				mw.message( 'multimediaviewer-enable-confirmation-header' ).text(),
				mw.message( 'multimediaviewer-enable-confirmation-text', mw.config.get( 'wgSiteName' ) ).text()
			] );
	};

	/**
	 * Initialises the disable confirmation pane.
	 */
	ODP.initDisableConfirmation = function () {
		this.createConfirmationPane(
			'mw-mmv-disable-confirmation',
			'$disableConfirmation',
			[
				mw.message( 'multimediaviewer-disable-confirmation-header' ).text(),
				mw.message( 'multimediaviewer-disable-confirmation-text', mw.config.get( 'wgSiteName' ) ).text()
			] );
	};

	/**
	 * Initialises the enable action pane.
	 */
	ODP.initEnableDiv = function () {
		this.createActionPane(
			'mw-mmv-options-enable',
			'$enableDiv',
			mw.message( 'multimediaviewer-enable-submit-button' ).text(),
			[
				mw.message( 'multimediaviewer-enable-dialog-header' ).text(),
				mw.message( 'multimediaviewer-enable-text-header' ).text()
			], true );
	};

	/**
	 * Initialises the disable action pane.
	 */
	ODP.initDisableDiv = function () {
		this.createActionPane(
			'mw-mmv-options-disable',
			'$disableDiv',
			mw.message( 'multimediaviewer-option-submit-button' ).text(),
			[
				mw.message( 'multimediaviewer-options-dialog-header' ).text(),
				mw.message( 'multimediaviewer-options-text-header' ).text(),
				mw.message( 'multimediaviewer-options-text-body' ).text()
			], false );
	};

	/**
	 * Hides all of the divs.
	 */
	ODP.hideDivs = function () {
		this.$dialog.removeClass( 'mw-mmv-disable-confirmation-shown mw-mmv-enable-confirmation-shown mw-mmv-enable-div-shown' );

		this.$disableDiv
			.add( this.$disableConfirmation )
			.add( this.$enableDiv )
			.add( this.$enableConfirmation )
			.removeClass( 'mw-mmv-shown' );
	};

	/**
	 * Shows the confirmation div for the disable action.
	 */
	ODP.showDisableConfirmation = function () {
		this.hideDivs();
		this.$disableConfirmation.addClass( 'mw-mmv-shown' );
		this.$dialog.addClass( 'mw-mmv-disable-confirmation-shown' );
	};

	/**
	 * Shows the confirmation div for the enable action.
	 */
	ODP.showEnableConfirmation = function () {
		this.hideDivs();
		this.$enableConfirmation.addClass( 'mw-mmv-shown' );
		this.$dialog.addClass( 'mw-mmv-enable-confirmation-shown' );
	};

	/**
	 * @event mmv-options-opened
	 * Fired when the dialog is opened.
	 */

	/**
	 * Opens a dialog with information about file reuse.
	 */
	ODP.openDialog = function () {
		if ( this.isEnabled() ) {
			this.$disableDiv.addClass( 'mw-mmv-shown' );
		} else {
			this.$enableDiv.addClass( 'mw-mmv-shown' );
			this.$dialog.addClass( 'mw-mmv-enable-div-shown' );
		}

		mw.mmv.ui.Dialog.prototype.openDialog.call( this );
		$( document ).trigger( 'mmv-options-opened' );
	};

	/**
	 * @event mmv-options-closed
	 * Fired when the dialog is closed.
	 */

	/**
	 * Closes the options dialog.
	 *
	 * @param {Event} [e] Event object when the close action is caused by a user
	 *   action, as opposed to closing the window or something.
	 */
	ODP.closeDialog = function ( e ) {
		var wasConfirmation = this.$dialog.is( '.mw-mmv-disable-confirmation-shown' ) || this.$dialog.is( '.mw-mmv-enable-confirmation-shown' );

		mw.mmv.ui.Dialog.prototype.closeDialog.call( this );
		$( document ).trigger( 'mmv-options-closed' );
		this.hideDivs();

		if ( e && $( e.target ).is( '.mw-mmv-options-button' ) && wasConfirmation ) {
			this.openDialog();
		}
	};

	/**
	 * Creates a confirmation pane.
	 *
	 * @param {string} divClass Class applied to main div.
	 * @param {string} propName Name of the property on this object to which we'll assign the div.
	 * @param {string} msgs See #addText
	 */
	ODP.createConfirmationPane = function ( divClass, propName, msgs ) {
		var dialog = this,
			$div = $( '<div>' )
				.addClass( divClass )
				.appendTo( this.$dialog );

		$( '<div>' )
			.html( '&nbsp;' )
			.addClass( 'mw-mmv-confirmation-close' )
			.click( function () {
				dialog.closeDialog();
			} )
			.appendTo( $div );

		this.addText( $div, msgs );

		this[ propName ] = $div;
	};

	/**
	 * Creates an action pane.
	 *
	 * @param {string} divClass Class applied to main div.
	 * @param {string} propName Name of the property on this object to which we'll assign the div.
	 * @param {string} smsg Message for the submit button.
	 * @param {string} msgs See #addText
	 * @param {boolean} enabled Whether this dialog is an enable one.
	 */
	ODP.createActionPane = function ( divClass, propName, smsg, msgs, enabled ) {
		var $div = $( '<div>' )
			.addClass( divClass )
			.appendTo( this.$dialog );

		if ( enabled ) {
			$( '<div>' )
				.addClass( 'mw-mmv-options-enable-alert' )
				.text( mw.message( 'multimediaviewer-enable-alert' ).text() )
				.appendTo( $div );
		}

		this.addText( $div, msgs, true );
		this.addInfoLink( $div, ( enabled ? 'enable' : 'disable' ) + '-about-link' );
		this.makeButtons( $div, smsg, enabled );

		this[ propName ] = $div;
	};

	/**
	 * Creates buttons for the dialog.
	 *
	 * @param {jQuery} $container
	 * @param {string} smsg Message for the submit button.
	 * @param {boolean} enabled Whether the viewer is enabled after this dialog is submitted.
	 */
	ODP.makeButtons = function ( $container, smsg, enabled ) {
		var $submitDiv = $( '<div>' )
			.addClass( 'mw-mmv-options-submit' )
			.appendTo( $container );

		this.makeSubmitButton(
			$submitDiv,
			smsg,
			enabled
		);

		this.makeCancelButton( $submitDiv );
	};

	/**
	 * Makes a submit button for one of the panels.
	 *
	 * @param {jQuery} $submitDiv The div for the buttons in the dialog.
	 * @param {string} msg The string to put in the button.
	 * @param {boolean} enabled Whether to turn the viewer on or off when this button is pressed.
	 * @return {jQuery} Submit button
	 */
	ODP.makeSubmitButton = function ( $submitDiv, msg, enabled ) {
		var dialog = this;

		return $( '<button>' )
			.addClass( 'mw-mmv-options-submit-button mw-ui-button mw-ui-progressive' )
			.text( msg )
			.appendTo( $submitDiv )
			.click( function () {
				var $buttons = $( this ).closest( '.mw-mmv-options-submit' ).find( '.mw-mmv-options-submit-button, .mw-mmv-options-cancel-button' );
				$buttons.prop( 'disabled', true );

				dialog.config.setMediaViewerEnabledOnClick( enabled ).done( function () {
					mw.mmv.actionLogger.log( 'opt' + ( enabled ? 'in' : 'out' ) + '-' + ( mw.user.isAnon() ? 'anon' : 'loggedin' ) );

					if ( enabled ) {
						dialog.showEnableConfirmation();
					} else {
						dialog.showDisableConfirmation();
					}
				} ).always( function () {
					$buttons.prop( 'disabled', false );
				} );

				return false;
			} );
	};

	/**
	 * Makes a cancel button for one of the panels.
	 *
	 * @param {jQuery} $submitDiv The div for the buttons in the dialog.
	 * @return {jQuery} Cancel button
	 */
	ODP.makeCancelButton = function ( $submitDiv ) {
		var dialog = this;

		return $( '<button>' )
			.addClass( 'mw-mmv-options-cancel-button mw-ui-button mw-ui-quiet' )
			.text( mw.message( 'multimediaviewer-option-cancel-button' ).text() )
			.appendTo( $submitDiv )
			.click( function () {
				dialog.closeDialog();
				return false;
			} );
	};

	/**
	 * Adds text to a dialog.
	 *
	 * @param {jQuery} $container
	 * @param {string[]} msgs The messages to be added.
	 * @param {boolean} icon Whether to display an icon next to the text or not
	 */
	ODP.addText = function ( $container, msgs, icon ) {
		var i, $text, $subContainer,
			adders = [
				function ( msg ) {
					$( '<h3>' )
						.text( msg )
						.addClass( 'mw-mmv-options-dialog-header' )
						.appendTo( $container );
				},

				function ( msg ) {
					$( '<p>' )
						.text( msg )
						.addClass( 'mw-mmv-options-text-header' )
						.appendTo( $text );
				},

				function ( msg ) {
					$( '<p>' )
						.text( msg )
						.addClass( 'mw-mmv-options-text-body' )
						.appendTo( $text );
				}
			];

		$text = $( '<div>' )
			.addClass( 'mw-mmv-options-text' );

		for ( i = 0; i < msgs.length && i < adders.length; i++ ) {
			adders[ i ]( msgs[ i ] );
		}

		if ( icon ) {
			$subContainer = $( '<div>' ).addClass( 'mw-mmv-options-subcontainer' );

			$( '<div>' )
				.html( '&nbsp;' )
				.addClass( 'mw-mmv-options-icon' )
				.appendTo( $subContainer );

			$text.appendTo( $subContainer );
			$subContainer.appendTo( $container );
		} else {
			$text.appendTo( $container );
		}
	};

	/**
	 * Adds the info link to the panel.
	 *
	 * @param {jQuery} $div The panel to which we're adding the link.
	 * @param {string} eventName
	 */
	ODP.addInfoLink = function ( $div, eventName ) {
		$( '<a>' )
			.addClass( 'mw-mmv-project-info-link' )
			.prop( 'href', mw.config.get( 'wgMultimediaViewer' ).helpLink )
			.text( mw.message( 'multimediaviewer-options-learn-more' ) )
			.click( function () { mw.mmv.actionLogger.log( eventName ); } )
			.appendTo( $div.find( '.mw-mmv-options-text' ) );
	};

	/**
	 * Checks the preference.
	 *
	 * @return {boolean} MV is enabled
	 */
	ODP.isEnabled = function () {
		return this.config.isMediaViewerEnabledOnClick();
	};

	mw.mmv.ui.OptionsDialog = OptionsDialog;
}( mediaWiki, jQuery, OO ) );
