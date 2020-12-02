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
	var C;

	/**
	 * UI component that contains the multimedia element to be displayed.
	 * This first version assumes an image but it can be extended to other
	 * media types (video, sound, presentation, etc.).
	 *
	 * @class mw.mmv.ui.Canvas
	 * @extends mw.mmv.ui.Element
	 * @constructor
	 * @param {jQuery} $container Canvas' container
	 * @param {jQuery} $imageWrapper
	 * @param {jQuery} $mainWrapper
	 */
	function Canvas( $container, $imageWrapper, $mainWrapper ) {
		mw.mmv.ui.Element.call( this, $container );

		/**
		 * @property {boolean}
		 * @private
		 */
		this.dialogOpen = false;

		/**
		 * @property {mw.mmv.ThumbnailWidthCalculator}
		 * @private
		 */
		this.thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator();

		/**
		 * Contains image.
		 *
		 * @property {jQuery}
		 */
		this.$imageDiv = $( '<div>' )
			.addClass( 'mw-mmv-image' );

		this.$imageDiv.appendTo( this.$container );

		/**
		 * Container of canvas and controls, needed for canvas size calculations.
		 *
		 * @property {jQuery}
		 * @private
		 */
		this.$imageWrapper = $imageWrapper;

		/**
		 * Main container of image and metadata, needed to propagate events.
		 *
		 * @property {jQuery}
		 * @private
		 */
		this.$mainWrapper = $mainWrapper;

		/**
		 * Raw metadata of current image, needed for canvas size calculations.
		 *
		 * @property {mw.mmv.LightboxImage}
		 * @private
		 */
		this.imageRawMetadata = null;
	}
	OO.inheritClass( Canvas, mw.mmv.ui.Element );
	C = Canvas.prototype;

	/**
	 * Maximum blownup factor tolerated
	 *
	 * @property {number} MAX_BLOWUP_FACTOR
	 * @static
	 */
	Canvas.MAX_BLOWUP_FACTOR = 11;

	/**
	 * Blowup factor threshold at which blurring kicks in
	 *
	 * @property {number} BLUR_BLOWUP_FACTOR_THRESHOLD
	 * @static
	 */
	Canvas.BLUR_BLOWUP_FACTOR_THRESHOLD = 2;

	/**
	 * Clears everything.
	 */
	C.empty = function () {
		this.$imageDiv.addClass( 'empty' ).removeClass( 'error' );

		this.$imageDiv.empty();
	};

	/**
	 * Sets image on the canvas; does not resize it to fit. This is used to make the placeholder
	 * image available; it will be resized and displayed by #maybeDisplayPlaceholder().
	 * FIXME maybeDisplayPlaceholder() receives the placeholder so it is very unclear why this
	 * is necessary at all (apart from setting the LightboxImage, which is used in size calculations).
	 *
	 * @param {mw.mmv.LightboxImage} imageRawMetadata
	 * @param {jQuery} $imageElement
	 */
	C.set = function ( imageRawMetadata, $imageElement ) {
		this.$imageDiv.removeClass( 'empty' );

		this.imageRawMetadata = imageRawMetadata;
		this.$image = $imageElement;
		this.setUpImageClick();

		this.$imageDiv.html( this.$image );
	};

	/**
	 * Resizes image to the given dimensions and displays it on the canvas.
	 * This is used to display the actual image; it assumes set function was already called before.
	 *
	 * @param {mw.mmv.model.Thumbnail} thumbnail thumbnail information
	 * @param {HTMLImageElement} imageElement
	 * @param {mw.mmv.model.ThumbnailWidth} imageWidths
	 */
	C.setImageAndMaxDimensions = function ( thumbnail, imageElement, imageWidths ) {
		var $image = $( imageElement );

		// we downscale larger images but do not scale up smaller ones, that would look ugly
		if ( thumbnail.width > imageWidths.cssWidth ) {
			imageElement.width = imageWidths.cssWidth;
			imageElement.height = imageWidths.cssHeight;
		}

		if ( !this.$image.is( imageElement ) ) { // http://bugs.jquery.com/ticket/4087
			this.$image.replaceWith( $image );
			this.$image = $image;

			// Since the image element got replaced, we need to rescue the dialog-open class.
			this.$image.toggleClass( 'mw-mmv-dialog-is-open', this.dialogOpen );

			this.setUpImageClick();
		}
	};

	/**
	 * Handles a "dialog open/close" event from dialogs on the page.
	 *
	 * @param {jQuery.Event} e
	 */
	C.handleDialogEvent = function ( e ) {
		switch ( e.type ) {
			case 'mmv-download-opened':
				this.downloadOpen = true;
				break;
			case 'mmv-download-closed':
				this.downloadOpen = false;
				break;
			case 'mmv-reuse-opened':
				this.reuseOpen = true;
				break;
			case 'mmv-reuse-closed':
				this.reuseOpen = false;
				break;
			case 'mmv-options-opened':
				this.optionsOpen = true;
				break;
			case 'mmv-options-closed':
				this.optionsOpen = false;
				break;
		}

		this.dialogOpen = this.reuseOpen || this.downloadOpen || this.optionsOpen;
		this.$image.toggleClass( 'mw-mmv-dialog-is-open', this.dialogOpen );
	};

	/**
	 * Registers click listener on the image.
	 */
	C.setUpImageClick = function () {
		var canvas = this;

		this.handleEvent( 'mmv-reuse-opened', this.handleDialogEvent.bind( this ) );
		this.handleEvent( 'mmv-reuse-closed', this.handleDialogEvent.bind( this ) );
		this.handleEvent( 'mmv-download-opened', this.handleDialogEvent.bind( this ) );
		this.handleEvent( 'mmv-download-closed', this.handleDialogEvent.bind( this ) );
		this.handleEvent( 'mmv-options-opened', this.handleDialogEvent.bind( this ) );
		this.handleEvent( 'mmv-options-closed', this.handleDialogEvent.bind( this ) );

		this.$image.on( 'click.mmv-canvas', function ( e ) {
			// ignore clicks if the metadata panel or one of the dialogs is open - assume the intent is to
			// close it in this case; that will be handled elsewhere
			if (
				!canvas.dialogOpen &&
				// FIXME a UI component should not know about its parents
				canvas.$container.closest( '.metadata-panel-is-open' ).length === 0
			) {
				e.stopPropagation(); // don't let $imageWrapper handle this
				mw.mmv.actionLogger.log( 'view-original-file' ).always( function () {
					$( document ).trigger( 'mmv-viewfile' );
				} );
			}
		} );

		// open the download panel on right clicking the image
		this.$image.on( 'mousedown.mmv-canvas', function ( e ) {
			if ( e.which === 3 ) {
				mw.mmv.actionLogger.log( 'right-click-image' );
				if ( !canvas.downloadOpen ) {
					$( document ).trigger( 'mmv-download-open', e );
					e.stopPropagation();
				}
			}
		} );
	};

	/**
	 * Registers listeners.
	 */
	C.attach = function () {
		var canvas = this;

		$( window ).on( 'resize.mmv-canvas', $.debounce( 100, function () {
			canvas.$mainWrapper.trigger( $.Event( 'mmv-resize-end' ) );
		} ) );

		this.$imageWrapper.on( 'click.mmv-canvas', function () {
			if ( canvas.$container.closest( '.metadata-panel-is-open' ).length > 0 ) {
				canvas.$mainWrapper.trigger( 'mmv-panel-close-area-click' );
			}
		} );
	};

	/**
	 * Clears listeners.
	 */
	C.unattach = function () {
		this.clearEvents();

		$( window ).off( 'resize.mmv-canvas' );

		this.$imageWrapper.off( 'click.mmv-canvas' );
	};

	/**
	 * Sets page thumbnail for display if blowupFactor <= MAX_BLOWUP_FACTOR. Otherwise thumb is not set.
	 * The image gets also blured to avoid pixelation if blowupFactor > BLUR_BLOWUP_FACTOR_THRESHOLD.
	 * We set SVG files to the maximum screen size available.
	 * Assumes set function called before.
	 *
	 * @param {{width: number, height: number}} size
	 * @param {jQuery} $imagePlaceholder Image placeholder to be displayed while the real image loads.
	 * @param {mw.mmv.model.ThumbnailWidth} imageWidths
	 * @return {boolean} Whether the image was blured or not
	 */
	C.maybeDisplayPlaceholder = function ( size, $imagePlaceholder, imageWidths ) {
		var targetWidth,
			targetHeight,
			blowupFactor,
			blurredThumbnailShown = false;

		// Assume natural thumbnail size¸
		targetWidth = size.width;
		targetHeight = size.height;

		// If the image is bigger than the screen we need to resize it
		if ( size.width > imageWidths.cssWidth ) { // This assumes imageInfo.width in CSS units
			targetWidth = imageWidths.cssWidth;
			targetHeight = imageWidths.cssHeight;
		}

		blowupFactor = targetWidth / $imagePlaceholder.width();

		// If the placeholder is too blown up, it's not worth showing it
		if ( blowupFactor > Canvas.MAX_BLOWUP_FACTOR ) {
			return blurredThumbnailShown;
		}

		$imagePlaceholder.width( targetWidth );
		$imagePlaceholder.height( targetHeight );

		// Only blur the placeholder if it's blown up significantly
		if ( blowupFactor > Canvas.BLUR_BLOWUP_FACTOR_THRESHOLD ) {
			this.blur( $imagePlaceholder );
			blurredThumbnailShown = true;
		}

		this.set( this.imageRawMetadata, $imagePlaceholder.show() );

		return blurredThumbnailShown;
	};

	/**
	 * Blur image
	 *
	 * @param {jQuery} $image Image to be blurred.
	 */
	C.blur = function ( $image ) {
		// We have to apply the SVG filter here, it doesn't work when defined in the .less file
		// We can't use an external SVG file because filters can't be accessed cross-domain
		// We can't embed the SVG file because accessing the filter inside of it doesn't work
		$image.addClass( 'blurred' ).css( 'filter', 'url("#gaussian-blur")' );
	};

	/**
	 * Animates the image into focus
	 */
	C.unblurWithAnimation = function () {
		var self = this,
			animationLength = 300;

		// The blurred class has an opacity < 1. This animated the image to become fully opaque
		// FIXME: Use CSS transition
		// eslint-disable-next-line no-jquery/no-animate
		this.$image
			.addClass( 'blurred' )
			.animate( { opacity: 1.0 }, animationLength );

		// During the same amount of time (animationLength) we animate a blur value from 3.0 to 0.0
		// We pass that value to an inline CSS Gaussian blur effect
		// FIXME: Use CSS transition
		// eslint-disable-next-line no-jquery/no-animate
		$( { blur: 3.0 } ).animate( { blur: 0.0 }, {
			duration: animationLength,
			step: function ( step ) {
				self.$image.css( { '-webkit-filter': 'blur(' + step + 'px)',
					filter: 'blur(' + step + 'px)' } );
			},
			complete: function () {
				// When the animation is complete, the blur value is 0, clean things up
				self.unblur();
			}
		} );
	};

	C.unblur = function () {
		// We apply empty CSS values to remove the inline styles applied by jQuery
		// so that they don't get in the way of styles defined in CSS
		this.$image.css( { '-webkit-filter': '', opacity: '', filter: '' } )
			.removeClass( 'blurred' );
	};

	/**
	 * Displays a message and error icon when loading the image fails.
	 *
	 * @param {string} error error message
	 */
	C.showError = function ( error ) {
		var errorDetails, description, errorUri, $retryLink, $reportLink,
			canvasDimensions = this.getDimensions(),
			thumbnailDimensions = this.getCurrentImageWidths(),
			htmlUtils = new mw.mmv.HtmlUtils();

		errorDetails = [
			'error: ' + error,
			'URL: ' + location.href,
			'user agent: ' + navigator.userAgent,
			'screen size: ' + screen.width + 'x' + screen.height,
			'canvas size: ' + canvasDimensions.width + 'x' + canvasDimensions.height,
			'image size: ' + this.imageRawMetadata.originalWidth + 'x' + this.imageRawMetadata.originalHeight,
			'thumbnail size: CSS: ' + thumbnailDimensions.cssWidth + 'x' + thumbnailDimensions.cssHeight +
				', screen width: ' + thumbnailDimensions.screen + ', real width: ' + thumbnailDimensions.real
		];
		// ** is bolding in Phabricator
		description = '**' + mw.message( 'multimediaviewer-errorreport-privacywarning' ).text() + '**\n\n\n' +
			'Error details:\n\n' + errorDetails.join( '\n' );
		errorUri = mw.msg( 'multimediaviewer-report-issue-url', encodeURIComponent( description ) );

		$retryLink = $( '<a>' ).addClass( 'mw-mmv-retry-link' ).text(
			mw.msg( 'multimediaviewer-thumbnail-error-retry' ) );
		$reportLink = $( '<a>' ).attr( 'href', errorUri ).text(
			mw.msg( 'multimediaviewer-thumbnail-error-report' ) );

		this.$imageDiv.empty()
			.addClass( 'error' )
			.append(
				$( '<div>' ).addClass( 'error-box' ).append(
					$( '<div>' ).addClass( 'mw-mmv-error-text' ).text(
						mw.msg( 'multimediaviewer-thumbnail-error' )
					)
				).append(
					$( '<div>' ).addClass( 'mw-mmv-error-description' ).append(
						mw.msg( 'multimediaviewer-thumbnail-error-description',
							htmlUtils.jqueryToHtml( $retryLink ),
							error,
							htmlUtils.jqueryToHtml( $reportLink )
						)
					)
				)
			);
		this.$imageDiv.find( '.mw-mmv-retry-link' ).on( 'click', function () {
			location.reload();
		} );
	};

	/**
	 * Returns width and height of the canvas area (i.e. the space available for the image).
	 *
	 * @param {boolean} forFullscreen if true, return size in fullscreen mode; otherwise, return current size
	 *  (which might still be fullscreen mode).
	 * @return {Object} Width and height in CSS pixels
	 */
	C.getDimensions = function ( forFullscreen ) {
		var $window = $( window ),
			// eslint-disable-next-line no-jquery/no-global-selector
			$aboveFold = $( '.mw-mmv-above-fold' ),
			isFullscreened = !!$aboveFold.closest( '.jq-fullscreened' ).length,
			// Don't rely on this.$imageWrapper's sizing because it's fragile.
			// Depending on what the wrapper contains, its size can be 0 on some browsers.
			// Therefore, we calculate the available space manually
			availableWidth = $window.width(),
			availableHeight = $window.height() - ( isFullscreened ? 0 : $aboveFold.outerHeight() );

		if ( forFullscreen ) {
			return {
				width: screen.width,
				height: screen.height
			};
		} else {
			return {
				width: availableWidth,
				height: availableHeight
			};
		}
	};

	/**
	 * Gets the widths for a given lightbox image.
	 *
	 * @param {mw.mmv.LightboxImage} image
	 * @return {mw.mmv.model.ThumbnailWidth}
	 */
	C.getLightboxImageWidths = function ( image ) {
		var thumb = image.thumbnail,
			canvasDimensions = this.getDimensions();

		return this.thumbnailWidthCalculator.calculateWidths(
			canvasDimensions.width, canvasDimensions.height, thumb.width, thumb.height );
	};

	/**
	 * Gets the fullscreen widths for a given lightbox image.
	 * Intended for use before the viewer is in fullscreen mode
	 * (in fullscreen mode getLightboxImageWidths() works fine).
	 *
	 * @param {mw.mmv.LightboxImage} image
	 * @return {mw.mmv.model.ThumbnailWidth}
	 */
	C.getLightboxImageWidthsForFullscreen = function ( image ) {
		var thumb = image.thumbnail,
			canvasDimensions = this.getDimensions( true );

		return this.thumbnailWidthCalculator.calculateWidths(
			canvasDimensions.width, canvasDimensions.height, thumb.width, thumb.height );
	};

	/**
	 * Gets the widths for the current lightbox image.
	 *
	 * @return {mw.mmv.model.ThumbnailWidth}
	 */
	C.getCurrentImageWidths = function () {
		return this.getLightboxImageWidths( this.imageRawMetadata );
	};

	mw.mmv.ui.Canvas = Canvas;
}() );
