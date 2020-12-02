/*!
 * VisualEditor AlienContextItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a Alien.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.AlienContextItem = function VeUiAlienContextItem( context, model, config ) {
	// Parent constructor
	ve.ui.AlienContextItem.super.call( this, context, model, config );

	// Initialization
	this.$element.addClass( 've-ui-alienContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.AlienContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.AlienContextItem.static.name = 'alien';

ve.ui.AlienContextItem.static.icon = 'puzzle';

ve.ui.AlienContextItem.static.label = OO.ui.deferMsg( 'visualeditor-aliencontextitem-title' );

ve.ui.AlienContextItem.static.editable = false;

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.AlienContextItem );
