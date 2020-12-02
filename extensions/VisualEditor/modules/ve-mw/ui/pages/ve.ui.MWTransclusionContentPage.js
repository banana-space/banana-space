/*!
 * VisualEditor user interface MWTransclusionContentPage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion dialog content page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {ve.dm.MWTransclusionContentModel} content Transclusion content
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 * @cfg {boolean} [isReadOnly] Page is read-only
 */
ve.ui.MWTransclusionContentPage = function VeUiMWTransclusionContentPage( content, name, config ) {
	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWTransclusionContentPage.super.call( this, name, config );

	// Properties
	this.content = content;
	this.textInput = new ve.ui.MWLazyMultilineTextInputWidget( {
		autosize: true,
		classes: [ 've-ui-mwTransclusionDialog-input' ]
	} )
		.setValue( this.content.getValue() )
		.setReadOnly( config.isReadOnly )
		.connect( this, { change: 'onTextInputChange' } );
	this.removeButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'trash',
		title: ve.msg( 'visualeditor-dialog-transclusion-remove-content' ),
		flags: [ 'destructive' ],
		classes: [ 've-ui-mwTransclusionDialog-removeButton' ]
	} )
		.connect( this, { click: 'onRemoveButtonClick' } );
	this.valueFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'visualeditor-dialog-transclusion-content' ),
		icon: 'wikiText',
		$content: this.textInput.$element
	} );

	// Initialization
	this.$element
		.addClass( 've-ui-mwTransclusionContentPage' )
		.append( this.valueFieldset.$element );

	if ( !config.isReadOnly ) {
		this.$element.append( this.removeButton.$element );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionContentPage, OO.ui.PageLayout );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionContentPage.prototype.setOutlineItem = function () {
	// Parent method
	ve.ui.MWTransclusionContentPage.super.prototype.setOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem
			.setIcon( 'wikiText' )
			.setMovable( true )
			.setRemovable( true )
			.setLabel( ve.msg( 'visualeditor-dialog-transclusion-content' ) );
	}
};

ve.ui.MWTransclusionContentPage.prototype.onTextInputChange = function () {
	this.content.setValue( this.textInput.getValue() );
};

ve.ui.MWTransclusionContentPage.prototype.onRemoveButtonClick = function () {
	this.content.remove();
};
