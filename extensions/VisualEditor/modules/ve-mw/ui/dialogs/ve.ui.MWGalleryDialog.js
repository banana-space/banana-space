/*!
 * VisualEditor user interface MWGalleryDialog class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for editing MediaWiki galleries.
 *
 * @class
 * @extends ve.ui.NodeDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWGalleryDialog = function VeUiMWGalleryDialog() {
	// Parent constructor
	ve.ui.MWGalleryDialog.super.apply( this, arguments );

	this.$element.addClass( 've-ui-mwGalleryDialog' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWGalleryDialog, ve.ui.NodeDialog );

/* Static properties */

ve.ui.MWGalleryDialog.static.name = 'gallery';

ve.ui.MWGalleryDialog.static.size = 'large';

ve.ui.MWGalleryDialog.static.title =
	OO.ui.deferMsg( 'visualeditor-mwgallerydialog-title' );

ve.ui.MWGalleryDialog.static.modelClasses = [ ve.dm.MWGalleryNode ];

ve.ui.MWGalleryDialog.static.includeCommands = null;

ve.ui.MWGalleryDialog.static.excludeCommands = [
	// No formatting
	'paragraph',
	'heading1',
	'heading2',
	'heading3',
	'heading4',
	'heading5',
	'heading6',
	'preformatted',
	'blockquote',
	// No block-level markup is allowed inside gallery caption (or gallery image captions)
	// No tables
	'insertTable',
	'deleteTable',
	'mergeCells',
	'tableCaption',
	'tableCellHeader',
	'tableCellData',
	// No structure
	'bullet',
	'bulletWrapOnce',
	'number',
	'numberWrapOnce',
	'indent',
	'outdent',
	// Nested galleries don't work either
	'gallery'
];

/**
 * Get the import rules for the surface widget in the dialog
 *
 * @see ve.dm.ElementLinearData#sanitize
 * @return {Object} Import rules
 */
ve.ui.MWGalleryDialog.static.getImportRules = function () {
	var rules = ve.copy( ve.init.target.constructor.static.importRules );
	return ve.extendObject(
		rules,
		{
			all: {
				blacklist: ve.extendObject(
					{
						// No block-level markup is allowed inside gallery caption (or gallery image captions).
						// No lists, no tables.
						list: true,
						listItem: true,
						definitionList: true,
						definitionListItem: true,
						table: true,
						tableCaption: true,
						tableSection: true,
						tableRow: true,
						tableCell: true,
						mwTable: true,
						mwTransclusionTableCell: true,
						// Nested galleries don't work either
						mwGallery: true
					},
					ve.getProp( rules, 'all', 'blacklist' )
				),
				// Headings are also possible, but discouraged
				conversions: ve.extendObject(
					{
						mwHeading: 'paragraph'
					},
					ve.getProp( rules, 'all', 'conversions' )
				)
			}
		}
	);
};

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWGalleryDialog.prototype.initialize = function () {
	var imagesTabPanel, optionsTabPanel,
		imageListMenuLayout, imageListMenuPanel, imageListContentPanel,
		modeField, captionField, widthsField, heightsField,
		perrowField, showFilenameField, classesField, stylesField,
		highlightedCaptionField, highlightedCaptionFieldset,
		highlightedAltTextField, highlightedAltTextFieldset;

	// Parent method
	ve.ui.MWGalleryDialog.super.prototype.initialize.call( this );

	// States
	this.highlightedItem = null;
	this.searchPanelVisible = false;
	this.selectedFilenames = {};
	this.initialImageData = [];
	this.originalMwDataNormalized = null;
	this.originalGalleryGroupItems = [];
	this.imageData = {};
	this.isMobile = OO.ui.isMobile();

	// Default settings
	this.defaults = mw.config.get( 'wgVisualEditorConfig' ).galleryOptions;

	// Images and options tab panels
	this.indexLayout = new OO.ui.IndexLayout();
	imagesTabPanel = new OO.ui.TabPanelLayout( 'images', {
		label: ve.msg( 'visualeditor-mwgallerydialog-card-images' ),
		// Contains a menu layout which handles its own scrolling
		scrollable: false,
		padded: true
	} );
	optionsTabPanel = new OO.ui.TabPanelLayout( 'options', {
		label: ve.msg( 'visualeditor-mwgallerydialog-card-options' ),
		padded: true
	} );

	// Images tab panel

	// General layout
	imageListContentPanel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: true,
		scrollable: true
	} );
	imageListMenuPanel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: true
	} );
	imageListMenuLayout = new OO.ui.MenuLayout( {
		menuPosition: this.isMobile ? 'after' : 'bottom',
		classes: [
			've-ui-mwGalleryDialog-imageListMenuLayout',
			this.isMobile ?
				've-ui-mwGalleryDialog-imageListMenuLayout-mobile' :
				've-ui-mwGalleryDialog-imageListMenuLayout-desktop'
		],
		contentPanel: imageListContentPanel,
		menuPanel: imageListMenuPanel
	} );
	this.editPanel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: true,
		scrollable: true
	} );
	this.searchPanel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: true,
		scrollable: true
	} ).toggle( false );
	this.editSearchStack = new OO.ui.StackLayout( {
		items: [ this.editPanel, this.searchPanel ]
	} );
	this.imageTabMenuLayout = new OO.ui.MenuLayout( {
		menuPosition: this.isMobile ? 'top' : 'before',
		classes: [
			've-ui-mwGalleryDialog-menuLayout',
			this.isMobile ?
				've-ui-mwGalleryDialog-menuLayout-mobile' :
				've-ui-mwGalleryDialog-menuLayout-desktop'
		],
		menuPanel: imageListMenuLayout,
		contentPanel: this.editSearchStack
	} );

	// Menu
	this.$emptyGalleryMessage = $( '<div>' )
		.addClass( 'oo-ui-element-hidden' )
		.text( ve.msg( 'visualeditor-mwgallerydialog-empty-gallery-message' ) );
	this.galleryGroup = new ve.ui.MWGalleryGroupWidget( {
		orientation: this.isMobile ? 'horizontal' : 'vertical'
	} );
	this.showSearchPanelButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'visualeditor-mwgallerydialog-search-button-label' ),
		invisibleLabel: !!this.isMobile,
		icon: 'add',
		framed: false,
		flags: [ 'progressive' ],
		classes: [ 've-ui-mwGalleryDialog-show-search-panel-button' ]
	} );

	// Edit panel
	this.filenameFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'visualeditor-dialog-media-content-filename' ),
		icon: 'image'
	} );
	this.$highlightedImage = $( '<div>' )
		.addClass( 've-ui-mwGalleryDialog-highlighted-image' );
	this.filenameFieldset.$element.append( this.$highlightedImage );
	this.highlightedCaptionTarget = ve.init.target.createTargetWidget( {
		includeCommands: this.constructor.static.includeCommands,
		excludeCommands: this.constructor.static.excludeCommands,
		importRules: this.constructor.static.getImportRules(),
		multiline: false
	} );
	this.highlightedAltTextInput = new OO.ui.TextInputWidget( {
		placeholder: ve.msg( 'visualeditor-dialog-media-alttext-section' )
	} );
	this.removeButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'visualeditor-mwgallerydialog-remove-button-label' ),
		icon: 'trash',
		flags: [ 'destructive' ],
		classes: [ 've-ui-mwGalleryDialog-remove-button' ]
	} );

	highlightedCaptionField = new OO.ui.FieldLayout( this.highlightedCaptionTarget, {
		align: 'top'
	} );
	highlightedCaptionFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'visualeditor-dialog-media-content-section' )
	} );
	highlightedCaptionFieldset.addItems( highlightedCaptionField );

	highlightedAltTextField = new OO.ui.FieldLayout( this.highlightedAltTextInput, {
		align: 'top'
	} );
	highlightedAltTextFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'visualeditor-dialog-media-alttext-section' )
	} );
	highlightedAltTextFieldset.addItems( highlightedAltTextField );

	// Search panel
	this.searchWidget = new mw.widgets.MediaSearchWidget( {
		rowHeight: this.isMobile ? 100 : 150
	} );

	// Options tab panel

	// Input widgets
	this.modeDropdown = new OO.ui.DropdownWidget( {
		menu: {
			items: [
				new OO.ui.MenuOptionWidget( {
					data: 'traditional',
					label: ve.msg( 'visualeditor-mwgallerydialog-mode-dropdown-label-traditional' )
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'nolines',
					label: ve.msg( 'visualeditor-mwgallerydialog-mode-dropdown-label-nolines' )
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'packed',
					label: ve.msg( 'visualeditor-mwgallerydialog-mode-dropdown-label-packed' )
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'packed-overlay',
					label: ve.msg( 'visualeditor-mwgallerydialog-mode-dropdown-label-packed-overlay' )
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'packed-hover',
					label: ve.msg( 'visualeditor-mwgallerydialog-mode-dropdown-label-packed-hover' )
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'slideshow',
					label: ve.msg( 'visualeditor-mwgallerydialog-mode-dropdown-label-slideshow' )
				} )
			]
		}
	} );
	this.captionTarget = ve.init.target.createTargetWidget( {
		includeCommands: this.constructor.static.includeCommands,
		excludeCommands: this.constructor.static.excludeCommands,
		importRules: this.constructor.static.getImportRules(),
		multiline: false
	} );
	this.widthsInput = new OO.ui.NumberInputWidget( {
		min: 0,
		showButtons: false,
		input: {
			placeholder: ve.msg( 'visualeditor-mwgallerydialog-widths-input-placeholder', this.defaults.imageWidth )
		}
	} );
	this.heightsInput = new OO.ui.NumberInputWidget( {
		min: 0,
		showButtons: false,
		input: {
			placeholder: ve.msg( 'visualeditor-mwgallerydialog-heights-input-placeholder', this.defaults.imageHeight )
		}
	} );
	this.perrowInput = new OO.ui.NumberInputWidget( {
		min: 0,
		showButtons: false
	} );
	this.showFilenameCheckbox = new OO.ui.CheckboxInputWidget( {
		value: 'yes'
	} );
	this.classesInput = new OO.ui.TextInputWidget( {
		placeholder: ve.msg( 'visualeditor-mwgallerydialog-classes-input-placeholder' )
	} );
	this.stylesInput = new OO.ui.TextInputWidget( {
		placeholder: ve.msg( 'visualeditor-mwgallerydialog-styles-input-placeholder' )
	} );

	// Field layouts
	modeField = new OO.ui.FieldLayout( this.modeDropdown, {
		label: ve.msg( 'visualeditor-mwgallerydialog-mode-field-label' )
	} );
	captionField = new OO.ui.FieldLayout( this.captionTarget, {
		label: ve.msg( 'visualeditor-mwgallerydialog-caption-field-label' ),
		align: this.isMobile ? 'top' : 'left'
	} );
	widthsField = new OO.ui.FieldLayout( this.widthsInput, {
		label: ve.msg( 'visualeditor-mwgallerydialog-widths-field-label' )
	} );
	heightsField = new OO.ui.FieldLayout( this.heightsInput, {
		label: ve.msg( 'visualeditor-mwgallerydialog-heights-field-label' )
	} );
	perrowField = new OO.ui.FieldLayout( this.perrowInput, {
		label: ve.msg( 'visualeditor-mwgallerydialog-perrow-field-label' )
	} );
	showFilenameField = new OO.ui.FieldLayout( this.showFilenameCheckbox, {
		label: ve.msg( 'visualeditor-mwgallerydialog-show-filename-field-label' )
	} );
	classesField = new OO.ui.FieldLayout( this.classesInput, {
		label: ve.msg( 'visualeditor-mwgallerydialog-classes-field-label' )
	} );
	stylesField = new OO.ui.FieldLayout( this.stylesInput, {
		label: ve.msg( 'visualeditor-mwgallerydialog-styles-field-label' )
	} );

	// Append everything
	imageListMenuPanel.$element.append(
		this.showSearchPanelButton.$element
	);
	imageListContentPanel.$element.append(
		this.$emptyGalleryMessage,
		this.galleryGroup.$element
	);
	this.editPanel.$element.append(
		this.filenameFieldset.$element,
		highlightedCaptionFieldset.$element,
		highlightedAltTextFieldset.$element,
		this.removeButton.$element
	);
	this.searchPanel.$element.append(
		this.searchWidget.$element
	);
	imagesTabPanel.$element.append(
		this.imageTabMenuLayout.$element
	);
	optionsTabPanel.$element.append(
		modeField.$element,
		captionField.$element,
		widthsField.$element,
		heightsField.$element,
		perrowField.$element,
		showFilenameField.$element,
		classesField.$element,
		stylesField.$element
	);
	this.indexLayout.addTabPanels( [
		imagesTabPanel,
		optionsTabPanel
	] );
	this.$body.append( this.indexLayout.$element );
};

/**
 * @inheritdoc
 */
ve.ui.MWGalleryDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWGalleryDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var i, ilen, resource, imageTitles,
				image, imageCaptionNode,
				mode, widths, heights, perrow,
				showFilename, classes, styles,
				namespaceIds = mw.config.get( 'wgNamespaceIds' ),
				dialog = this,
				mwData = this.selectedNode && this.selectedNode.getAttribute( 'mw' ),
				attributes = mwData && mwData.attrs,
				captionNode = this.selectedNode && this.selectedNode.getCaptionNode(),
				imageNodes = this.selectedNode && this.selectedNode.getImageNodes(),
				isReadOnly = this.isReadOnly();

			this.anyItemModified = false;

			// Images tab panel
			// If editing an existing gallery, populate with the images...
			if ( this.selectedNode ) {
				imageTitles = [];

				for ( i = 0, ilen = imageNodes.length; i < ilen; i++ ) {
					image = imageNodes[ i ];
					resource = mw.Title.newFromText( image.getAttribute( 'resource' ), namespaceIds.file ).getPrefixedText();
					imageCaptionNode = image.getCaptionNode();
					imageTitles.push( resource );
					this.initialImageData.push( {
						resource: resource,
						altText: image.getAttribute( 'altText' ),
						src: image.getAttribute( 'src' ),
						height: image.getAttribute( 'height' ),
						width: image.getAttribute( 'width' ),
						captionDocument: dialog.createCaptionDocument( imageCaptionNode )
					} );
				}

				// Populate menu and edit panels
				this.imagesPromise = this.requestImages( {
					titles: imageTitles
				} ).done( function () {
					dialog.onHighlightItem();
				} );

			// ...Otherwise show the search panel
			} else {
				this.toggleEmptyGalleryMessage( true );
				this.showSearchPanelButton.toggle( false );
				this.toggleSearchPanel( true );
			}

			// Options tab panel

			// Set options
			mode = attributes && attributes.mode || this.defaults.mode;
			widths = attributes && parseInt( attributes.widths ) || '';
			heights = attributes && parseInt( attributes.heights ) || '';
			perrow = attributes && attributes.perrow || '';
			showFilename = attributes && attributes.showfilename === 'yes';
			classes = attributes && attributes.class || '';
			styles = attributes && attributes.style || '';
			// Caption
			this.captionDocument = this.createCaptionDocument( captionNode );

			// Populate options panel
			this.modeDropdown.getMenu().selectItemByData( mode );
			this.widthsInput.setValue( widths );
			this.heightsInput.setValue( heights );
			this.perrowInput.setValue( perrow );
			this.showFilenameCheckbox.setSelected( showFilename );
			this.classesInput.setValue( classes );
			this.stylesInput.setValue( styles );
			// Caption
			this.captionTarget.setDocument( this.captionDocument );
			this.captionTarget.setReadOnly( isReadOnly );

			if ( mwData ) {
				this.originalMwDataNormalized = ve.copy( mwData );
				this.updateMwData( this.originalMwDataNormalized );
			}

			this.highlightedAltTextInput.setReadOnly( isReadOnly );
			this.modeDropdown.setDisabled( isReadOnly );
			this.widthsInput.setReadOnly( isReadOnly );
			this.heightsInput.setReadOnly( isReadOnly );
			this.perrowInput.setReadOnly( isReadOnly );
			this.showFilenameCheckbox.setDisabled( isReadOnly );
			this.classesInput.setReadOnly( isReadOnly );
			this.stylesInput.setReadOnly( isReadOnly );

			this.showSearchPanelButton.setDisabled( isReadOnly );
			this.removeButton.setDisabled( isReadOnly );

			this.galleryGroup.toggleDraggable( !isReadOnly );

			// Disable fields depending on mode
			this.onModeDropdownChange();

			// Add event handlers
			this.indexLayout.connect( this, { set: 'updateDialogSize' } );
			this.searchWidget.getResults().connect( this, { choose: 'onSearchResultsChoose' } );
			this.showSearchPanelButton.connect( this, { click: 'onShowSearchPanelButtonClick' } );
			this.galleryGroup.connect( this, { editItem: 'onHighlightItem' } );
			this.galleryGroup.connect( this, { change: 'updateActions' } );
			this.removeButton.connect( this, { click: 'onRemoveItem' } );
			this.modeDropdown.getMenu().connect( this, { choose: 'onModeDropdownChange' } );
			this.widthsInput.connect( this, { change: 'updateActions' } );
			this.heightsInput.connect( this, { change: 'updateActions' } );
			this.perrowInput.connect( this, { change: 'updateActions' } );
			this.showFilenameCheckbox.connect( this, { change: 'updateActions' } );
			this.classesInput.connect( this, { change: 'updateActions' } );
			this.stylesInput.connect( this, { change: 'updateActions' } );
			this.captionTarget.connect( this, { change: 'updateActions' } );
			this.highlightedAltTextInput.connect( this, { change: 'updateActions' } );
			this.highlightedCaptionTarget.connect( this, { change: 'updateActions' } );

			return this.imagesPromise;
		}, this );
};

/**
 * Get a new caption document for the gallery caption or an image caption.
 *
 * @private
 * @param {ve.dm.MWGalleryCaptionNode|ve.dm.MWGalleryImageCaptionNode|null} captionNode
 * @return {ve.dm.Document}
 */
ve.ui.MWGalleryDialog.prototype.createCaptionDocument = function ( captionNode ) {
	if ( captionNode && captionNode.getLength() > 0 ) {
		return this.selectedNode.getDocument().cloneFromRange( captionNode.getRange() );
	} else {
		return this.getFragment().getDocument().cloneWithData( [
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			{ type: '/paragraph' },
			{ type: 'internalList' },
			{ type: '/internalList' }
		] );
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWGalleryDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWGalleryDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			this.searchWidget.getQuery().focus().select();
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWGalleryDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWGalleryDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			// Layouts
			this.indexLayout.setTabPanel( 'images' );
			this.indexLayout.resetScroll();
			this.imageTabMenuLayout.resetScroll();

			// Widgets
			this.galleryGroup.clearItems();
			this.searchWidget.getQuery().setValue( '' );
			this.searchWidget.teardown();

			// States
			this.highlightedItem = null;
			this.searchPanelVisible = false;
			this.selectedFilenames = {};
			this.initialImageData = [];
			this.originalMwDataNormalized = null;
			this.originalGalleryGroupItems = [];

			// Disconnect events
			this.indexLayout.disconnect( this );
			this.searchWidget.getResults().disconnect( this );
			this.showSearchPanelButton.disconnect( this );
			this.galleryGroup.disconnect( this );
			this.removeButton.disconnect( this );
			this.modeDropdown.disconnect( this );
			this.widthsInput.disconnect( this );
			this.heightsInput.disconnect( this );
			this.perrowInput.disconnect( this );
			this.showFilenameCheckbox.disconnect( this );
			this.classesInput.disconnect( this );
			this.stylesInput.disconnect( this );
			this.highlightedAltTextInput.disconnect( this );
			this.captionTarget.disconnect( this );
			this.highlightedCaptionTarget.disconnect( this );

		}, this );
};

ve.ui.MWGalleryDialog.prototype.getActionProcess = function ( action ) {
	return ve.ui.MWGalleryDialog.super.prototype.getActionProcess.call( this, action )
		.next( function () {
			if ( action === 'done' ) {
				// Save the input values for the highlighted item
				this.updateHighlightedItem();

				this.insertOrUpdateNode();
				this.close( { action: 'done' } );
			}
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWGalleryDialog.prototype.getBodyHeight = function () {
	return 600;
};

/**
 * Request the images for the images tab panel menu
 *
 * @param {Object} options Options for the request
 * @return {jQuery.Promise} Promise which resolves when image data has been fetched
 */
ve.ui.MWGalleryDialog.prototype.requestImages = function ( options ) {
	var i, len,
		dialog = this,
		promises = [];
	for ( i = 0, len = options.titles.length; i < len; i++ ) {
		promises.push( ve.init.platform.galleryImageInfoCache.get( options.titles[ i ] ) );
	}
	return ve.promiseAll( promises )
		.done( function () {
			var resp = {};
			for ( i = 0; i < len; i++ ) {
				resp[ options.titles[ i ] ] = arguments[ i ];
			}
			dialog.onRequestImagesSuccess( resp );
		} );
};

/**
 * Create items for the returned images and add them to the gallery group
 *
 * @param {Object} response jQuery response object
 */
ve.ui.MWGalleryDialog.prototype.onRequestImagesSuccess = function ( response ) {
	var title,
		thumbUrls = {},
		items = [],
		config = { isMobile: this.isMobile, draggable: !this.isReadOnly() };

	for ( title in response ) {
		thumbUrls[ title ] = {
			thumbUrl: response[ title ].thumburl,
			width: response[ title ].thumbwidth,
			height: response[ title ].thumbheight
		};
	}

	if ( this.initialImageData.length > 0 ) {
		this.initialImageData.forEach( function ( image ) {
			image.thumbUrl = thumbUrls[ image.resource ].thumbUrl;
			items.push( new ve.ui.MWGalleryItemWidget( image, config ) );
		} );
		this.initialImageData = [];
		this.originalGalleryGroupItems = ve.copy( items );
	} else {
		for ( title in this.selectedFilenames ) {
			if ( Object.prototype.hasOwnProperty.call( thumbUrls, title ) ) {
				items.push( new ve.ui.MWGalleryItemWidget( {
					resource: title,
					altText: '',
					src: '',
					height: thumbUrls[ title ].height,
					width: thumbUrls[ title ].width,
					thumbUrl: thumbUrls[ title ].thumbUrl,
					captionDocument: this.createCaptionDocument( null )
				}, config ) );
				delete this.selectedFilenames[ title ];
			}
		}
	}

	this.galleryGroup.addItems( items );

	// Gallery is no longer empty
	this.updateActions();
	this.toggleEmptyGalleryMessage( false );
	this.showSearchPanelButton.toggle( true );
};

/**
 * Request a new image and highlight it
 *
 * @param {string} title Normalized title of the new image
 */
ve.ui.MWGalleryDialog.prototype.addNewImage = function ( title ) {
	var dialog = this;

	// Make list of unique pending images, for onRequestImagesSuccess
	this.selectedFilenames[ title ] = true;

	// Request image
	this.requestImages( {
		titles: [ title ]
	} ).done( function () {

		// populate edit panel with the new image
		var items = dialog.galleryGroup.items;
		dialog.onHighlightItem( items[ items.length - 1 ] );
		dialog.highlightedCaptionTarget.focus();
	} );
};

/**
 * Update the image currently being edited (ve.ui.MWGalleryItemWidget) with the values from inputs
 * in this dialog (currently only the image caption).
 */
ve.ui.MWGalleryDialog.prototype.updateHighlightedItem = function () {
	this.anyItemModified = this.anyItemModified || this.isHighlightedItemModified();

	// TODO: Support link, page and lang
	if ( this.highlightedItem ) {
		// No need to call setCaptionDocument(), the document object is updated on every change
		this.highlightedItem.setAltText( this.highlightedAltTextInput.getValue() );
	}
};

/**
 * Handle search results choose event.
 *
 * @param {mw.widgets.MediaResultWidget} item Chosen item
 */
ve.ui.MWGalleryDialog.prototype.onSearchResultsChoose = function ( item ) {
	var title = mw.Title.newFromText( item.getData().title ).getPrefixedText();

	// Check title against pending insertions
	// TODO: Prevent two 'choose' events firing from the UI
	if ( !Object.prototype.hasOwnProperty.call( this.selectedFilenames, title ) ) {
		this.addNewImage( title );
	}

	this.updateActions();
};

/**
 * Handle click event for the remove button
 */
ve.ui.MWGalleryDialog.prototype.onRemoveItem = function () {
	// Remove the highlighted item
	this.galleryGroup.removeItems( [ this.highlightedItem ] );

	// Highlight another item, or show the search panel if the gallery is now empty
	this.onHighlightItem();
};

/**
 * Handle clicking on an image in the menu
 *
 * @param {ve.ui.MWGalleryItemWidget} [item] The item that was clicked on
 */
ve.ui.MWGalleryDialog.prototype.onHighlightItem = function ( item ) {
	var title;

	// Unhighlight previous item
	if ( this.highlightedItem ) {
		this.highlightedItem.toggleHighlighted( false );
	}

	// Show edit panel
	// (This also calls updateHighlightedItem() to save the input values.)
	this.toggleSearchPanel( false );

	// Highlight new item.
	// If no item was given, highlight the first item in the gallery.
	item = item || this.galleryGroup.items[ 0 ];

	if ( !item ) {
		// Show the search panel if the gallery is empty
		this.toggleEmptyGalleryMessage( true );
		this.showSearchPanelButton.toggle( false );
		this.toggleSearchPanel( true );
		return;
	}

	item.toggleHighlighted( true );
	this.highlightedItem = item;

	// Scroll item into view in menu
	OO.ui.Element.static.scrollIntoView( item.$element[ 0 ] );

	// Populate edit panel
	title = mw.Title.newFromText( item.resource );
	this.filenameFieldset.setLabel(
		$( '<span>' ).append(
			document.createTextNode( title.getMainText() + ' ' ),
			$( '<a>' )
				.addClass( 've-ui-mwMediaDialog-description-link' )
				.attr( 'href', title.getUrl() )
				.attr( 'target', '_blank' )
				.attr( 'rel', 'noopener' )
				.text( ve.msg( 'visualeditor-dialog-media-content-description-link' ) )
		)
	);
	this.$highlightedImage
		.css( 'background-image', 'url(' + item.thumbUrl + ')' );
	this.highlightedCaptionTarget.setDocument( item.captionDocument );
	this.highlightedCaptionTarget.setReadOnly( this.isReadOnly() );
	this.highlightedAltTextInput.setValue( item.altText );
};

/**
 * Handle change event for this.modeDropdown
 */
ve.ui.MWGalleryDialog.prototype.onModeDropdownChange = function () {
	var mode = this.modeDropdown.getMenu().findSelectedItem().getData(),
		disabled = (
			mode === 'packed' ||
			mode === 'packed-overlay' ||
			mode === 'packed-hover' ||
			mode === 'slideshow'
		);

	this.widthsInput.setDisabled( disabled );
	this.perrowInput.setDisabled( disabled );

	// heights is only ignored in slideshow mode
	this.heightsInput.setDisabled( mode === 'slideshow' );

	this.updateActions();
};

/**
 * Handle click event for showSearchPanelButton
 */
ve.ui.MWGalleryDialog.prototype.onShowSearchPanelButtonClick = function () {
	this.toggleSearchPanel( true );
};

/**
 * Toggle the search panel (and the edit panel, the opposite way)
 *
 * @param {boolean} visible The search panel is visible
 */
ve.ui.MWGalleryDialog.prototype.toggleSearchPanel = function ( visible ) {
	visible = visible !== undefined ? visible : !this.searchPanelVisible;

	// If currently visible panel is an edit panel, save the input values for the highlighted item
	if ( !this.searchPanelVisible ) {
		this.updateHighlightedItem();
	}

	// Record the state of the search panel
	this.searchPanelVisible = visible;

	// Toggle the search panel, and do the opposite for the edit panel
	this.editSearchStack.setItem( visible ? this.searchPanel : this.editPanel );

	// If the edit panel is visible, focus the caption target
	if ( !visible ) {
		this.highlightedCaptionTarget.focus();
	} else {
		// Try to populate with user uploads
		this.searchWidget.queryMediaQueue();
		this.searchWidget.getQuery().focus().select();
	}
	this.updateDialogSize();
};

/**
 * Resize the dialog according to which panel is focused
 */
ve.ui.MWGalleryDialog.prototype.updateDialogSize = function () {
	if ( this.searchPanelVisible && this.indexLayout.currentTabPanelName === 'images' ) {
		this.setSize( 'larger' );
	} else {
		this.setSize( 'large' );
	}
};

/**
 * Toggle the empty gallery message
 *
 * @param {boolean} empty The gallery is empty
 */
ve.ui.MWGalleryDialog.prototype.toggleEmptyGalleryMessage = function ( empty ) {
	if ( empty ) {
		this.$emptyGalleryMessage.removeClass( 'oo-ui-element-hidden' );
	} else {
		this.$emptyGalleryMessage.addClass( 'oo-ui-element-hidden' );
	}
};

/**
 * Disable the "Done" button if the gallery is empty, otherwise enable it
 *
 * TODO Disable the button until the user makes any changes
 */
ve.ui.MWGalleryDialog.prototype.updateActions = function () {
	this.actions.setAbilities( { done: this.isModified() } );
};

/**
 * Check if gallery attributes or contents would be modified if changes were applied.
 *
 * @return {boolean}
 */
ve.ui.MWGalleryDialog.prototype.isModified = function () {
	var mwDataCopy, i;

	// Check attributes
	if ( this.originalMwDataNormalized ) {
		mwDataCopy = ve.copy( this.selectedNode.getAttribute( 'mw' ) );
		this.updateMwData( mwDataCopy );
		if ( !ve.compare( mwDataCopy, this.originalMwDataNormalized ) ) {
			return true;
		}
	}
	if ( this.captionTarget.hasBeenModified() ) {
		return true;
	}

	// Check contents: each image's attributes and contents (caption)
	if ( this.anyItemModified || this.isHighlightedItemModified() ) {
		return true;
	}

	// Check contents: added/removed/reordered images
	if ( this.originalGalleryGroupItems ) {
		if ( this.galleryGroup.items.length !== this.originalGalleryGroupItems.length ) {
			return true;
		}
		for ( i = 0; i < this.galleryGroup.items.length; i++ ) {
			if ( this.galleryGroup.items[ i ] !== this.originalGalleryGroupItems[ i ] ) {
				return true;
			}
		}
	}

	return false;
};

/**
 * Check if currently highlighted item's attributes or contents would be modified if changes were
 * applied.
 *
 * @return {boolean}
 */
ve.ui.MWGalleryDialog.prototype.isHighlightedItemModified = function () {
	if ( this.highlightedItem ) {
		if ( this.highlightedAltTextInput.getValue() !== this.highlightedItem.altText ) {
			return true;
		}
		if ( this.highlightedCaptionTarget.hasBeenModified() ) {
			return true;
		}
	}
	return false;
};

/**
 * Insert or update the node in the document model from the new values
 */
ve.ui.MWGalleryDialog.prototype.insertOrUpdateNode = function () {
	var i, ilen, element, mwData, innerRange, captionInsertionOffset,
		surfaceModel = this.getFragment().getSurface(),
		surfaceModelDocument = surfaceModel.getDocument(),
		items = this.galleryGroup.items,
		data = [];

	function scaleImage( height, width, maxHeight, maxWidth ) {
		var scaleFactor, heightScaleFactor, widthScaleFactor;

		heightScaleFactor = maxHeight / height;
		widthScaleFactor = maxWidth / width;

		scaleFactor = width * heightScaleFactor > maxWidth ? widthScaleFactor : heightScaleFactor;

		return {
			height: Math.round( height * scaleFactor ),
			width: Math.round( width * scaleFactor )
		};
	}

	function getImageLinearData( image ) {
		var size, imageAttributes;

		size = scaleImage(
			parseInt( image.height ),
			parseInt( image.width ),
			parseInt( mwData.attrs.heights || this.defaults.imageHeight ),
			parseInt( mwData.attrs.widths || this.defaults.imageWidth )
		);
		imageAttributes = {
			resource: image.resource,
			altText: image.altText,
			src: image.thumbUrl,
			height: size.height,
			width: size.width
		};

		return [
			{ type: 'mwGalleryImage', attributes: imageAttributes },
			{ type: 'mwGalleryImageCaption' },
			// Actual caption contents are inserted later
			{ type: '/mwGalleryImageCaption' },
			{ type: '/mwGalleryImage' }
		];
	}

	if ( this.selectedNode ) {
		// Update mwData
		mwData = ve.copy( this.selectedNode.getAttribute( 'mw' ) );
		this.updateMwData( mwData );
		surfaceModel.change(
			ve.dm.TransactionBuilder.static.newFromAttributeChanges(
				surfaceModelDocument,
				this.selectedNode.getOuterRange().start,
				{ mw: mwData }
			)
		);

		innerRange = this.selectedNode.getRange();
	} else {
		// Make gallery node and mwData
		element = {
			type: 'mwGallery',
			attributes: {
				mw: {
					name: 'gallery',
					attrs: {},
					body: {}
				}
			}
		};
		mwData = element.attributes.mw;
		this.updateMwData( mwData );
		// Collapse returns a new fragment, so update this.fragment
		this.fragment = this.getFragment().collapseToEnd();
		this.getFragment().insertContent( [
			element,
			{ type: '/mwGallery' }
		] );

		innerRange = new ve.Range( this.fragment.getSelection().getRange().from + 1 );
	}

	// Update all child elements' data, but without the contents of the captions
	if ( this.captionDocument.data.hasContent() ) {
		data = data.concat( [
			{ type: 'mwGalleryCaption' },
			{ type: '/mwGalleryCaption' }
		] );
	}
	// Build node for each image
	for ( i = 0, ilen = items.length; i < ilen; i++ ) {
		data = data.concat( getImageLinearData.call( this, items[ i ] ) );
	}
	// Replace whole contents of this node with the new ones
	surfaceModel.change(
		ve.dm.TransactionBuilder.static.newFromReplacement(
			surfaceModelDocument,
			innerRange,
			data
		)
	);

	// Minus 2 to skip past </mwGalleryImageCaption></mwGalleryImage>
	captionInsertionOffset = innerRange.from + data.length - 2;
	// Update image captions. In reverse order to avoid having to adjust offsets for each insertion.
	for ( i = items.length - 1; i >= 0; i-- ) {
		surfaceModel.change(
			ve.dm.TransactionBuilder.static.newFromDocumentInsertion(
				surfaceModel.getDocument(),
				captionInsertionOffset,
				items[ i ].captionDocument
			)
		);
		// Skip past </mwGalleryImageCaption></mwGalleryImage><mwGalleryImage><mwGalleryImageCaption>
		captionInsertionOffset -= 4;
	}

	// Update gallery caption
	if ( this.captionDocument.data.hasContent() ) {
		surfaceModel.change(
			ve.dm.TransactionBuilder.static.newFromDocumentInsertion(
				surfaceModel.getDocument(),
				// Plus 1 to skip past <mwGalleryCaption>
				innerRange.from + 1,
				this.captionDocument
			)
		);
	}
};

/**
 * Update the 'mw' attribute with data from inputs in the dialog.
 *
 * @param {Object} mwData Value of the 'mw' attribute, updated in-place
 * @private
 */
ve.ui.MWGalleryDialog.prototype.updateMwData = function ( mwData ) {
	var mode;

	// Need to do this, otherwise mwData.body.extsrc will override all attribute changes
	mwData.body = {};
	// Need to do this, otherwise it will override the caption from the gallery caption node
	delete mwData.attrs.caption;
	// Update attributes
	if ( this.modeDropdown.getMenu().findSelectedItem() ) {
		mode = this.modeDropdown.getMenu().findSelectedItem().getData();
	}
	// Unset mode attribute if it is the same as the default
	mwData.attrs.mode = mode === this.defaults.mode ? undefined : mode;
	mwData.attrs.widths = this.widthsInput.getValue() || undefined;
	mwData.attrs.heights = this.heightsInput.getValue() || undefined;
	mwData.attrs.perrow = this.perrowInput.getValue() || undefined;
	mwData.attrs.showfilename = this.showFilenameCheckbox.isSelected() ? 'yes' : undefined;
	mwData.attrs.class = this.classesInput.getValue() || undefined;
	mwData.attrs.style = this.stylesInput.getValue() || undefined;
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWGalleryDialog );
