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
	var LIP;

	/**
	 * Represents the main interface of the lightbox
	 *
	 * @class mw.mmv.LightboxInterface
	 * @extends mw.mmv.ui.Element
	 * @constructor
	 */
	function LightboxInterface() {
		this.localStorage = mw.storage;

		/** @property {mw.mmv.Config} config - */
		this.config = new mw.mmv.Config(
			mw.config.get( 'wgMultimediaViewer', {} ),
			mw.config,
			mw.user,
			new mw.Api(),
			this.localStorage
		);

		/**
		 * @property {mw.mmv.ThumbnailWidthCalculator}
		 * @private
		 */
		this.thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator();

		this.init();
		mw.mmv.ui.Element.call( this, this.$wrapper );
	}
	oo.inheritClass( LightboxInterface, mw.mmv.ui.Element );
	LIP = LightboxInterface.prototype;

	/**
	 * The currently selected LightboxImage.
	 *
	 * @type {mw.mmv.LightboxImage}
	 * @protected
	 */
	LIP.currentImage = null;

	/**
	 * Initialize the entire interface - helper method.
	 */
	LIP.init = function () {
		// SVG filter, needed to achieve blur in Firefox
		this.$filter = $( '<svg><filter id="gaussian-blur"><fegaussianblur stdDeviation="3"></filter></svg>' );

		this.$wrapper = $( '<div>' )
			.addClass( 'mw-mmv-wrapper' );

		this.$main = $( '<div>' )
			.addClass( 'mw-mmv-main' );

		// I blame CSS for this
		this.$innerWrapper = $( '<div>' )
			.addClass( 'mw-mmv-image-inner-wrapper' );

		this.$imageWrapper = $( '<div>' )
			.addClass( 'mw-mmv-image-wrapper' )
			.append( this.$innerWrapper );

		this.$preDiv = $( '<div>' )
			.addClass( 'mw-mmv-pre-image' );

		this.$postDiv = $( '<div>' )
			.addClass( 'mw-mmv-post-image' );

		this.$aboveFold = $( '<div>' )
			.addClass( 'mw-mmv-above-fold' );

		this.$main.append(
			this.$preDiv,
			this.$imageWrapper,
			this.$postDiv,
			this.$filter
		);

		this.$wrapper.append(
			this.$main
		);

		this.setupCanvasButtons();

		this.panel = new mw.mmv.ui.MetadataPanel( this.$postDiv, this.$aboveFold, this.localStorage, this.config );
		this.buttons = new mw.mmv.ui.CanvasButtons( this.$preDiv, this.$closeButton, this.$fullscreenButton );
		this.canvas = new mw.mmv.ui.Canvas( this.$innerWrapper, this.$imageWrapper, this.$wrapper );

		this.fileReuse = new mw.mmv.ui.reuse.Dialog( this.$innerWrapper, this.buttons.$reuse, this.config );
		this.downloadDialog = new mw.mmv.ui.download.Dialog( this.$innerWrapper, this.buttons.$download, this.config );
		this.optionsDialog = new mw.mmv.ui.OptionsDialog( this.$innerWrapper, this.buttons.$options, this.config );
	};

	/**
	 * Sets up the file reuse data in the DOM
	 *
	 * @param {mw.mmv.model.Image} image
	 * @param {mw.mmv.model.Repo} repo
	 * @param {string} caption
	 * @param {string} alt
	 */
	LIP.setFileReuseData = function ( image, repo, caption, alt ) {
		this.fileReuse.set( image, repo, caption, alt );
		this.downloadDialog.set( image, repo );
	};

	/**
	 * Empties the interface.
	 */
	LIP.empty = function () {
		this.panel.empty();

		this.canvas.empty();

		this.buttons.empty();

		this.$main.addClass( 'metadata-panel-is-closed' )
			.removeClass( 'metadata-panel-is-open' );
	};

	/**
	 * Opens the lightbox.
	 */
	LIP.open = function () {
		this.empty();
		this.attach();
	};

	/**
	 * Attaches the interface to the DOM.
	 *
	 * @param {string} [parentId] parent id where we want to attach the UI. Defaults to document
	 *  element, override is mainly used for testing.
	 */
	LIP.attach = function ( parentId ) {
		var ui = this,
			$parent;

		// Advanced description needs to be below the fold when the lightbox opens
		// regardless of what the scroll value was prior to opening the lightbox
		// If the lightbox is already attached, it means we're doing prev/next, and
		// we should avoid scrolling the panel
		if ( !this.attached ) {
			$( window ).scrollTop( 0 );
		}

		// Make sure that the metadata is going to be at the bottom when it appears
		// 83 is the height of the top metadata area. Which can't be measured by
		// reading the DOM at this point of the execution, unfortunately
		this.$postDiv.css( 'top', ( $( window ).height() - 83 ) + 'px' );

		// Re-appending the same content can have nasty side-effects
		// Such as the browser leaving fullscreen mode if the fullscreened element is part of it
		if ( this.currentlyAttached ) {
			return;
		}

		this.handleEvent( 'keyup', function ( e ) {
			if ( e.keyCode === 27 && !( e.altKey || e.ctrlKey || e.shiftKey || e.metaKey ) ) {
				// Escape button pressed
				ui.unattach();
			}
		} );

		this.handleEvent( 'jq-fullscreen-change.lip', function ( e ) {
			ui.fullscreenChange( e );
		} );

		this.handleEvent( 'keydown', function ( e ) { ui.keydown( e ); } );

		// mousemove generates a ton of events, which is why we throttle it
		this.handleEvent( 'mousemove.lip', $.throttle( 250, function ( e ) {
			ui.mousemove( e );
		} ) );

		this.handleEvent( 'mmv-faded-out', function ( e ) { ui.fadedOut( e ); } );
		this.handleEvent( 'mmv-fade-stopped', function ( e ) { ui.fadeStopped( e ); } );

		this.buttons.connect( this, {
			next: [ 'emit', 'next' ],
			prev: [ 'emit', 'prev' ]
		} );

		$parent = $( parentId || document.body );

		// Clean up fullscreen data because hard-existing fullscreen might have left
		// jquery.fullscreen unable to remove the class and attribute, since $main wasn't
		// attached to the DOM anymore at the time the jq-fullscreen-change event triggered
		this.$main.data( 'isFullscreened', false ).removeClass( 'jq-fullscreened' );
		this.isFullscreen = false;

		$parent
			.append(
				this.$wrapper
			);
		this.currentlyAttached = true;

		this.panel.attach();

		this.canvas.attach();

		// cross-communication between panel and canvas, sort of
		this.$postDiv.on( 'mmv-metadata-open.lip', function () {
			ui.$main.addClass( 'metadata-panel-is-open' )
				.removeClass( 'metadata-panel-is-closed' );
		} ).on( 'mmv-metadata-close.lip', function () {
			ui.$main.removeClass( 'metadata-panel-is-open' )
				.addClass( 'metadata-panel-is-closed' );
		} );
		this.$wrapper.on( 'mmv-panel-close-area-click.lip', function () {
			ui.panel.scroller.toggle( 'down' );
		} );

		// Buttons fading might not had been reset properly after a hard fullscreen exit
		// This needs to happen after the parent attach() because the buttons need to be attached
		// to the DOM for $.fn.stop() to work
		this.buttons.stopFade();
		this.buttons.attach();

		this.fileReuse.attach();
		this.downloadDialog.attach();
		this.optionsDialog.attach();

		// Reset the cursor fading
		this.fadeStopped();

		this.attached = true;
	};

	/**
	 * Detaches the interface from the DOM.
	 */
	LIP.unattach = function () {
		mw.mmv.actionLogger.log( 'close' );

		// Has to happen first so that the scroller can freeze with visible elements
		this.panel.unattach();

		this.$wrapper.detach();

		this.currentlyAttached = false;

		this.buttons.unattach();

		this.$postDiv.off( '.lip' );
		this.$wrapper.off( 'mmv-panel-close-area-click.lip' );

		this.fileReuse.unattach();
		this.fileReuse.closeDialog();

		this.downloadDialog.unattach();
		this.downloadDialog.closeDialog();

		this.optionsDialog.unattach();
		this.optionsDialog.closeDialog();

		// Canvas listens for events from dialogs, so should be unattached at the end
		this.canvas.unattach();

		this.clearEvents();

		this.buttons.disconnect( this, {
			next: [ 'emit', 'next' ],
			prev: [ 'emit', 'prev' ]
		} );

		// We trigger this event on the document because unattach() can run
		// when the interface is unattached
		$( document ).trigger( $.Event( 'mmv-close' ) )
			.off( 'jq-fullscreen-change.lip' );

		this.attached = false;
	};

	/**
	 * Exits fullscreen mode.
	 */
	LIP.exitFullscreen = function () {
		this.fullscreenButtonJustPressed = true;
		this.$main.exitFullscreen();
	};

	/**
	 * Enters fullscreen mode.
	 */
	LIP.enterFullscreen = function () {
		this.$main.enterFullscreen();
	};

	/**
	 * Setup for canvas navigation buttons
	 */
	LIP.setupCanvasButtons = function () {
		var ui = this,
			tooltipDelay = mw.config.get( 'wgMultimediaViewer' ).tooltipDelay;

		this.$closeButton = $( '<button>' )
			.text( ' ' )
			.addClass( 'mw-mmv-close' )
			.prop( 'title', mw.message( 'multimediaviewer-close-popup-text' ).text() )
			.tipsy( {
				delayIn: tooltipDelay,
				gravity: this.correctEW( 'ne' )
			} )
			.click( function () {
				if ( ui.isFullscreen ) {
					ui.$main.trigger( $.Event( 'jq-fullscreen-change.lip' ) );
				}
				ui.unattach();
			} );

		this.$fullscreenButton = $( '<button>' )
			.text( ' ' )
			.addClass( 'mw-mmv-fullscreen' )
			.prop( 'title', mw.message( 'multimediaviewer-fullscreen-popup-text' ).text() )
			.tipsy( {
				delayIn: tooltipDelay,
				gravity: this.correctEW( 'ne' )
			} )
			.click( function ( e ) {
				if ( ui.isFullscreen ) {
					ui.exitFullscreen();

					// mousemove is throttled and the mouse coordinates only
					// register every 250ms, so there is a chance that we moved
					// our mouse over one of the buttons but it didn't register,
					// and a fadeOut is triggered; when we're coming back from
					// fullscreen, we'll want to make sure the mouse data is
					// current so that the fadeOut behavior will not trigger
					ui.mousePosition = { x: e.pageX, y: e.pageY };
					ui.buttons.revealAndFade( ui.mousePosition );
				} else {
					ui.enterFullscreen();
				}
			} );

		// If the browser doesn't support fullscreen mode, hide the fullscreen button
		if ( $.support.fullscreen ) {
			this.$fullscreenButton.show();
		} else {
			this.$fullscreenButton.hide();
		}
	};

	/**
	 * Handle a fullscreen change event.
	 *
	 * @param {jQuery.Event} e The fullscreen change event.
	 */
	LIP.fullscreenChange = function ( e ) {
		this.isFullscreen = e.fullscreen;

		if ( this.isFullscreen ) {
			mw.mmv.actionLogger.log( 'fullscreen' );

			this.$fullscreenButton
				.prop( 'title', mw.message( 'multimediaviewer-defullscreen-popup-text' ).text() )
				.attr( 'alt', mw.message( 'multimediaviewer-defullscreen-popup-text' ).text() );
		} else {
			mw.mmv.actionLogger.log( 'defullscreen' );

			this.$fullscreenButton
				.prop( 'title', mw.message( 'multimediaviewer-fullscreen-popup-text' ).text() )
				.attr( 'alt', mw.message( 'multimediaviewer-fullscreen-popup-text' ).text() );
		}

		if ( !this.fullscreenButtonJustPressed && !e.fullscreen ) {
			// Close the interface all the way if the user pressed 'esc'
			this.unattach();
		} else if ( this.fullscreenButtonJustPressed ) {
			this.fullscreenButtonJustPressed = false;
		}

		// Fullscreen change events can happen after unattach(), in which
		// case we shouldn't do anything UI-related
		if ( !this.currentlyAttached ) {
			return;
		}

		if ( this.isFullscreen ) {
			// When entering fullscreen without a mousemove, the browser
			// still thinks that the cursor is where it was prior to entering
			// fullscreen. I.e. on top of the fullscreen button
			// Thus, we purposefully reset the saved position, so that
			// the fade out really takes place (otherwise it's cancelled
			// by updateControls which is called a few times when fullscreen opens)
			this.mousePosition = { x: 0, y: 0 };
			this.buttons.fadeOut();
		}

		// Some browsers only send resize events before toggling fullscreen, but not once the toggling is done
		// This makes sure that the UI is properly resized after a fullscreen change
		this.$main.trigger( $.Event( 'mmv-resize-end' ) );
	};

	/**
	 * Handles keydown events on the document
	 *
	 * @param {jQuery.Event} e The jQuery keypress event object
	 */
	LIP.keydown = function ( e ) {
		var forward,
			isRtl = $( document.body ).hasClass( 'rtl' );

		if ( e.altKey || e.shiftKey || e.ctrlKey || e.metaKey ) {
			return;
		}

		switch ( e.which ) {
			case 37: // Left arrow
			case 39: // Right arrow
				e.preventDefault();
				forward = ( e.which === 39 );
				if ( isRtl ) {
					forward = !forward;
				}

				if ( forward ) {
					this.emit( 'next' );
				} else {
					this.emit( 'prev' );
				}

				e.preventDefault();
				break;
		}
	};

	/**
	 * Handles mousemove events on the document
	 *
	 * @param {jQuery.Event} e The mousemove event object
	 */
	LIP.mousemove = function ( e ) {
		// T77869 ignore fake mousemove events triggered by Chrome
		if (
			e &&
			e.originalEvent &&
			e.originalEvent.movementX === 0 &&
			e.originalEvent.movementY === 0
		) {
			return;
		}

		if ( e ) {
			// Saving the mouse position is useful whenever we need to
			// run LIP.mousemove manually, such as when going to the next/prev
			// element
			this.mousePosition = { x: e.pageX, y: e.pageY };
		}

		if ( this.isFullscreen ) {
			this.buttons.revealAndFade( this.mousePosition );
		}
	};

	/**
	 * Called when the buttons have completely faded out and disappeared
	 */
	LIP.fadedOut = function () {
		this.$main.addClass( 'cursor-hidden' );
	};

	/**
	 * Called when the buttons have stopped fading and are back into view
	 */
	LIP.fadeStopped = function () {
		this.$main.removeClass( 'cursor-hidden' );
	};

	/**
	 * Updates the next and prev buttons
	 *
	 * @param {boolean} showPrevButton Whether the prev button should be revealed or not
	 * @param {boolean} showNextButton Whether the next button should be revealed or not
	 */
	LIP.updateControls = function ( showPrevButton, showNextButton ) {
		var prevNextTop = ( ( this.$imageWrapper.height() / 2 ) - 60 ) + 'px';

		if ( this.$main.data( 'isFullscreened' ) ) {
			this.$postDiv.css( 'top', '' );
		} else {
			this.$postDiv.css( 'top', this.$imageWrapper.height() );
		}

		this.buttons.setOffset( prevNextTop );
		this.buttons.toggle( showPrevButton, showNextButton );
	};

	mw.mmv.LightboxInterface = LightboxInterface;
}( mediaWiki, jQuery, OO ) );
