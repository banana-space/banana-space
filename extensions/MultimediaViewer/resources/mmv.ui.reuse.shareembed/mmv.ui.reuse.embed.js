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
	var EP;

	/**
	 * UI component that provides the user html/wikitext snippets needed to share
	 * and/or embed a media asset.
	 *
	 * @class mw.mmv.ui.reuse.Embed
	 * @extends mw.mmv.ui.reuse.Tab
	 * @constructor
	 * @param {jQuery} $container
	 */
	function Embed( $container ) {
		mw.mmv.ui.reuse.Tab.call( this, $container );

		/**
		 * Formatter converting image data into formats needed for output
		 *
		 * @property {mw.mmv.EmbedFileFormatter}
		 */
		this.formatter = new mw.mmv.EmbedFileFormatter();

		/** @property {mw.mmv.ui.Utils} utils - */
		this.utils = new mw.mmv.ui.Utils();

		/**
		 * Indicates whether or not the default option has been reset for both size menus.
		 *
		 * @property {boolean}
		 */
		this.isSizeMenuDefaultReset = false;

		this.$pane.addClass( 'mw-mmv-embed-pane' );

		this.$pane.appendTo( this.$container );

		this.createSnippetTextAreas( this.$pane );

		this.createSnippetSelectionButtons( this.$pane );
		this.createSizePulldownMenus( this.$pane );

		/**
		 * Currently selected embed snippet.
		 *
		 * @property {mw.widgets.CopyTextLayout}
		 */
		this.currentMainEmbedText = mw.user.isAnon() ? this.embedTextHtml : this.embedTextWikitext;

		/**
		 * Default item for the html size menu.
		 *
		 * @property {OO.ui.MenuOptionWidget}
		 */
		this.defaultHtmlItem = this.embedSizeSwitchHtml.getMenu().findSelectedItem();

		/**
		 * Default item for the wikitext size menu.
		 *
		 * @property {OO.ui.MenuOptionWidget}
		 */
		this.defaultWikitextItem = this.embedSizeSwitchWikitext.getMenu().findSelectedItem();

		/**
		 * Currently selected size menu.
		 *
		 * @property {OO.ui.MenuSelectWidget}
		 */
		this.currentSizeMenu = mw.user.isAnon() ? this.embedSizeSwitchHtml.getMenu() : this.embedSizeSwitchWikitext.getMenu();

		/**
		 * Current default item.
		 *
		 * @property {OO.ui.MenuOptionWidget}
		 */
		this.currentDefaultItem = mw.user.isAnon() ? this.defaultHtmlItem : this.defaultWikitextItem;
	}
	OO.inheritClass( Embed, mw.mmv.ui.reuse.Tab );
	EP = Embed.prototype;

	/** @property {number} Width threshold at which an image is to be considered "large" */
	EP.LARGE_IMAGE_WIDTH_THRESHOLD = 1200;

	/** @property {number} Height threshold at which an image is to be considered "large" */
	EP.LARGE_IMAGE_HEIGHT_THRESHOLD = 900;

	/**
	 * Creates text areas for html and wikitext snippets.
	 *
	 * @param {jQuery} $container
	 */
	EP.createSnippetTextAreas = function ( $container ) {
		this.embedTextHtml = new mw.widgets.CopyTextLayout( {
			help: mw.message( 'multimediaviewer-embed-explanation' ).text(),
			helpInline: true,
			align: 'top',
			multiline: true,
			textInput: {
				placeholder: mw.message( 'multimediaviewer-reuse-loading-placeholder' ).text(),
				autosize: true,
				maxRows: 5
			},
			button: {
				title: mw.msg( 'multimediaviewer-reuse-copy-embed' )
			}
		} );

		this.embedTextHtml.on( 'copy', function () {
			mw.mmv.actionLogger.log( 'embed-html-copied' );
		} );

		this.embedTextWikitext = new mw.widgets.CopyTextLayout( {
			help: mw.message( 'multimediaviewer-embed-explanation' ).text(),
			helpInline: true,
			align: 'top',
			multiline: true,
			textInput: {
				// The following classes are used here:
				// * mw-editfont-monospace
				// * mw-editfont-sans-serif
				// * mw-editfont-serif
				classes: [ 'mw-editfont-' + mw.user.options.get( 'editfont' ) ],
				placeholder: mw.message( 'multimediaviewer-reuse-loading-placeholder' ).text(),
				autosize: true,
				maxRows: 5
			},
			button: {
				title: mw.msg( 'multimediaviewer-reuse-copy-embed' )
			}
		} );

		this.embedTextWikitext.on( 'copy', function () {
			mw.mmv.actionLogger.log( 'embed-wikitext-copied' );
		} );

		$container.append(
			this.embedTextHtml.$element,
			this.embedTextWikitext.$element
		);
	};

	/**
	 * Creates snippet selection buttons.
	 *
	 * @param {jQuery} $container
	 */
	EP.createSnippetSelectionButtons = function ( $container ) {
		var wikitextButtonOption,
			htmlButtonOption;

		this.embedSwitch = new OO.ui.ButtonSelectWidget( {
			classes: [ 'mw-mmv-embed-select' ]
		} );

		wikitextButtonOption = new OO.ui.ButtonOptionWidget( {
			data: 'wikitext',
			label: mw.message( 'multimediaviewer-embed-wt' ).text()
		} );
		htmlButtonOption = new OO.ui.ButtonOptionWidget( {
			data: 'html',
			label: mw.message( 'multimediaviewer-embed-html' ).text()
		} );

		this.embedSwitch.addItems( [
			wikitextButtonOption,
			htmlButtonOption
		] );

		$( '<p>' )
			.append( this.embedSwitch.$element )
			.appendTo( $container );

		// Logged-out defaults to 'html', logged-in to 'wikitext'
		this.embedSwitch.selectItem( mw.user.isAnon() ? htmlButtonOption : wikitextButtonOption );
	};

	/**
	 * Creates pulldown menus to select file sizes.
	 *
	 * @param {jQuery} $container
	 */
	EP.createSizePulldownMenus = function ( $container ) {
		// Wikitext sizes pulldown menu
		this.embedSizeSwitchWikitext = this.utils.createPulldownMenu(
			[ 'default', 'small', 'medium', 'large' ],
			[],
			'default'
		);

		this.embedSizeSwitchWikitext.getMenu().on( 'select', function ( item ) {
			mw.mmv.actionLogger.log( 'embed-select-menu-wikitext-' + item.data.name );
		} );

		// Html sizes pulldown menu
		this.embedSizeSwitchHtml = this.utils.createPulldownMenu(
			[ 'small', 'medium', 'large', 'original' ],
			[],
			'original'
		);

		this.embedSizeSwitchHtml.getMenu().on( 'select', function ( item ) {
			mw.mmv.actionLogger.log( 'embed-select-menu-html-' + item.data.name );
		} );

		this.embedSizeSwitchHtmlLayout = new OO.ui.FieldLayout( this.embedSizeSwitchHtml, { align: 'top' } );
		this.embedSizeSwitchWikitextLayout = new OO.ui.FieldLayout( this.embedSizeSwitchWikitext, { align: 'top' } );

		$container.append(
			this.embedSizeSwitchHtmlLayout.$element,
			this.embedSizeSwitchWikitextLayout.$element
		);
	};

	/**
	 * Registers listeners.
	 */
	EP.attach = function () {
		// Register handler for switching between wikitext/html snippets
		this.embedSwitch.on( 'select', this.handleTypeSwitch.bind( this ) );

		this.handleTypeSwitch( this.embedSwitch.findSelectedItem() );

		// Register handlers for switching between file sizes
		this.embedSizeSwitchHtml.getMenu().on( 'choose', this.handleSizeSwitch.bind( this ) );
		this.embedSizeSwitchWikitext.getMenu().on( 'choose', this.handleSizeSwitch.bind( this ) );
	};

	/**
	 * Clears listeners.
	 */
	EP.unattach = function () {
		mw.mmv.ui.reuse.Tab.prototype.unattach.call( this );

		this.embedSwitch.off( 'select' );
		this.embedSizeSwitchHtml.getMenu().off( 'choose' );
		this.embedSizeSwitchWikitext.getMenu().off( 'choose' );
	};

	/**
	 * Handles size menu change events.
	 *
	 * @param {OO.ui.MenuOptionWidget} item
	 */
	EP.handleSizeSwitch = function ( item ) {
		var value = item.getData();

		this.changeSize( value.width, value.height );
	};

	/**
	 * Handles snippet type switch.
	 *
	 * @param {OO.ui.MenuOptionWidget} item
	 */
	EP.handleTypeSwitch = function ( item ) {
		var value = item.getData();

		mw.mmv.actionLogger.log( 'embed-switched-to-' + value );

		if ( value === 'html' ) {
			this.currentMainEmbedText = this.embedTextHtml;
			this.embedSizeSwitchWikitext.getMenu().toggle( false );

			this.currentSizeMenu = this.embedSizeSwitchHtml.getMenu();
			this.currentDefaultItem = this.defaultHtmlItem;
		} else if ( value === 'wikitext' ) {
			this.currentMainEmbedText = this.embedTextWikitext;
			this.embedSizeSwitchHtml.getMenu().toggle( false );

			this.currentSizeMenu = this.embedSizeSwitchWikitext.getMenu();
			this.currentDefaultItem = this.defaultWikitextItem;
		}

		this.embedTextHtml.toggle( value === 'html' );
		this.embedSizeSwitchHtmlLayout.toggle( value === 'html' );

		this.embedTextWikitext.toggle( value === 'wikitext' );
		this.embedSizeSwitchWikitextLayout.toggle( value === 'wikitext' );

		// Reset current selection to default when switching the first time
		if ( !this.isSizeMenuDefaultReset ) {
			this.resetCurrentSizeMenuToDefault();
			this.isSizeMenuDefaultReset = true;
		}

		this.select();
	};

	/**
	 * Reset current menu selection to default item.
	 */
	EP.resetCurrentSizeMenuToDefault = function () {
		this.currentSizeMenu.chooseItem( this.currentDefaultItem );
		// Force select logic to update the selected item bar, otherwise we end up
		// with the wrong label. This is implementation dependent and maybe it should
		// be done via a to flag to OO.ui.SelectWidget.prototype.chooseItem()?
		this.currentSizeMenu.emit( 'select', this.currentDefaultItem );
	};

	/**
	 * Changes the size, takes different actions based on which sort of
	 * embed is currently chosen.
	 *
	 * @param {number} width New width to set
	 * @param {number} height New height to set
	 */
	EP.changeSize = function ( width, height ) {
		var currentItem = this.embedSwitch.findSelectedItem();

		if ( currentItem === null ) {
			return;
		}

		switch ( currentItem.getData() ) {
			case 'html':
				this.updateEmbedHtml( {}, width, height );
				break;
			case 'wikitext':
				this.updateEmbedWikitext( width );
				break;
		}

		this.select();
	};

	/**
	 * Sets the HTML embed text.
	 *
	 * Assumes that the set() method has already been called to update this.embedFileInfo
	 *
	 * @param {mw.mmv.model.Thumbnail} thumbnail (can be just an empty object)
	 * @param {number} width New width to set
	 * @param {number} height New height to set
	 */
	EP.updateEmbedHtml = function ( thumbnail, width, height ) {
		var src;

		if ( !this.embedFileInfo ) {
			return;
		}

		src = thumbnail.url || this.embedFileInfo.imageInfo.url;

		// If the image dimension requested are "large", use the current image url
		if ( width > EP.LARGE_IMAGE_WIDTH_THRESHOLD || height > EP.LARGE_IMAGE_HEIGHT_THRESHOLD ) {
			src = this.embedFileInfo.imageInfo.url;
		}

		this.embedTextHtml.textInput.setValue(
			this.formatter.getThumbnailHtml( this.embedFileInfo, src, width, height )
		);
	};

	/**
	 * Updates the wikitext embed text with a new value for width.
	 *
	 * Assumes that the set method has already been called.
	 *
	 * @param {number} width
	 */
	EP.updateEmbedWikitext = function ( width ) {
		if ( !this.embedFileInfo ) {
			return;
		}

		this.embedTextWikitext.textInput.setValue(
			this.formatter.getThumbnailWikitextFromEmbedFileInfo( this.embedFileInfo, width )
		);
	};

	/**
	 * Shows the pane.
	 */
	EP.show = function () {
		mw.mmv.ui.reuse.Tab.prototype.show.call( this );

		// Force update size on multiline inputs, as they may have be
		// calculated while not visible.
		this.currentMainEmbedText.textInput.valCache = null;
		this.currentMainEmbedText.textInput.adjustSize();

		this.select();
	};

	/**
	 * Gets size options for html and wikitext snippets.
	 *
	 * @param {number} width
	 * @param {number} height
	 * @return {Object}
	 * @return {Object} return.html Collection of possible image sizes for html snippets
	 * @return {Object} return.wikitext Collection of possible image sizes for wikitext snippets
	 */
	EP.getSizeOptions = function ( width, height ) {
		var sizes = {};

		sizes.html = this.utils.getPossibleImageSizesForHtml( width, height );
		sizes.wikitext = this.getPossibleImageSizesForWikitext( width, height );

		return sizes;
	};

	/**
	 * Sets the data on the element.
	 *
	 * @param {mw.mmv.model.Image} image
	 * @param {mw.mmv.model.Repo} repo
	 * @param {string} [caption]
	 * @param {string} [alt]
	 */
	EP.set = function ( image, repo, caption, alt ) {
		var embed = this,
			htmlSizeSwitch = this.embedSizeSwitchHtml.getMenu(),
			htmlSizeOptions = htmlSizeSwitch.getItems(),
			wikitextSizeSwitch = this.embedSizeSwitchWikitext.getMenu(),
			wikitextSizeOptions = wikitextSizeSwitch.getItems(),
			sizes = this.getSizeOptions( image.width, image.height );

		this.embedFileInfo = { imageInfo: image, repoInfo: repo };
		if ( caption ) { this.embedFileInfo.caption = caption; }
		if ( alt ) { this.embedFileInfo.alt = alt; }

		this.utils.updateMenuOptions( sizes.html, htmlSizeOptions );
		this.utils.updateMenuOptions( sizes.wikitext, wikitextSizeOptions );

		// Reset defaults
		this.isSizeMenuDefaultReset = false;
		this.resetCurrentSizeMenuToDefault();

		this.utils.getThumbnailUrlPromise( this.LARGE_IMAGE_WIDTH_THRESHOLD )
			.done( function ( thumbnail ) {
				embed.updateEmbedHtml( thumbnail );
				embed.select();
			} );
	};

	/**
	 * @inheritdoc
	 */
	EP.empty = function () {
		this.embedTextHtml.textInput.setValue( '' );
		this.embedTextWikitext.textInput.setValue( '' );

		this.embedSizeSwitchHtml.getMenu().toggle( false );
		this.embedSizeSwitchWikitext.getMenu().toggle( false );
	};

	/**
	 * Selects the text in the current textbox by triggering a focus event.
	 */
	EP.select = function () {
		this.currentMainEmbedText.selectText();
	};

	/**
	 * Calculates possible image sizes for wikitext snippets. It returns up to
	 * three possible snippet frame sizes (small, medium, large).
	 *
	 * @param {number} width
	 * @param {number} height
	 * @return {Object}
	 * @return {Object} return.small
	 * @return {Object} return.medium
	 * @return {Object} return.large
	 */
	EP.getPossibleImageSizesForWikitext = function ( width, height ) {
		var i, bucketName,
			bucketWidth,
			buckets = {
				small: 300,
				medium: 400,
				large: 500
			},
			sizes = {},
			bucketNames = Object.keys( buckets ),
			widthToHeight = height / width;

		for ( i = 0; i < bucketNames.length; i++ ) {
			bucketName = bucketNames[ i ];
			bucketWidth = buckets[ bucketName ];

			if ( width > bucketWidth ) {
				sizes[ bucketName ] = {
					width: bucketWidth,
					height: Math.round( bucketWidth * widthToHeight )
				};
			}
		}

		sizes.default = { width: null, height: null };

		return sizes;
	};

	mw.mmv.ui.reuse.Embed = Embed;
}() );
