/*!
 * VisualEditor user interface MWTemplatePlaceholderPage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion dialog placeholder page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {ve.dm.MWTemplatePlaceholderModel} placeholder Template placeholder
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 */
ve.ui.MWTemplatePlaceholderPage = function VeUiMWTemplatePlaceholderPage( placeholder, name, config ) {
	var addTemplateActionFieldLayout;
	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWTemplatePlaceholderPage.super.call( this, name, config );

	// Properties
	this.placeholder = placeholder;

	this.addTemplateInput = new ve.ui.MWTemplateTitleInputWidget( {
		$overlay: config.$overlay,
		showDescriptions: true,
		api: ve.init.target.getContentApi()
	} )
		.connect( this, {
			change: 'onTemplateInputChange',
			enter: 'onAddTemplate'
		} );

	this.addTemplateInput.getLookupMenu().connect( this, {
		choose: 'onAddTemplate'
	} );

	this.addTemplateInput.$input.attr( 'aria-label', ve.msg( 'visualeditor-dialog-transclusion-add-template' ) );

	this.addTemplateButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'visualeditor-dialog-transclusion-add-template' ),
		flags: [ 'progressive' ],
		classes: [ 've-ui-mwTransclusionDialog-addButton' ],
		disabled: true
	} )
		.connect( this, { click: 'onAddTemplate' } );

	this.removeButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'trash',
		title: ve.msg( 'visualeditor-dialog-transclusion-remove-template' ),
		flags: [ 'destructive' ],
		classes: [ 've-ui-mwTransclusionDialog-removeButton' ]
	} )
		.connect( this, { click: 'onRemoveButtonClick' } );

	if ( this.placeholder.getTransclusion().parts.length === 1 ) {
		this.removeButton.toggle( false );
	}

	addTemplateActionFieldLayout = new OO.ui.ActionFieldLayout(
		this.addTemplateInput,
		this.addTemplateButton,
		{ align: 'top' }
	);

	this.addTemplateFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'visualeditor-dialog-transclusion-placeholder' ),
		icon: 'puzzle',
		classes: [ 've-ui-mwTransclusionDialog-addTemplateFieldset' ],
		items: [ addTemplateActionFieldLayout ]
	} );

	// Initialization
	this.$element
		.addClass( 've-ui-mwTemplatePlaceholderPage' )
		.append( this.addTemplateFieldset.$element, this.removeButton.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTemplatePlaceholderPage, OO.ui.PageLayout );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWTemplatePlaceholderPage.prototype.setOutlineItem = function () {
	// Parent method
	ve.ui.MWTemplatePlaceholderPage.super.prototype.setOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem
			.setIcon( 'puzzle' )
			.setMovable( true )
			.setRemovable( true )
			.setFlags( [ 'placeholder' ] )
			.setLabel( ve.msg( 'visualeditor-dialog-transclusion-placeholder' ) );
	}
};

ve.ui.MWTemplatePlaceholderPage.prototype.focus = function () {
	// Parent method
	ve.ui.MWTemplatePlaceholderPage.super.prototype.focus.apply( this, arguments );

	// HACK: Set the width of the lookupMenu to the width of the input
	// TODO: This should be handled upstream in OOUI
	this.addTemplateInput.lookupMenu.width = this.addTemplateInput.$input[ 0 ].clientWidth;
};

ve.ui.MWTemplatePlaceholderPage.prototype.onAddTemplate = function () {
	var part, name,
		transclusion = this.placeholder.getTransclusion(),
		menu = this.addTemplateInput.getLookupMenu();

	if ( menu.isVisible() ) {
		menu.chooseItem( menu.findSelectedItem() );
	}
	name = this.addTemplateInput.getMWTitle();
	if ( !name ) {
		// Invalid titles return null, so abort here.
		return;
	}
	part = ve.dm.MWTemplateModel.newFromName( transclusion, name );
	transclusion.replacePart( this.placeholder, part );
	this.addTemplateInput.pushPending();
	// abort pending lookups, also, so the menu can't appear after we've left the page
	this.addTemplateInput.closeLookupMenu();
	this.addTemplateButton.setDisabled( true );
	this.removeButton.setDisabled( true );
};

ve.ui.MWTemplatePlaceholderPage.prototype.onTemplateInputChange = function () {
	this.addTemplateButton.setDisabled( this.addTemplateInput.getMWTitle() === null );
};

ve.ui.MWTemplatePlaceholderPage.prototype.onRemoveButtonClick = function () {
	this.placeholder.remove();
};
