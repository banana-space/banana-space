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
	var MMVB;

	/**
	 * Bootstrap code listening to thumb clicks checking the initial location.hash
	 * Loads the mmv and opens it if necessary
	 *
	 * @class mw.mmv.MultimediaViewerBootstrap
	 */
	function MultimediaViewerBootstrap() {
		// Exposed for tests
		this.hoverWaitDuration = 200;

		// TODO lazy-load config and htmlUtils

		/** @property {mw.mmv.Config} config - */
		this.config = new mw.mmv.Config(
			mw.config.get( 'wgMultimediaViewer', {} ),
			mw.config,
			mw.user,
			new mw.Api(),
			mw.storage
		);

		this.validExtensions = this.config.extensions();

		/** @property {mw.mmv.HtmlUtils} htmlUtils - */
		this.htmlUtils = new mw.mmv.HtmlUtils();

		/**
		 * This flag is set to true when we were unable to load the viewer.
		 *
		 * @property {boolean}
		 */
		this.viewerIsBroken = false;

		this.thumbsReadyDeferred = $.Deferred();
		this.thumbs = [];
		this.$thumbs = null; // will be set by processThumbs

		// find and setup all thumbs on this page
		// this will run initially and then every time the content changes,
		// e.g. via a VE edit or pagination in a multipage file
		mw.hook( 'wikipage.content' ).add( this.processThumbs.bind( this ) );
	}

	MMVB = MultimediaViewerBootstrap.prototype;

	/**
	 * Loads the mmv module asynchronously and passes the thumb data to it
	 *
	 * @param {boolean} [setupOverlay]
	 * @return {jQuery.Promise}
	 */
	MMVB.loadViewer = function ( setupOverlay ) {
		var deferred = $.Deferred(),
			bs = this,
			viewer,
			message;

		// Don't load if someone has specifically stopped us from doing so
		if ( mw.config.get( 'wgMediaViewer' ) !== true ) {
			return deferred.reject();
		}

		// FIXME setupOverlay is a quick hack to avoid setting up and immediately
		// removing the overlay on a not-MMV -> not-MMV hash change.
		// loadViewer is called on every click and hash change and setting up
		// the overlay is not needed on all of those; this logic really should
		// not be here.
		if ( setupOverlay ) {
			bs.setupOverlay();
		}

		mw.loader.using( 'mmv', function () {
			try {
				viewer = bs.getViewer();
			} catch ( e ) {
				message = e.message;
				if ( e.stack ) {
					message += '\n' + e.stack;
				}
				deferred.reject( message );
				return;
			}
			deferred.resolve( viewer );
		}, function ( error ) {
			deferred.reject( error.message );
		} );

		return deferred.promise()
			.then(
				function ( viewer ) {
					if ( !bs.viewerInitialized ) {
						if ( bs.thumbs.length ) {
							viewer.initWithThumbs( bs.thumbs );
						}

						bs.viewerInitialized = true;
					}
					return viewer;
				},
				function ( message ) {
					mw.log.warn( message );
					bs.cleanupOverlay();
					bs.viewerIsBroken = true;
					mw.notify( 'Error loading MediaViewer: ' + message );
					return $.Deferred().reject( message );
				}
			);
	};

	/**
	 * Processes all thumbs found on the page
	 *
	 * @param {jQuery} $content Element to search for thumbs
	 */
	MMVB.processThumbs = function ( $content ) {
		var bs = this;

		this.$thumbs = $content.find(
			'.gallery .image img, ' +
			'a.image img, ' +
			'#file a img, ' +
			'figure[typeof*="mw:Image"] > *:first-child > img, ' +
			'span[typeof*="mw:Image"] img'
		);

		try {
			this.$thumbs.each( function ( i, thumb ) {
				bs.processThumb( thumb );
			} );
		} finally {
			this.thumbsReadyDeferred.resolve();
			// now that we have set up our real click handler we can we can remove the temporary
			// handler added in mmv.head.js which just replays clicks to the real handler
			$( document ).off( 'click.mmv-head' );
		}
	};

	/**
	 * Check if this thumbnail should be handled by MediaViewer
	 *
	 * @param {jQuery} $thumb the thumbnail (an `<img>` element) in question
	 * @return {boolean}
	 */
	MMVB.isAllowedThumb = function ( $thumb ) {
		var selectors = [
			'.metadata', // this is inside an informational template like {{refimprove}} on enwiki.
			'.noviewer', // MediaViewer has been specifically disabled for this image
			'.noarticletext', // we are on an error page for a non-existing article, the image is part of some template
			'#siteNotice',
			'ul.mw-gallery-slideshow li.gallerybox' // thumbnails of a slideshow gallery
		];
		return $thumb.closest( selectors.join( ', ' ) ).length === 0;

	};

	/**
	 * Processes a thumb
	 *
	 * @param {Object} thumb
	 */
	MMVB.processThumb = function ( thumb ) {
		var title,
			bs = this,
			$thumb = $( thumb ),
			$link = $thumb.closest( 'a.image, [typeof*="mw:Image"] > a' ),
			$thumbContain = $link.closest( '.thumb, [typeof*="mw:Image"]' ),
			$enlarge = $thumbContain.find( '.magnify a' ),
			link = $link.prop( 'href' ),
			alt = $thumb.attr( 'alt' ),
			isFilePageMainThumb = $thumb.closest( '#file' ).length > 0;

		if ( isFilePageMainThumb ) {
			// main thumbnail (file preview area) of a file page
			// if this is a PDF filetype thumbnail, it can trick us,
			// so we short-circuit that logic and use the file page title
			// instead of the thumbnail logic.
			title = mw.Title.newFromText( mw.config.get( 'wgTitle' ), mw.config.get( 'wgNamespaceNumber' ) );
		} else {
			title = mw.Title.newFromImg( $thumb );
		}

		if ( !title || !title.getExtension() || !( title.getExtension().toLowerCase() in bs.validExtensions ) ) {
			// Short-circuit event handler and interface setup, because
			// we can't do anything for this filetype
			return;
		}

		if ( !bs.isAllowedThumb( $thumb ) ) {
			return;
		}

		if ( $thumbContain.length ) {
			// If this is a thumb, we preload JS/CSS when the mouse cursor hovers the thumb container (thumb image + caption + border)
			$thumbContain.on( {
				mouseenter: function () {
					// There is no point preloading if clicking the thumb won't open Media Viewer
					if ( !bs.config.isMediaViewerEnabledOnClick() ) {
						return;
					}
					bs.preloadOnHoverTimer = setTimeout( function () {
						mw.loader.load( 'mmv' );
					}, bs.hoverWaitDuration );
				},
				mouseleave: function () {
					if ( bs.preloadOnHoverTimer ) {
						clearTimeout( bs.preloadOnHoverTimer );
					}
				}
			} );
		}

		if ( isFilePageMainThumb ) {
			this.processFilePageThumb( $thumb, title );
			return;
		}

		// This is the data that will be passed onto the mmv
		this.thumbs.push( {
			thumb: thumb,
			$thumb: $thumb,
			title: title,
			link: link,
			alt: alt,
			caption: this.findCaption( $thumbContain, $link ) } );

		$link.add( $enlarge ).on( 'click', function ( e ) {
			return bs.click( this, e, title );
		} );
	};

	/**
	 * Processes the main thumbnail of a file page by adding some buttons
	 * below to open MediaViewer.
	 *
	 * @param {jQuery} $thumb
	 * @param {mw.Title} title
	 */
	MMVB.processFilePageThumb = function ( $thumb, title ) {
		var $link,
			$configLink,
			$filepageButtons,
			bs = this,
			link = $thumb.closest( 'a' ).prop( 'href' );

		// remove the buttons (and the clearing element) if they are already there
		// this should not happen (at least until we support paged media) but just in case
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.mw-mmv-filepage-buttons' ).next().addBack().remove();

		$link = $( '<a>' )
			// It won't matter because we catch the click event anyway, but
			// give the user some URL to see.
			.prop( 'href', link )
			.addClass( 'mw-mmv-view-expanded mw-ui-button mw-ui-icon mw-ui-icon-before' )
			.text( mw.message( 'multimediaviewer-view-expanded' ).text() );

		$configLink = $( '<a>' )
			.prop( 'href', $thumb.closest( 'a' ).prop( 'href' ) )
			.addClass( 'mw-mmv-view-config mw-ui-button mw-ui-icon mw-ui-icon-element' )
			.text( mw.message( 'multimediaviewer-view-config' ).text() );

		$filepageButtons = $( '<div>' )
			.addClass( 'mw-ui-button-group mw-mmv-filepage-buttons' )
			.append( $link, $configLink );

		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.fullMedia' ).append(
			$filepageButtons,
			$( '<div>' )
				.css( 'clear', 'both' )
		);

		this.thumbs.push( {
			thumb: $thumb.get( 0 ),
			$thumb: $thumb,
			title: title,
			link: link
		} );

		$link.on( 'click', function () {
			if ( bs.statusInfoDialog ) {
				bs.statusInfoDialog.close();
			}
			bs.openImage( this, title );
			return false;
		} );

		$configLink.on( 'click', function () {
			if ( bs.statusInfoDialog ) {
				bs.statusInfoDialog.close();
			}
			bs.openImage( this, title ).then( function () {
				$( document ).trigger( 'mmv-options-open' );
			} );
			return false;
		} );

		if ( this.config.shouldShowStatusInfo() ) {
			this.config.disableStatusInfo();
			this.showStatusInfo();
		}
	};

	/**
	 * Shows a popup notifying the user
	 */
	MMVB.showStatusInfo = function () {
		var bs = this;

		mw.loader.using( 'mmv.ui.tipsyDialog' ).done( function () {
			/** @property {mw.mmv.ui.TipsyDialog} statusInfoDialog popup on the file page explaining how to re-enable */
			// eslint-disable-next-line no-jquery/no-global-selector
			bs.statusInfoDialog = new mw.mmv.ui.TipsyDialog( $( '.mw-mmv-view-expanded' ), { gravity: 'sw' } );
			bs.statusInfoDialog.setContent(
				mw.message( 'multimediaviewer-disable-info-title' ).plain(),
				mw.message( 'multimediaviewer-disable-info' ).escaped()
			);
			// tipsy mispositions the tooltip, probably because it does the positioning before the buttons are
			// displayed and the page is reflown. Adding some delay seems to help.
			setTimeout( function () {
				bs.statusInfoDialog.open();
			}, 1000 );
		} );
	};

	/**
	 * Finds the caption for an image.
	 *
	 * @param {jQuery} $thumbContain The container for the thumbnail.
	 * @param {jQuery} $link The link that encompasses the thumbnail.
	 * @return {string|undefined} Unsafe HTML may be present - caution
	 */
	MMVB.findCaption = function ( $thumbContain, $link ) {
		var $thumbCaption, $potentialCaptions;

		if ( !$thumbContain.length ) {
			return $link.prop( 'title' ) || undefined;
		}

		$potentialCaptions = $thumbContain.find( '.thumbcaption, figcaption' );
		if ( $potentialCaptions.length < 2 ) {
			$thumbCaption = $potentialCaptions.eq( 0 );
		} else {
			// Template:Multiple_image or some such; try to find closest caption to the image
			// eslint-disable-next-line no-jquery/no-sizzle
			$thumbCaption = $link.closest( ':has(> .thumbcaption)', $thumbContain )
				.find( '> .thumbcaption' );
		}

		if ( !$thumbCaption.length ) { // gallery, maybe
			$thumbCaption = $thumbContain
				.closest( '.gallerybox' )
				.not( function () {
					// do not treat categories as galleries - the autogenerated caption they have is not helpful
					return $thumbContain.closest( '#mw-category-media' ).length;
				} )
				.not( function () {
					// do not treat special file related pages as galleries
					var $specialFileRelatedPages = $(
						'.page-Special_NewFiles, ' +
						'.page-Special_MostLinkedFiles,' +
						'.page-Special_MostGloballyLinkedFiles, ' +
						'.page-Special_UncategorizedFiles, ' +
						'.page-Special_UnusedFiles'
					);
					return $thumbContain.closest( $specialFileRelatedPages ).length;
				} )
				.find( '.gallerytext' );
		}

		if ( $thumbCaption.find( '.magnify' ).length ) {
			$thumbCaption = $thumbCaption.clone();
			$thumbCaption.find( '.magnify' ).remove();
		}

		return this.htmlUtils.htmlToTextWithTags( $thumbCaption.html() || '' );
	};

	/**
	 * Opens MediaViewer and loads the given thumbnail. Requires processThumb() to be called first.
	 *
	 * @param {HTMLElement} element Clicked element
	 * @param {mw.Title} title File title
	 * @return {jQuery.Promise}
	 */
	MMVB.openImage = function ( element, title ) {
		var $element = $( element );

		mw.mmv.durationLogger.start( [ 'click-to-first-image', 'click-to-first-metadata' ] );

		if ( $element.is( 'a.image, [typeof*="mw:Image"] > a' ) ) {
			mw.mmv.actionLogger.log( 'thumbnail' );
		} else if ( $element.is( '.magnify a' ) ) {
			mw.mmv.actionLogger.log( 'enlarge' );
		}

		this.ensureEventHandlersAreSetUp();

		return this.loadViewer( true ).then( function ( viewer ) {
			viewer.loadImageByTitle( title, false );
		} );
	};

	/**
	 * Handles a click event on a link
	 *
	 * @param {HTMLElement} element Clicked element
	 * @param {jQuery.Event} e jQuery event object
	 * @param {mw.Title} title File title
	 * @return {boolean} a value suitable for an event handler (ie. true if the click should be handled
	 *  by the browser).
	 */
	MMVB.click = function ( element, e, title ) {
		// Do not interfere with non-left clicks or if modifier keys are pressed.
		if ( ( e.button !== 0 && e.which !== 1 ) || e.altKey || e.ctrlKey || e.shiftKey || e.metaKey ) {
			return true;
		}

		// Don't load if someone has specifically stopped us from doing so
		if ( !this.config.isMediaViewerEnabledOnClick() ) {
			return true;
		}

		// Don't load if we already tried loading and it failed
		if ( this.viewerIsBroken ) {
			return true;
		}

		this.openImage( element, title );

		// calling this late so that in case of errors users at least get to the file page
		e.preventDefault();

		return false;
	};

	/**
	 * Returns true if the hash part of the current URL is one that's owned by MMV.
	 *
	 * @return {boolean}
	 * @private
	 */
	MMVB.isViewerHash = function () {
		var path = location.hash.slice( 1 );
		return path.match( mw.mmv.ROUTE_REGEXP ) || path.match( mw.mmv.LEGACY_ROUTE_REGEXP );
	};

	/**
	 * Handles the browser location hash on pageload or hash change
	 *
	 * @param {boolean} initialHash Whether this is called for the hash that came with the pageload
	 */
	MMVB.hash = function ( initialHash ) {
		var bootstrap = this;

		// There is no point loading the mmv if it isn't loaded yet for hash changes unrelated to the mmv
		// Such as anchor links on the page
		if ( !this.viewerInitialized && !this.isViewerHash() ) {
			return;
		}

		this.loadViewer( this.isViewerHash() ).then( function ( viewer ) {
			viewer.router.checkRoute();
			// this is an ugly temporary fix to avoid a black screen of death when
			// the page is loaded with an invalid MMV url
			if ( !viewer.isOpen ) {
				bootstrap.cleanupOverlay();
			} else if ( initialHash ) {
				mw.mmv.actionLogger.log( 'hash-load' );
			} else {
				mw.mmv.actionLogger.log( 'history-navigation' );
			}
		} );
	};

	/**
	 * Instantiates a new viewer if necessary
	 *
	 * @return {mw.mmv.MultimediaViewer}
	 */
	MMVB.getViewer = function () {
		if ( this.viewer === undefined ) {
			this.viewer = new mw.mmv.MultimediaViewer( this.config );
			this.viewer.setupEventHandlers();
			mw.mmv.viewer = this.viewer;
		}

		return this.viewer;
	};

	/**
	 * Listens to events on the window/document
	 */
	MMVB.setupEventHandlers = function () {
		var self = this;

		/** @property {boolean} eventHandlersHaveBeenSetUp tracks domready event handler state */
		this.eventHandlersHaveBeenSetUp = true;

		// Interpret any hash that might already be in the url
		self.hash( true );

		$( document ).on( 'mmv-setup-overlay', function () {
			self.setupOverlay();
		} ).on( 'mmv-cleanup-overlay', function () {
			self.cleanupOverlay();
		} );
	};

	/**
	 * Cleans up event handlers, used for tests
	 */
	MMVB.cleanupEventHandlers = function () {
		$( document ).off( 'mmv-setup-overlay mmv-cleanup-overlay' );
		this.eventHandlersHaveBeenSetUp = false;
	};

	/**
	 * Makes sure event handlers are set up properly via MultimediaViewerBootstrap.setupEventHandlers().
	 * Called before loading the main mmv module. At this point, event handers for MultimediaViewerBootstrap
	 * should have been set up, but due to bug 70756 it cannot be guaranteed.
	 */
	MMVB.ensureEventHandlersAreSetUp = function () {
		if ( !this.eventHandlersHaveBeenSetUp ) {
			this.setupEventHandlers();
		}
	};

	/**
	 * Sets up the overlay while the viewer loads
	 */
	MMVB.setupOverlay = function () {
		var $body = $( document.body );

		// There are situations where we can call setupOverlay while the overlay is already there,
		// such as inside this.hash(). In that case, do nothing
		if ( $body.hasClass( 'mw-mmv-lightbox-open' ) ) {
			return;
		}

		if ( !this.$overlay ) {
			this.$overlay = $( '<div>' )
				.addClass( 'mw-mmv-overlay' );
		}

		this.savedScrollTop = $( window ).scrollTop();

		$body.addClass( 'mw-mmv-lightbox-open' )
			.append( this.$overlay );
	};

	/**
	 * Cleans up the overlay
	 */
	MMVB.cleanupOverlay = function () {
		var bootstrap = this;

		$( document.body ).removeClass( 'mw-mmv-lightbox-open' );

		if ( this.$overlay ) {
			this.$overlay.remove();
		}

		if ( this.savedScrollTop !== undefined ) {
			// setTimeout because otherwise Chrome will scroll back to top after the popstate event handlers run
			setTimeout( function () {
				$( window ).scrollTop( bootstrap.savedScrollTop );
				bootstrap.savedScrollTop = undefined;
			} );
		}
	};

	MMVB.whenThumbsReady = function () {
		return this.thumbsReadyDeferred.promise();
	};

	mw.mmv.MultimediaViewerBootstrap = MultimediaViewerBootstrap;
}() );
