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
	var DP;

	/**
	 * Represents a dialog and the link to open it.
	 *
	 * @class mw.mmv.ui.Dialog
	 * @extends mw.mmv.ui.Element
	 * @param {jQuery} $container the element to which the dialog will be appended
	 * @param {jQuery} $openButton the button which opens the dialog. Only used for positioning.
	 * @param {mw.mmv.Config} config
	 */
	function Dialog( $container, $openButton, config ) {
		mw.mmv.ui.Element.call( this, $container );

		/** @property {boolean} isOpen Whether or not the dialog is open. */
		this.isOpen = false;

		/**
		 * @property {string[]} loadDependencies Dependencies to load before showing the dialog.
		 */
		this.loadDependencies = [];

		/**
		 * @property {string} eventPrefix Prefix specific to the class to be applied to events.
		 */
		this.eventPrefix = '';
		/** @property {mw.mmv.Config} config - */
		this.config = config;

		/** @property {jQuery} $openButton The click target which opens the dialog. */
		this.$openButton = $openButton;

		/** @type {jQuery} $dialog The main dialog container */
		this.$dialog = $( '<div>' )
			.addClass( 'mw-mmv-dialog' );

		/**
		 * @property {jQuery} $downArrow Tip of the dialog pointing to $openButton. Called
		 * downArrow for historical reasons although it does not point down anymore.
		 */
		this.$downArrow = $( '<div>' )
			.addClass( 'mw-mmv-dialog-down-arrow' )
			.appendTo( this.$dialog );

		this.initWarning();

		this.$dialog.appendTo( this.$container );
	}

	OO.inheritClass( Dialog, mw.mmv.ui.Element );
	DP = Dialog.prototype;

	/**
	 * Creates the DOM element that setWarning()/clearWarning() will operate on.
	 *
	 * @private
	 */
	DP.initWarning = function () {
		this.$warning = $( '<div>' )
			.addClass( 'mw-mmv-dialog-warning' )
			.hide()
			.on( 'click', function ( e ) {
				// prevent other click handlers such as the download CTA from intercepting clicks at the warning
				e.stopPropagation();
			} )
			.appendTo( this.$dialog );
	};

	/**
	 * Handles click on link that opens/closes the dialog.
	 *
	 * @param {jQuery.Event} openEvent Event object for the mmv-$dialog-open event.
	 * @param {jQuery.Event} e Event object for the click event.
	 * @return {boolean} False to cancel the default event
	 */
	DP.handleOpenCloseClick = function ( openEvent, e ) {
		var dialog = this;

		mw.loader.using( this.loadDependencies, function () {
			dialog.dependenciesLoaded = true;
			dialog.toggleDialog( e );
		}, function ( error ) {
			mw.log.error( 'mw.loader.using error when trying to load dialog dependencies', error );
		} );

		return false;
	};

	/**
	 * Toggles the open state on the dialog.
	 *
	 * @param {jQuery.Event} [e] Event object when the close action is caused by a user
	 *   action, as opposed to closing the window or something.
	 */
	DP.toggleDialog = function ( e ) {
		if ( this.isOpen ) {
			this.closeDialog( e );
		} else {
			this.openDialog();
		}
	};

	/**
	 * Opens a dialog.
	 */
	DP.openDialog = function () {
		mw.mmv.actionLogger.log( this.eventPrefix + '-open' );

		this.startListeningToOutsideClick();
		this.$dialog.show();
		this.isOpen = true;
		this.$openButton.addClass( 'mw-mmv-dialog-open' );
	};

	/**
	 * Closes a dialog.
	 */
	DP.closeDialog = function () {
		if ( this.isOpen ) {
			mw.mmv.actionLogger.log( this.eventPrefix + '-close' );
		}

		this.stopListeningToOutsideClick();
		this.$dialog.hide();
		this.isOpen = false;
		this.$openButton.removeClass( 'mw-mmv-dialog-open' );
	};

	/**
	 * Sets up the event handler which closes the dialog when the user clicks outside.
	 */
	DP.startListeningToOutsideClick = function () {
		var dialog = this;

		this.outsideClickHandler = this.outsideClickHandler || function ( e ) {
			var $clickTarget = $( e.target );

			// Don't close the dialog if the click inside a dialog or on an navigation arrow
			if (
				$clickTarget.closest( dialog.$dialog ).length ||
				$clickTarget.closest( '.mw-mmv-next-image' ).length ||
				$clickTarget.closest( '.mw-mmv-prev-image' ).length ||
				e.which === 3
			) {
				return;
			}

			dialog.closeDialog();
			return false;
		};
		$( document ).on( 'click.mmv.' + this.eventPrefix, this.outsideClickHandler );
	};

	/**
	 * Removes the event handler set up by startListeningToOutsideClick().
	 */
	DP.stopListeningToOutsideClick = function () {
		$( document ).off( 'click.mmv.' + this.eventPrefix, this.outsideClickHandler );
	};

	/**
	 * Clears listeners.
	 */
	DP.unattach = function () {
		mw.mmv.ui.Element.prototype.unattach.call( this );

		this.stopListeningToOutsideClick();
	};

	/**
	 * @inheritdoc
	 */
	DP.empty = function () {
		this.closeDialog();
		this.clearWarning();
	};

	/**
	 * Displays a warning ribbon.
	 *
	 * @param {string} content Content of the warning (can be HTML,
	 *   setWarning does no escaping).
	 */
	DP.setWarning = function ( content ) {
		this.$warning
			.empty()
			.append( content )
			.show();
		this.$dialog.addClass( 'mw-mmv-warning-visible' );
	};

	/**
	 * Removes the warning ribbon.
	 */
	DP.clearWarning = function () {
		this.$warning.hide();
		this.$dialog.removeClass( 'mw-mmv-warning-visible' );
	};

	/**
	 * @param {mw.mmv.model.Image} image
	 * @return {string[]}
	 */
	DP.getImageWarnings = function ( image ) {
		var warnings = [];

		if ( image.deletionReason ) {
			warnings.push( mw.message( 'multimediaviewer-reuse-warning-deletion' ).plain() );
			// Don't inform about other warnings (they may be the cause of the deletion)
			return warnings;
		}

		if ( !image.license || image.license.needsAttribution() && !image.author && !image.attribution ) {
			warnings.push( mw.message( 'multimediaviewer-reuse-warning-noattribution' ).plain() );
		}

		if ( image.license && !image.license.isFree() ) {
			warnings.push( mw.message( 'multimediaviewer-reuse-warning-nonfree' ).plain() );
		}

		return warnings;
	};

	/**
	 * @param {mw.mmv.model.Image} image
	 */
	DP.showImageWarnings = function ( image ) {
		var warnings = this.getImageWarnings( image );

		if ( warnings.length > 0 ) {
			warnings.push( mw.message( 'multimediaviewer-reuse-warning-generic', image.descriptionUrl ).parse() );
			this.setWarning( warnings.join( '<br />' ) );
		} else {
			this.clearWarning();
		}
	};

	mw.mmv.ui.Dialog = Dialog;
}() );
