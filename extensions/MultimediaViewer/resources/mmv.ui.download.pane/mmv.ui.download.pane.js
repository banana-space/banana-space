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
	 * UI component that provides functionality to download the media asset displayed.
	 *
	 * @class mw.mmv.ui.download.Pane
	 * @extends mw.mmv.ui.Element
	 * @constructor
	 * @param {jQuery} $container
	 */
	function Pane( $container ) {
		mw.mmv.ui.Element.call( this, $container );

		/** @property {mw.mmv.ui.Utils} utils - */
		this.utils = new mw.mmv.ui.Utils();

		this.$pane = $( '<div>' )
			.addClass( 'mw-mmv-download-pane' )
			.appendTo( this.$container );

		this.$downloadArea = $( '<div>' )
			.addClass( 'mw-mmv-download-area' )
			.appendTo( this.$pane );

		this.createDownloadButton( this.$downloadArea );
		this.createSizePulldownMenu( this.$downloadArea );
		this.createPreviewLink( this.$downloadArea );

		this.formatter = new mw.mmv.EmbedFileFormatter();
		this.currentAttrView = 'plain';
		this.createAttributionButton( this.$pane );

		/**
		 * Default item for the size menu.
		 *
		 * @property {OO.ui.MenuOptionWidget}
		 */
		this.defaultItem = this.downloadSizeMenu.getMenu().findSelectedItem();

		/** @property {mw.mmv.model.Image|null} Image the download button currently points to. */
		this.image = null;
	}
	OO.inheritClass( Pane, mw.mmv.ui.Element );
	DP = Pane.prototype;

	/**
	 * @event mmv-download-cta-open
	 * Fired when the attribution call to action panel is clicked.
	 */
	/**
	 * @event mmv-download-cta-close
	 * Fired when the attribution area is closed.
	 */

	/**
	 * Creates download split button. It is a link with the "download" property set plus an
	 * arrow that allows the user to select the image size desired. The "download" property
	 * triggers native browser downloading in browsers that support it. The fallback is the
	 * 'download' parameter which instructs the server to send the right headers so the browser
	 * downloads the file instead of just displaying it. If all this fails, the image will appear
	 * in another window/tab.
	 *
	 * @param {jQuery} $container
	 */
	DP.createDownloadButton = function ( $container ) {
		// TODO: Use OOUI progressive button widget instead
		this.$downloadButton = $( '<a>' )
			.attr( 'target', '_blank' )
			.attr( 'download', '' )
			.addClass( 'mw-ui-button mw-ui-progressive mw-mmv-download-go-button' )
			.on( 'click', function () {
				mw.mmv.actionLogger.log( 'download' );
			} );

		this.$selectionArrow = $( '<span>' )
			.addClass( 'mw-ui-button mw-ui-progressive mw-mmv-download-select-menu' )
			.append(
				$( '<span>' )
					.addClass( 'mw-mmv-download-image-size-name' )
					.html( '&nbsp;' )
			)
			.append(
				$( '<span>' )
					.addClass( 'mw-mmv-download-image-size' )
					.html( '&nbsp;' )
			);

		$container
			.append( this.$downloadButton )
			.append( this.$selectionArrow );
	};

	/**
	 * Creates pulldown menu to select image sizes.
	 *
	 * @param {jQuery} $container
	 */
	DP.createSizePulldownMenu = function ( $container ) {
		this.downloadSizeMenu = this.utils.createPulldownMenu(
			[ 'original', 'small', 'medium', 'large', 'xl' ],
			[ 'mw-mmv-download-size' ],
			'original'
		);

		this.downloadSizeMenu.getMenu().on( 'select', function ( item ) {
			mw.mmv.actionLogger.log( 'download-select-menu-' + item.data.name );
		} );

		$container.append( this.downloadSizeMenu.$element );
	};

	/**
	 * Creates preview link.
	 *
	 * @param {jQuery} $container
	 */
	DP.createPreviewLink = function ( $container ) {
		this.$previewLink = $( '<a>' )
			.attr( 'target', '_blank' )
			.addClass( 'mw-mmv-download-preview-link' )
			.text( mw.message( 'multimediaviewer-download-preview-link-title' ).text() )
			.appendTo( $container )
			.on( 'click', function () {
				mw.mmv.actionLogger.log( 'download-view-in-browser' );
			} );
	};

	DP.createAttributionButton = function ( $container ) {
		var dl = this,
			attributionInput = new mw.widgets.CopyTextLayout( {
				align: 'top',
				button: {
					label: '',
					title: mw.msg( 'multimediaviewer-download-attribution-copy' )
				}
			} ),
			attributionSwitch = new OO.ui.ButtonSelectWidget( {
				classes: [ 'mw-mmv-download-attr-select' ]
			} ),
			plainOption = new OO.ui.ButtonOptionWidget( {
				data: 'plain',
				label: mw.message( 'multimediaviewer-attr-plain' ).text()
			} ),
			htmlOption = new OO.ui.ButtonOptionWidget( {
				data: 'html',
				label: mw.message( 'multimediaviewer-attr-html' ).text()
			} );

		attributionSwitch.addItems( [
			plainOption,
			htmlOption
		] );

		attributionSwitch.selectItem( plainOption );

		attributionSwitch.on( 'select', function ( selection ) {
			dl.selectAttribution( selection.getData() );

			dl.attributionInput.selectText();
		} );

		this.$attributionSection = $( '<div>' )
			.addClass( 'mw-mmv-download-attribution mw-mmv-download-attribution-collapsed' )
			.appendTo( $container )
			.on( 'click', function () {
				if ( dl.$attributionSection.hasClass( 'mw-mmv-download-attribution-collapsed' ) ) {
					dl.$container.trigger( 'mmv-download-cta-open' );
					dl.$attributionSection.removeClass( 'mw-mmv-download-attribution-collapsed' );
					dl.attributionInput.selectText();
				}
			} );

		this.$attributionCtaHeader = $( '<p>' )
			.addClass( 'mw-mmv-download-attribution-cta-header' )
			.text( mw.message( 'multimediaviewer-download-attribution-cta-header' ).text() );
		this.$attributionCta = $( '<div>' )
			.addClass( 'mw-mmv-download-attribution-cta' )
			.append(
				this.$attributionCtaHeader,
				$( '<p>' )
					.addClass( 'mw-mmv-download-attribution-cta-invite' )
					.text( mw.message( 'multimediaviewer-download-attribution-cta' ).text() )
			)
			.appendTo( this.$attributionSection );
		this.attributionInput = attributionInput;

		this.$attributionHowHeader = $( '<p>' )
			.addClass( 'mw-mmv-download-attribution-how-header' )
			.text( mw.message( 'multimediaviewer-download-attribution-cta-header' ).text() );
		this.$attributionHow = $( '<div>' )
			.addClass( 'mw-mmv-download-attribution-how' )
			.append(
				this.$attributionHowHeader,
				this.attributionInput.$element,
				new OO.ui.FieldLayout( attributionSwitch, { align: 'top' } ).$element,
				$( '<p>' )
					.addClass( 'mw-mmv-download-attribution-close-button' )
					.on( 'click', function ( e ) {
						dl.$container.trigger( 'mmv-download-cta-close' );
						dl.$attributionSection.addClass( 'mw-mmv-download-attribution-collapsed' );
						e.stopPropagation();
					} )
					.text( ' ' )
			)
			.appendTo( this.$attributionSection );
	};

	/**
	 * Selects the specified attribution type.
	 *
	 * @param {string} [name='plain'] The attribution type to use ('plain' or 'html')
	 */
	DP.selectAttribution = function ( name ) {
		this.currentAttrView = name;

		if ( this.currentAttrView === 'html' ) {
			this.attributionInput.textInput.setValue( this.htmlCredit );
		} else {
			this.attributionInput.textInput.setValue( this.textCredit );
		}
	};

	/**
	 * Registers listeners.
	 */
	DP.attach = function () {
		var download = this;

		// Register handlers for switching between file sizes
		this.downloadSizeMenu.getMenu().on( 'choose', function ( item ) {
			download.handleSizeSwitch( item );
		} );
		this.$selectionArrow.on( 'click', function () {
			download.downloadSizeMenu.getMenu().toggle();
		} );
	};

	/**
	 * Clears listeners.
	 */
	DP.unattach = function () {
		mw.mmv.ui.Element.prototype.unattach.call( this );

		this.downloadSizeMenu.getMenu().off( 'choose' );
		this.$selectionArrow.off( 'click' );
	};

	/**
	 * Handles size menu change events.
	 *
	 * @param {OO.ui.MenuOptionWidget} item
	 */
	DP.handleSizeSwitch = function ( item ) {
		var download = this,
			value = item.getData();

		if ( value.name === 'original' && this.image !== null ) {
			this.setDownloadUrl( this.image.url );
			this.setButtonText( value.name, this.getExtensionFromUrl( this.image.url ),
				value.width, value.height );
		} else {
			// Disable download while we get the image
			this.$downloadButton.addClass( 'disabledLink' );
			// Set a temporary message. It will be updated once we have the file type.
			this.setButtonText( value.name, this.imageExtension, value.width, value.height );

			this.utils.getThumbnailUrlPromise( value.width ).done( function ( thumbnail ) {
				download.setDownloadUrl( thumbnail.url );
				download.setButtonText( value.name, download.getExtensionFromUrl( thumbnail.url ),
					value.width, value.height );
			} );
		}
	};

	/**
	 * Sets the URL on the download button.
	 *
	 * @param {string} url
	 */
	DP.setDownloadUrl = function ( url ) {
		this.$downloadButton.attr( 'href', url + '?download' );
		this.$previewLink.attr( 'href', url );

		// Re-enable download
		this.$downloadButton.removeClass( 'disabledLink' );
	};

	/**
	 * Sets the text of the download button.
	 *
	 * @param {string} sizeClass A size class such as 'small'
	 * @param {string} extension file extension
	 * @param {number} width
	 * @param {number} height
	 */
	DP.setButtonText = function ( sizeClass, extension, width, height ) {
		var sizeClassMessage, sizeMessage, dimensionMessage;

		// The following messages are used here:
		// * multimediaviewer-download-original-button-name
		// * multimediaviewer-download-small-button-name
		// * multimediaviewer-download-medium-button-name
		// * multimediaviewer-download-large-button-name
		// * multimediaviewer-download-xl-button-name
		sizeClassMessage = mw.message( 'multimediaviewer-download-' + sizeClass + '-button-name' ).text();
		dimensionMessage = mw.message( 'multimediaviewer-embed-dimensions', width, height ).text();
		sizeMessage = mw.message( 'multimediaviewer-embed-dimensions-with-file-format',
			dimensionMessage, extension ).text();

		// Update button label and size strings to reflect new selected size
		this.$downloadButton.html(
			'<span class="mw-mmv-download-image-size-name">' + sizeClassMessage + '</span>' +
			'<span class="mw-mmv-download-image-size">' + sizeMessage + '</span>'
		);
	};

	/**
	 * Sets the text in the attribution input element.
	 *
	 * @param {Object} embed
	 * @param {mw.mmv.model.Image} embed.imageInfo
	 * @param {mw.mmv.model.Repo} embed.repoInfo
	 */
	DP.setAttributionText = function ( embed ) {
		this.htmlCredit = this.formatter.getCreditHtml( embed );
		this.textCredit = this.formatter.getCreditText( embed );
		this.selectAttribution( this.currentAttrView );
	};

	/**
	 * Chops off the extension part of an URL.
	 *
	 * @param {string} url URL
	 * @return {string} Extension
	 */
	DP.getExtensionFromUrl = function ( url ) {
		var urlParts = url.split( '.' );
		return urlParts[ urlParts.length - 1 ];
	};

	/**
	 * Sets the data on the element.
	 *
	 * @param {mw.mmv.model.Image} image
	 * @param {mw.mmv.model.Repo} repo
	 */
	DP.set = function ( image, repo ) {
		var attributionCtaMessage,
			embedFileInfo,
			license = image && image.license,
			sizeOptions = this.downloadSizeMenu.getMenu().getItems(),
			sizes = this.utils.getPossibleImageSizesForHtml( image.width, image.height );

		this.image = image;

		this.utils.updateMenuOptions( sizes, sizeOptions );

		this.downloadSizeMenu.$element.addClass( 'active' );

		// Note: This extension will not be the real one for file types other than: png/gif/jpg/jpeg
		this.imageExtension = image.title.getExtension().toLowerCase();

		// Reset size menu to default item and update download button label now that we have the info
		this.downloadSizeMenu.getMenu().chooseItem( this.defaultItem );

		if ( image && repo ) {
			embedFileInfo = {
				imageInfo: image,
				repoInfo: repo
			};
			this.setAttributionText( embedFileInfo );
		}

		attributionCtaMessage = ( license && license.needsAttribution() ) ?
			'multimediaviewer-download-attribution-cta-header' :
			'multimediaviewer-download-optional-attribution-cta-header';
		// Message defined above
		// eslint-disable-next-line mediawiki/msg-doc
		this.$attributionCtaHeader.text( mw.message( attributionCtaMessage ).text() );
		// eslint-disable-next-line mediawiki/msg-doc
		this.$attributionHowHeader.text( mw.message( attributionCtaMessage ).text() );
	};

	/**
	 * @inheritdoc
	 */
	DP.empty = function () {
		this.downloadSizeMenu.getMenu().toggle( false );
		this.downloadSizeMenu.$element.removeClass( 'active' );

		this.$downloadButton.attr( 'href', '' );
		this.$previewLink.attr( 'href', '' );
		this.imageExtension = undefined;

		this.image = null;
	};

	mw.mmv.ui.download.Pane = Pane;
}() );
