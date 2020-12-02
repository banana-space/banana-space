/*!
 * VisualEditor UserInterface ToolFactory class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Factory for tools.
 *
 * @class
 * @extends OO.ui.ToolFactory
 * @mixins ve.ui.ModeledFactory
 *
 * @constructor
 */
ve.ui.ToolFactory = function VeUiToolFactory() {
	// Parent constructor
	ve.ui.ToolFactory.super.call( this );

	// Mixin constructors
	ve.ui.ModeledFactory.call( this );
};

/* Inheritance */

OO.inheritClass( ve.ui.ToolFactory, OO.ui.ToolFactory );
OO.mixinClass( ve.ui.ToolFactory, ve.ui.ModeledFactory );

/* Initialization */

ve.ui.toolFactory = new ve.ui.ToolFactory();

ve.ui.toolGroupFactory = new OO.ui.ToolGroupFactory();
