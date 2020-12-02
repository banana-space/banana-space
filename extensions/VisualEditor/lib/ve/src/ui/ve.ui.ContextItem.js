/*!
 * VisualEditor UserInterface ContextItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Item in a context.
 *
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} [model] Model item is related to
 * @param {Object} [config] Configuration options
 */
ve.ui.ContextItem = function VeUiContextItem( context, model, config ) {
	// Parent constructor
	ve.ui.ContextItem.super.call( this, config );

	// Properties
	this.context = context;
	this.model = model;
	this.fragment = null;

	// Events
	this.$element.on( 'mousedown', function () {
		// Deactivate so context is not automatically closed
		// by null selection
		context.getSurface().getView().deactivate();
	} );
	this.$element.on( 'keydown', function ( e ) {
		// Pressing escape while focus is in the context should
		// return focus to the surface
		if ( e.keyCode === OO.ui.Keys.ESCAPE && context.getSurface().getView().isDeactivated() ) {
			context.getSurface().getView().activate();
			return false;
		}
	} );

	// Initialization
	this.$element.addClass( 've-ui-contextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.ContextItem, OO.ui.Widget );

/* Events */

/**
 * @event command
 */

/* Static Properties */

/**
 * Whether this item exclusively handles any model class
 *
 * @static
 * @property {boolean}
 * @inheritable
 */
ve.ui.ContextItem.static.exclusive = true;

ve.ui.ContextItem.static.commandName = null;

/**
 * Annotation or node models this item is related to.
 *
 * Used by #isCompatibleWith.
 *
 * @static
 * @property {Function[]}
 * @inheritable
 */
ve.ui.ContextItem.static.modelClasses = [];

/* Methods */

/**
 * Check if this item is compatible with a given model.
 *
 * @static
 * @inheritable
 * @param {ve.dm.Model} model Model to check
 * @return {boolean} Item can be used with model
 */
ve.ui.ContextItem.static.isCompatibleWith = function ( model ) {
	return ve.isInstanceOfAny( model, this.modelClasses );
};

/**
 * Check if model is a node
 *
 * @return {boolean} Model is a node
 */
ve.ui.ContextItem.prototype.isNode = function () {
	return this.model && this.model instanceof ve.dm.Node;
};

/**
 * Get the command for this item.
 *
 * @return {ve.ui.Command} Command
 */
ve.ui.ContextItem.prototype.getCommand = function () {
	return this.context.getSurface().commandRegistry.lookup( this.constructor.static.commandName );
};

/**
 * Get a surface fragment covering the related model node, or the current selection otherwise
 *
 * @return {ve.dm.SurfaceFragment} Surface fragment
 */
ve.ui.ContextItem.prototype.getFragment = function () {
	var surfaceModel;
	if ( !this.fragment ) {
		surfaceModel = this.context.getSurface().getModel();
		this.fragment = this.isNode() ?
			surfaceModel.getLinearFragment( this.model.getOuterRange() ) :
			surfaceModel.getFragment();
	}
	return this.fragment;
};

/**
 * Check if the context's surface is readOnly
 *
 * @return {boolean} Context's surface is readOnly
 */
ve.ui.ContextItem.prototype.isReadOnly = function () {
	return this.context.getSurface().isReadOnly();
};

/**
 * Setup the item.
 *
 * @return {ve.ui.ContextItem}
 * @chainable
 */
ve.ui.ContextItem.prototype.setup = function () {
	return this;
};

/**
 * Teardown the item.
 *
 * @return {ve.ui.ContextItem}
 * @chainable
 */
ve.ui.ContextItem.prototype.teardown = function () {
	return this;
};
