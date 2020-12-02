/*!
 * VisualEditor user interface MWParameterPlaceholderPage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion dialog parameter placeholder page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {ve.dm.MWTemplateModel} parameter Template
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 */
ve.ui.MWParameterPlaceholderPage = function VeUiMWParameterPlaceholderPage( parameter, name, config ) {
	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWParameterPlaceholderPage.super.call( this, name, config );

	// Properties
	this.name = name;
	this.parameter = parameter;
	this.template = this.parameter.getTemplate();
	this.addParameterSearch = new ve.ui.MWParameterSearchWidget( this.template, {
		showAll: !!config.expandedParamList
	} )
		.connect( this, {
			choose: 'onParameterChoose',
			showAll: 'onParameterShowAll'
		} );

	this.removeButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'trash',
		title: ve.msg( 'visualeditor-dialog-transclusion-remove-param' ),
		flags: [ 'destructive' ],
		classes: [ 've-ui-mwTransclusionDialog-removeButton' ]
	} )
		.connect( this, { click: 'onRemoveButtonClick' } );

	this.addParameterFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'visualeditor-dialog-transclusion-add-param' ),
		icon: 'parameter',
		classes: [ 've-ui-mwTransclusionDialog-addParameterFieldset' ],
		$content: this.addParameterSearch.$element
	} );

	this.addParameterFieldset.$element.attr( 'aria-label', ve.msg( 'visualeditor-dialog-transclusion-add-param' ) );

	// Initialization
	this.$element
		.addClass( 've-ui-mwParameterPlaceholderPage' )
		.append( this.addParameterFieldset.$element, this.removeButton.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWParameterPlaceholderPage, OO.ui.PageLayout );

/* Methods */

/**
 * Respond to the parameter search widget showAll event
 *
 * @fires showAll
 */
ve.ui.MWParameterPlaceholderPage.prototype.onParameterShowAll = function () {
	this.emit( 'showAll', this.name );
};

/**
 * @inheritdoc
 */
ve.ui.MWParameterPlaceholderPage.prototype.setOutlineItem = function () {
	// Parent method
	ve.ui.MWParameterPlaceholderPage.super.prototype.setOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem
			.setIcon( 'parameter' )
			.setMovable( false )
			.setRemovable( true )
			.setLevel( 1 )
			.setFlags( [ 'placeholder' ] )
			.setLabel( ve.msg( 'visualeditor-dialog-transclusion-add-param' ) );
	}
};

ve.ui.MWParameterPlaceholderPage.prototype.onParameterChoose = function ( name ) {
	var param;

	if ( name ) {
		param = new ve.dm.MWParameterModel( this.template, name );
		this.addParameterSearch.query.setValue( '' );
		this.template.addParameter( param );
	}
};

ve.ui.MWParameterPlaceholderPage.prototype.onRemoveButtonClick = function () {
	this.parameter.remove();
};
