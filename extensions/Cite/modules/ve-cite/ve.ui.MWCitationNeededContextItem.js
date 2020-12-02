/*!
 * VisualEditor MWCitationNeededContextItem class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Context item for a citation needed template.
 *
 * @class
 * @extends ve.ui.MWDefinedTransclusionContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWCitationNeededContextItem = function VeUiMWCitationNeededContextItem() {
	var contextItem = this;

	// Parent constructor
	ve.ui.MWCitationNeededContextItem.super.apply( this, arguments );

	this.addButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'cite-ve-citationneeded-button' ),
		flags: [ 'progressive' ]
	} ).on( 'click', function () {
		var action = ve.ui.actionFactory.create( 'citoid', contextItem.context.getSurface() );
		action.open( true );
		ve.track( 'activity.' + contextItem.constructor.static.name, { action: 'context-add-citation' } );
	} );

	// Remove progressive flag from edit, as addButton is now the
	// main progressive action in the context.
	this.editButton.setFlags( { progressive: false } );

	// Initialization
	this.$element.addClass( 've-ui-mwCitationNeededContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCitationNeededContextItem, ve.ui.MWDefinedTransclusionContextItem );

/* Static Properties */

ve.ui.MWCitationNeededContextItem.static.name = 'citationNeeded';

ve.ui.MWCitationNeededContextItem.static.icon = 'quotes';

ve.ui.MWCitationNeededContextItem.static.label = OO.ui.deferMsg( 'cite-ve-citationneeded-title' );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWCitationNeededContextItem.prototype.renderBody = function () {
	this.$body.empty().append(
		$( '<p>' ).text( ve.msg( 'cite-ve-citationneeded-description' ) ),
		this.addButton.$element
	);
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWCitationNeededContextItem );
