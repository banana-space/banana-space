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
	var MPP;

	/**
	 * Represents the metadata panel in the viewer
	 *
	 * @class mw.mmv.ui.MetadataPanel
	 * @extends mw.mmv.ui.Element
	 * @constructor
	 * @param {jQuery} $container The container for the panel (.mw-mmv-post-image).
	 * @param {jQuery} $aboveFold The brighter headline of the metadata panel (.mw-mmv-above-fold).
	 *  Called "aboveFold" for historical reasons, but actually a part of the next sibling of the element
	 *  is also above the fold (bottom of the screen).
	 * @param {mw.storage} localStorage the localStorage object, for dependency injection
	 * @param {mw.mmv.Config} config A configuration object.
	 */
	function MetadataPanel( $container, $aboveFold, localStorage, config ) {
		mw.mmv.ui.Element.call( this, $container );

		this.$aboveFold = $aboveFold;

		/** @property {mw.mmv.Config} config - */
		this.config = config;

		/** @property {mw.mmv.HtmlUtils} htmlUtils - */
		this.htmlUtils = new mw.mmv.HtmlUtils();

		this.initializeHeader( localStorage );
		this.initializeImageMetadata();
		this.initializeAboutLinks();
	}
	oo.inheritClass( MetadataPanel, mw.mmv.ui.Element );
	MPP = MetadataPanel.prototype;

	/**
	 * Maximum number of restriction icons before default icon is used
	 *
	 * @property MAX_RESTRICT
	 * @static
	 */
	MetadataPanel.MAX_RESTRICT = 4;

	/**
	 * FIXME this should be in the jquery.fullscreen plugin.
	 *
	 * @return {boolean}
	 */
	MPP.isFullscreened = function () {
		return $( this.$container ).closest( '.jq-fullscreened' ).length > 0;
	};

	MPP.attach = function () {
		var panel = this;

		this.scroller.attach();
		this.buttons.attach();
		this.title.attach();
		this.creditField.attach();

		this.$title
			.add( this.$authorAndSource )
			.add( this.title.$ellipsis )
			.add( this.creditField.$ellipsis )
			.each( function () {
				$( this ).tipsy( 'enable' );
			} )
			.on( 'click.mmv-mp', function ( e ) {
				var clickTargetIsLink = $( e.target ).is( 'a' ),
					clickTargetIsTruncated = !!$( e.target ).closest( '.mw-mmv-ttf-truncated' ).length,
					someTextIsExpanded = !!$( e.target ).closest( '.mw-mmv-untruncated' ).length;

				if (
					!clickTargetIsLink && // don't interfere with clicks on links in the text
					clickTargetIsTruncated && // don't expand when non-truncated text is clicked
					!someTextIsExpanded // ignore clicks if text is already expanded
				) {
					if ( panel.isFullscreened() ) {
						panel.revealTruncatedText();
					} else {
						panel.scroller.toggle( 'up' );
					}
				}
			} );

		$( this.$container ).on( 'mmv-metadata-open.mmv-mp mmv-metadata-reveal-truncated-text.mmv-mp', function () {
			panel.revealTruncatedText();
		} ).on( 'mmv-metadata-close.mmv-mp', function () {
			panel.hideTruncatedText();
		} ).on( 'mouseleave.mmv-mp', function () {
			var duration;

			if ( panel.isFullscreened() ) {
				duration = parseFloat( panel.$container.css( 'transition-duration' ) ) * 1000 || 0;
				panel.panelShrinkTimeout = setTimeout( function () {
					panel.hideTruncatedText();
				}, duration );
			}
		} ).on( 'mouseenter.mmv-mp', function () {
			clearTimeout( panel.panelShrinkTimeout );
		} ).on( 'mmv-permission-grow.mmv-mp', function () {
			panel.$permissionLink
				.text( mw.message( 'multimediaviewer-permission-link-hide' ).text() );
		} ).on( 'mmv-permission-shrink.mmv-mp', function () {
			panel.$permissionLink
				.text( mw.message( 'multimediaviewer-permission-link' ).text() );
		} );

		this.handleEvent( 'jq-fullscreen-change.lip', function () {
			panel.hideTruncatedText();
		} );
	};

	MPP.unattach = function () {
		this.scroller.freezeHeight();

		this.$title
			.add( this.title.$ellipsis )
			.add( this.$authorAndSource )
			.add( this.creditField.$ellipsis )
			.each( function () {
				$( this ).tipsy( 'hide' ).tipsy( 'disable' );
			} )
			.off( 'click.mmv-mp' );

		$( this.$container ).off( '.mmv-mp' );

		this.scroller.unattach();
		this.buttons.unattach();
		this.clearEvents();
	};

	MPP.empty = function () {
		this.scroller.freezeHeight();
		this.scroller.empty();

		this.buttons.empty();

		this.description.empty();
		this.permission.empty();

		this.$title.removeClass( 'error' );
		this.title.empty();
		this.creditField.empty();

		this.$license.empty().prop( 'href', '#' );
		this.$licenseLi.addClass( 'empty' );
		this.$permissionLink.hide();
		this.$restrictions.children().hide();

		this.$filename.empty();
		this.$filenamePrefix.empty();
		this.$filenameLi.addClass( 'empty' );

		this.$datetime.empty();
		this.$datetimeLi.addClass( 'empty' );

		this.$location.empty();
		this.$locationLi.addClass( 'empty' );

		this.progressBar.empty();

		this.$container.removeClass( 'mw-mmv-untruncated' );
	};

	/* Initialization methods */

	/**
	 * Initializes the header, which contains the title, credit, and license elements.
	 *
	 * @param {mw.storage} localStorage the localStorage object, for dependency injection
	 */
	MPP.initializeHeader = function ( localStorage ) {
		this.progressBar = new mw.mmv.ui.ProgressBar( this.$aboveFold );

		this.scroller = new mw.mmv.ui.MetadataPanelScroller( this.$container, this.$aboveFold,
			localStorage );

		this.$titleDiv = $( '<div>' )
			.addClass( 'mw-mmv-title-contain' )
			.appendTo( this.$aboveFold );

		this.$container.append( this.$aboveFold );

		this.initializeButtons(); // float, needs to be on top
		this.initializeTitle();
	};

	/**
	 * Initializes the title elements.
	 */
	MPP.initializeTitle = function () {
		this.$titlePara = $( '<p>' )
			.addClass( 'mw-mmv-title-para' )
			.appendTo( this.$aboveFold );

		this.$title = $( '<span>' )
			.addClass( 'mw-mmv-title' );

		this.title = new mw.mmv.ui.TruncatableTextField( this.$titlePara, this.$title, {
			styles: [ 'mw-mmv-title-small', 'mw-mmv-title-smaller' ]
		} );
		this.title.setTitle(
			mw.message( 'multimediaviewer-title-popup-text' ),
			mw.message( 'multimediaviewer-title-popup-text-more' )
		);

		this.$title.add( this.title.$ellipsis ).tipsy( {
			delayIn: mw.config.get( 'wgMultimediaViewer' ).tooltipDelay,
			gravity: this.correctEW( 'sw' )
		} );
	};

	MPP.initializeButtons = function () {
		this.buttons = new mw.mmv.ui.StripeButtons( this.$titleDiv );
	};

	/**
	 * Initializes the main body of metadata elements.
	 */
	MPP.initializeImageMetadata = function () {
		this.$container.addClass( 'mw-mmv-ttf-ellipsis-container' );

		this.$imageMetadata = $( '<div>' )
			.addClass( 'mw-mmv-image-metadata' )
			.appendTo( this.$container );

		this.$imageMetadataLeft = $( '<div>' )
			.addClass( 'mw-mmv-image-metadata-column mw-mmv-image-metadata-desc-column' )
			.appendTo( this.$imageMetadata );

		this.$imageMetadataRight = $( '<div>' )
			.addClass( 'mw-mmv-image-metadata-column mw-mmv-image-metadata-links-column' )
			.appendTo( this.$imageMetadata );

		this.initializeCredit();
		this.description = new mw.mmv.ui.Description( this.$imageMetadataLeft );
		this.permission = new mw.mmv.ui.Permission( this.$imageMetadataLeft, this.scroller );
		this.initializeImageLinks();
	};

	/**
	 * Initializes the credit elements.
	 */
	MPP.initializeCredit = function () {
		this.$credit = $( '<p>' )
			.addClass( 'mw-mmv-credit empty' )
			.appendTo( this.$imageMetadataLeft )
			.on( 'click.mmv-mp', '.mw-mmv-credit-fallback', function () {
				mw.mmv.actionLogger.log( 'author-page' );
			} );

		// we need an inline container for tipsy, otherwise it would be centered weirdly
		this.$authorAndSource = $( '<span>' )
			.addClass( 'mw-mmv-source-author' )
			.on( 'click', '.mw-mmv-author a', function () {
				mw.mmv.actionLogger.log( 'author-page' );
			} )
			.on( 'click', '.mw-mmv-source a', function () {
				mw.mmv.actionLogger.log( 'source-page' );
			} );

		this.creditField = new mw.mmv.ui.TruncatableTextField(
			this.$credit,
			this.$authorAndSource,
			{ styles: [] }
		);

		this.creditField.setTitle(
			mw.message( 'multimediaviewer-credit-popup-text' ),
			mw.message( 'multimediaviewer-credit-popup-text-more' )
		);

		this.$authorAndSource.add( this.creditField.$ellipsis ).tipsy( {
			delayIn: mw.config.get( 'wgMultimediaViewer' ).tooltipDelay,
			gravity: this.correctEW( 'sw' )
		} );
	};

	/**
	 * Initializes the list of image metadata on the right side of the panel.
	 */
	MPP.initializeImageLinks = function () {
		this.$imageLinkDiv = $( '<div>' )
			.addClass( 'mw-mmv-image-links-div' )
			.appendTo( this.$imageMetadataRight );

		this.$imageLinks = $( '<ul>' )
			.addClass( 'mw-mmv-image-links' )
			.appendTo( this.$imageLinkDiv );

		this.initializeLicense();
		this.initializeFilename();
		this.initializeDatetime();
		this.initializeLocation();
	};

	/**
	 * Initializes the license elements.
	 */
	MPP.initializeLicense = function () {
		var panel = this;

		this.$licenseLi = $( '<li>' )
			.addClass( 'mw-mmv-license-li empty' )
			.appendTo( this.$imageLinks );

		this.$license = $( '<a>' )
			.addClass( 'mw-mmv-license' )
			.prop( 'href', '#' )
			.appendTo( this.$licenseLi )
			.on( 'click', function () {
				mw.mmv.actionLogger.log( 'license-page' );
			} );

		this.$restrictions = $( '<span>' )
			.addClass( 'mw-mmv-restrictions' )
			.appendTo( this.$licenseLi );

		this.$permissionLink = $( '<span>' )
			.addClass( 'mw-mmv-permission-link mw-mmv-label' )
			.text( mw.message( 'multimediaviewer-permission-link' ).text() )
			.appendTo( this.$licenseLi )
			.hide()
			.on( 'click', function () {
				if ( panel.permission.isFullSize() ) {
					panel.permission.shrink();
				} else {
					panel.permission.grow();
					panel.scroller.toggle( 'up' );
				}
				return false;
			} );
	};

	/**
	 * Initializes the filename element.
	 */
	MPP.initializeFilename = function () {
		this.$filenameLi = $( '<li>' )
			.addClass( 'mw-mmv-filename-li empty' )
			.appendTo( this.$imageLinks );

		this.$filenamePrefix = $( '<span>' )
			.addClass( 'mw-mmv-filename-prefix' )
			.appendTo( this.$filenameLi );

		this.$filename = $( '<span>' )
			.addClass( 'mw-mmv-filename' )
			.appendTo( this.$filenameLi );
	};

	/**
	 * Initializes the upload date/time element.
	 */
	MPP.initializeDatetime = function () {
		this.$datetimeLi = $( '<li>' )
			.addClass( 'mw-mmv-datetime-li empty' )
			.appendTo( this.$imageLinks );

		this.$datetime = $( '<span>' )
			.addClass( 'mw-mmv-datetime' )
			.appendTo( this.$datetimeLi );
	};

	/**
	 * Initializes the geolocation element.
	 */
	MPP.initializeLocation = function () {
		this.$locationLi = $( '<li>' )
			.addClass( 'mw-mmv-location-li empty' )
			.appendTo( this.$imageLinks );

		this.$location = $( '<a>' )
			.addClass( 'mw-mmv-location' )
			.appendTo( this.$locationLi )
			.click( function () { mw.mmv.actionLogger.log( 'location-page' ); } );
	};

	/**
	 * Initializes two about links at the bottom of the panel.
	 */
	MPP.initializeAboutLinks = function () {
		var separator = ' | ';

		this.$mmvAboutLink = $( '<a>' )
			.prop( 'href', mw.config.get( 'wgMultimediaViewer' ).infoLink )
			.text( mw.message( 'multimediaviewer-about-mmv' ).text() )
			.addClass( 'mw-mmv-about-link' )
			.click( function () { mw.mmv.actionLogger.log( 'about-page' ); } );

		this.$mmvDiscussLink = $( '<a>' )
			.prop( 'href', mw.config.get( 'wgMultimediaViewer' ).discussionLink )
			.text( mw.message( 'multimediaviewer-discuss-mmv' ).text() )
			.addClass( 'mw-mmv-discuss-link' )
			.click( function () { mw.mmv.actionLogger.log( 'discuss-page' ); } );

		this.$mmvHelpLink = $( '<a>' )
			.prop( 'href', mw.config.get( 'wgMultimediaViewer' ).helpLink )
			.text( mw.message( 'multimediaviewer-help-mmv' ).text() )
			.addClass( 'mw-mmv-help-link' )
			.click( function () { mw.mmv.actionLogger.log( 'help-page' ); } );

		this.$mmvAboutLinks = $( '<div>' )
			.addClass( 'mw-mmv-about-links' )
			.append(
				this.$mmvAboutLink,
				separator,
				this.$mmvDiscussLink,
				separator,
				this.$mmvHelpLink
			)
			.appendTo( this.$imageMetadata );
	};

	/* Setters */

	/**
	 * Sets the image title at the top of the metadata panel.
	 * The title will be the first one available form the options below:
	 * - the image caption
	 * - the description from the filepage
	 * - the filename (without extension)
	 *
	 * @param {mw.mmv.LightboxImage} image
	 * @param {mw.mmv.model.Image} imageData
	 */
	MPP.setTitle = function ( image, imageData ) {
		var title;

		if ( image.caption ) {
			title = image.caption;
		} else if ( imageData.description ) {
			title = imageData.description;
		} else {
			title = image.filePageTitle.getNameText();
		}

		this.title.set( title );
	};

	/**
	 * Sets the upload or creation date and time in the panel
	 *f
	 * @param {string} date The formatted date to set.
	 * @param {boolean} created Whether this is the creation date
	 */
	MPP.setDateTime = function ( date, created ) {
		this.$datetime.text(
			mw.message(
				'multimediaviewer-datetime-' + ( created ? 'created' : 'uploaded' ),
				date
			).text()
		);

		this.$datetimeLi.removeClass( 'empty' );
	};

	/**
	 * Sets the file name in the panel.
	 *
	 * @param {string} filename The file name to set, without prefix
	 */
	MPP.setFileName = function ( filename ) {
		this.$filenamePrefix.text( 'File:' );
		this.$filename.text( filename );

		this.$filenameLi.removeClass( 'empty' );
	};

	/**
	 * Set source and author.
	 *
	 * @param {string} attribution Custom attribution string
	 * @param {string} source With unsafe HTML
	 * @param {string} author With unsafe HTML
	 * @param {number} authorCount
	 * @param {string} filepageUrl URL of the file page (used when other data is not available)
	 */
	MPP.setCredit = function ( attribution, source, author, authorCount, filepageUrl ) {
		// sanitization will be done by TruncatableTextField.set()
		if ( attribution && ( authorCount <= 1 || !authorCount ) ) {
			this.creditField.set( this.wrapAttribution( attribution ) );
		} else if ( author && source ) {
			this.creditField.set(
				mw.message(
					'multimediaviewer-credit',
					this.wrapAuthor( author, authorCount, filepageUrl ),
					this.wrapSource( source )
				).plain()
			);
		} else if ( author ) {
			this.creditField.set( this.wrapAuthor( author, authorCount, filepageUrl ) );
		} else if ( source ) {
			this.creditField.set( this.wrapSource( source ) );
		} else {
			this.creditField.set(
				$( '<a>' )
					.addClass( 'mw-mmv-credit-fallback' )
					.prop( 'href', filepageUrl )
					.text( mw.message( 'multimediaviewer-credit-fallback' ).plain() )
			);
		}

		this.$credit.removeClass( 'empty' );
	};

	/**
	 * Wraps a source string it with MediaViewer styles
	 *
	 * @param {string} source Warning - unsafe HTML sometimes goes here
	 * @return {string} unsafe HTML
	 */
	MPP.wrapSource = function ( source ) {
		return $( '<span>' )
			.addClass( 'mw-mmv-source' )
			.append( $.parseHTML( source ) )
			.get( 0 ).outerHTML;
	};

	/**
	 * Wraps an author string with MediaViewer styles
	 *
	 * @param {string} author Warning - unsafe HTML sometimes goes here
	 * @param {number} authorCount
	 * @param {string} filepageUrl URL of the file page (used when some author data is not available)
	 * @return {string} unsafe HTML
	 */
	MPP.wrapAuthor = function ( author, authorCount, filepageUrl ) {
		var moreText,
			$wrapper = $( '<span>' );

		$wrapper.addClass( 'mw-mmv-author' );

		if ( authorCount > 1 ) {
			moreText = this.htmlUtils.jqueryToHtml(
				$( '<a>' )
					.addClass( 'mw-mmv-more-authors' )
					.text( mw.message( 'multimediaviewer-multiple-authors', authorCount - 1 ).text() )
					.attr( 'href', filepageUrl )
			);
			$wrapper.append( mw.message( 'multimediaviewer-multiple-authors-combine', author, moreText ).text() );
		} else {
			$wrapper.append( author );
		}

		return $wrapper.get( 0 ).outerHTML;
	};

	/**
	 * Wraps an attribution string with MediaViewer styles
	 *
	 * @param {string} attribution Warning - unsafe HTML sometimes goes here
	 * @return {string} unsafe HTML
	 */
	MPP.wrapAttribution = function ( attribution ) {
		return $( '<span>' )
			.addClass( 'mw-mmv-author' )
			.addClass( 'mw-mmv-source' )
			.append( $.parseHTML( attribution ) )
			.get( 0 ).outerHTML;
	};

	/**
	 * Sets the license display in the panel
	 *
	 * @param {mw.mmv.model.License|null} license license data (could be missing)
	 * @param {string} filePageUrl URL of the file description page
	 */
	MPP.setLicense = function ( license, filePageUrl ) {
		var shortName, url, isCc, isPd;

		if ( license ) {
			shortName = license.getShortName();
			url = license.deedUrl || filePageUrl;
			isCc = license.isCc();
			isPd = license.isPd();
		} else {
			shortName = mw.message( 'multimediaviewer-license-default' ).text();
			url = filePageUrl;
			isCc = isPd = false;
		}

		this.$license
			.text( shortName )
			.prop( 'href', url )
			.prop( 'target', license && license.deedUrl ? '_blank' : '' );

		this.$licenseLi
			.toggleClass( 'cc-license', isCc )
			.toggleClass( 'pd-license', isPd )
			.removeClass( 'empty' );
	};

	/**
	 * Set an extra permission text which should be displayed.
	 *
	 * @param {string} permission
	 */
	MPP.setPermission = function ( permission ) {
		this.$permissionLink.show();
		this.permission.set( permission );
	};

	/**
	 * Sets any special restrictions that should be displayed.
	 *
	 * @param {string[]} restrictions Array of restrictions
	 */
	MPP.setRestrictions = function ( restrictions ) {
		var panel = this,
			restrictionsSet = {},
			showDefault = false,
			validRestrictions = 0;

		$.each( restrictions, function ( index, value ) {
			if ( !mw.message( 'multimediaviewer-restriction-' + value ).exists() || value === 'default' || index + 1 > MetadataPanel.MAX_RESTRICT ) {
				showDefault = true; // If the restriction isn't defined or there are more than MAX_RESTRICT of them, show a generic symbol at the end
				return;
			}
			if ( restrictionsSet[ value ] ) {
				return; // Only show one of each symbol
			} else {
				restrictionsSet[ value ] = true;
			}

			panel.$restrictions.append( panel.createRestriction( value ) );
			validRestrictions++; // See how many defined restrictions are added so we know which default i18n msg to use
		} );

		if ( showDefault ) {
			if ( validRestrictions ) {
				panel.$restrictions.append( panel.createRestriction( 'default-and-others' ) );
			} else {
				panel.$restrictions.append( panel.createRestriction( 'default' ) );
			}
		}
	};

	/**
	 * Helper function that generates restriction labels
	 *
	 * @param {string} type Restriction type
	 * @return {jQuery} jQuery object of label
	 */
	MPP.createRestriction = function ( type ) {
		var $label = $( '<span>' )
			.addClass( 'mw-mmv-label mw-mmv-restriction-label' )
			.prop( 'title', mw.message( 'multimediaviewer-restriction-' + type ).text() )
			.tipsy( {
				delay: mw.config.get( 'wgMultimediaViewer' ).tooltipDelay,
				gravity: this.correctEW( 'se' )
			} );

		$( '<span>' )
			.addClass( 'mw-mmv-restriction-label-inner mw-mmv-restriction-' +
				( type === 'default-and-others' ? 'default' : type ) )
			.text( mw.message( 'multimediaviewer-restriction-' + type ).text() )
			.appendTo( $label );

		return $label;
	};

	/**
	 * Sets location data in the interface.
	 *
	 * @param {mw.mmv.model.Image} imageData
	 */
	MPP.setLocationData = function ( imageData ) {
		var latsec, latitude, latmsg, latdeg, latremain, latmin,
			longsec, longitude, longmsg, longdeg, longremain, longmin,
			language;

		if ( !imageData.hasCoords() ) {
			return;
		}

		latitude = imageData.latitude >= 0 ? imageData.latitude : imageData.latitude * -1;
		latmsg = 'multimediaviewer-geoloc-' + ( imageData.latitude >= 0 ? 'north' : 'south' );
		latdeg = Math.floor( latitude );
		latremain = latitude - latdeg;
		latmin = Math.floor( ( latremain ) * 60 );

		longitude = imageData.longitude >= 0 ? imageData.longitude : imageData.longitude * -1;
		longmsg = 'multimediaviewer-geoloc-' + ( imageData.longitude >= 0 ? 'east' : 'west' );
		longdeg = Math.floor( longitude );
		longremain = longitude - longdeg;
		longmin = Math.floor( ( longremain ) * 60 );

		longremain -= longmin / 60;
		latremain -= latmin / 60;
		latsec = Math.round( latremain * 100 * 60 * 60 ) / 100;
		longsec = Math.round( longremain * 100 * 60 * 60 ) / 100;

		this.$location.text(
			mw.message( 'multimediaviewer-geolocation',
				mw.message(
					'multimediaviewer-geoloc-coords',

					mw.message(
						'multimediaviewer-geoloc-coord',
						mw.language.convertNumber( latdeg ),
						mw.language.convertNumber( latmin ),
						mw.language.convertNumber( latsec ),
						mw.message( latmsg ).text()
					).text(),

					mw.message(
						'multimediaviewer-geoloc-coord',
						mw.language.convertNumber( longdeg ),
						mw.language.convertNumber( longmin ),
						mw.language.convertNumber( longsec ),
						mw.message( longmsg ).text()
					).text()
				).text()
			).text()
		);

		$.each( mw.language.data, function ( key ) {
			language = key;
			return false;
		} );

		this.$location.prop( 'href', (
			'//tools.wmflabs.org/geohack/geohack.php?pagename=' +
			'File:' + imageData.title.getMain() +
			'&params=' +
			Math.abs( imageData.latitude ) + ( imageData.latitude >= 0 ? '_N_' : '_S_' ) +
			Math.abs( imageData.longitude ) + ( imageData.longitude >= 0 ? '_E_' : '_W_' ) +
			'&language=' + language
		) );

		this.$locationLi.removeClass( 'empty' );
	};

	/**
	 * Set all the image information in the panel
	 *
	 * @param {mw.mmv.LightboxImage} image
	 * @param {mw.mmv.model.Image} imageData
	 * @param {mw.mmv.model.Repo} repoData
	 */
	MPP.setImageInfo = function ( image, imageData, repoData ) {
		var panel = this;

		mw.mmv.attributionLogger.logAttribution( imageData );

		if ( imageData.creationDateTime ) {
			// Use the raw date until moment can try to interpret it
			panel.setDateTime( imageData.creationDateTime );

			this.formatDate( imageData.creationDateTime ).then( function ( formattedDate ) {
				panel.setDateTime( formattedDate, true );
			} );
		} else if ( imageData.uploadDateTime ) {
			// Use the raw date until moment can try to interpret it
			panel.setDateTime( imageData.uploadDateTime );

			this.formatDate( imageData.uploadDateTime ).then( function ( formattedDate ) {
				panel.setDateTime( formattedDate );
			} );
		}

		this.buttons.set( imageData, repoData );
		this.description.set( imageData.description, image.caption );

		this.setLicense( imageData.license, imageData.descriptionUrl );

		this.setFileName( imageData.title.getMainText() );

		// these handle text truncation and should be called when everything that can push text down
		// (e.g. floated buttons) has already been laid out
		this.setTitle( image, imageData );
		this.setCredit( imageData.attribution, imageData.source, imageData.author, imageData.authorCount, imageData.descriptionUrl );

		if ( imageData.permission ) {
			this.setPermission( imageData.permission );
		}

		if ( imageData.restrictions ) {
			this.setRestrictions( imageData.restrictions );
		}

		this.setLocationData( imageData );

		this.resetTruncatedText();
		this.scroller.unfreezeHeight();
	};

	/**
	 * Show an error message, in case the data could not be loaded
	 *
	 * @param {string} title image title
	 * @param {string} error error message
	 */
	MPP.showError = function ( title, error ) {
		this.$credit.text( mw.message( 'multimediaviewer-metadata-error', error ).text() );
		this.$title.html( title );
	};

	/**
	 * Transforms a date string into localized, human-readable format.
	 * Unrecognized strings are returned unchanged.
	 *
	 * @param {string} dateString
	 * @return {jQuery.Deferred}
	 */
	MPP.formatDate = function ( dateString ) {
		var deferred = $.Deferred(),
			date;

		mw.loader.using( 'moment', function () {
			/* global moment */
			date = moment( dateString );

			if ( date.isValid() ) {
				deferred.resolve( date.format( 'LL' ) );
			} else {
				deferred.resolve( dateString );
			}
		}, function ( error ) {
			deferred.reject( error );
			if ( window.console && window.console.error ) {
				window.console.error( 'mw.loader.using error when trying to load moment', error );
			}
		} );

		return deferred.promise();
	};

	/**
	 * Shows truncated text in the title and credit (this also rearranges the layout a bit).
	 */
	MPP.revealTruncatedText = function () {
		if ( this.$container.hasClass( 'mw-mmv-untruncated' ) ) {
			return;
		}
		this.$container.addClass( 'mw-mmv-untruncated' );
		this.title.grow();
		this.creditField.grow();
	};

	/**
	 * Undoes changes made by revealTruncatedText().
	 */
	MPP.hideTruncatedText = function () {
		if ( !this.$container.hasClass( 'mw-mmv-untruncated' ) ) {
			return;
		}
		this.title.shrink();
		this.creditField.shrink();
		this.$container.removeClass( 'mw-mmv-untruncated' );
	};

	/**
	 * Hide or reveal truncated text based on whether the panel is open. This is normally handled by
	 * MetadataPanelScroller, but when the panel is reset (e.g. on a prev/next event) sometimes the panel position can change without a panel , such as on a
	 * prev/next event; in such cases this function has to be called.
	 */
	MPP.resetTruncatedText = function () {
		if ( this.scroller.panelIsOpen() ) {
			this.revealTruncatedText();
		} else {
			this.hideTruncatedText();
		}
	};

	mw.mmv.ui.MetadataPanel = MetadataPanel;
}( mediaWiki, jQuery, OO ) );
