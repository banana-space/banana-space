/*!
 * VisualEditor MWAlienExtensionContextItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWAlienExtension.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWAlienExtensionContextItem = function VeUiMWAlienExtensionContextItem( context, model ) {
	// Parent constructor
	ve.ui.MWAlienExtensionContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwAlienExtensionContextItem' );

	this.setLabel( model.getExtensionName() );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWAlienExtensionContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWAlienExtensionContextItem.static.name = 'alienExtension';

ve.ui.MWAlienExtensionContextItem.static.icon = 'markup';

ve.ui.MWAlienExtensionContextItem.static.modelClasses = [
	ve.dm.MWAlienInlineExtensionNode,
	ve.dm.MWAlienBlockExtensionNode
];

ve.ui.MWAlienExtensionContextItem.static.commandName = 'alienExtension';

/* Methods */

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWAlienExtensionContextItem );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'alienExtension', 'window', 'open',
		{ args: [ 'alienExtension' ], supportedSelections: [ 'linear' ] }
	)
);
