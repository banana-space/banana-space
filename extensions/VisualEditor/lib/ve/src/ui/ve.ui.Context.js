/*!
 * VisualEditor UserInterface Context class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * UserInterface context.
 *
 * @class
 * @abstract
 * @extends OO.ui.Element
 * @mixins OO.EventEmitter
 * @mixins OO.ui.mixin.GroupElement
 *
 * @constructor
 * @param {ve.ui.Surface} surface
 * @param {Object} [config] Configuration options
 */
ve.ui.Context = function VeUiContext( surface, config ) {
	// Parent constructor
	ve.ui.Context.super.call( this, config );

	// Mixin constructors
	OO.EventEmitter.call( this );
	OO.ui.mixin.GroupElement.call( this, config );

	// Properties
	this.surface = surface;
	this.visible = false;
	this.choosing = false;

	this.$focusTrapBefore = $( '<div>' ).prop( 'tabIndex', 0 );
	this.$focusTrapAfter = $( '<div>' ).prop( 'tabIndex', 0 );
	this.$focusTrapBefore.add( this.$focusTrapAfter ).on( 'focus', function () {
		surface.getView().activate();
	} );

	// Initialization
	// Hide element using a class, not this.toggle, as child implementations
	// of toggle may require the instance to be fully constructed before running.
	this.$group.addClass( 've-ui-context-menu' );
	this.$element
		.addClass( 've-ui-context ve-ui-context-hidden' )
		.append( this.$focusTrapBefore, this.$group, this.$focusTrapAfter );
};

/* Inheritance */

OO.inheritClass( ve.ui.Context, OO.ui.Element );

OO.mixinClass( ve.ui.Context, OO.EventEmitter );

OO.mixinClass( ve.ui.Context, OO.ui.mixin.GroupElement );

/* Events */

/**
 * @event resize
 */

/* Static Properties */

/**
 * Context is for mobile devices.
 *
 * @static
 * @inheritable
 * @property {boolean}
 */
ve.ui.Context.static.isMobile = false;

/* Methods */

/**
 * Check if context is for mobile devices
 *
 * @return {boolean} Context is for mobile devices
 */
ve.ui.Context.prototype.isMobile = function () {
	return this.constructor.static.isMobile;
};

/**
 * Check if context is visible.
 *
 * @return {boolean} Context is visible
 */
ve.ui.Context.prototype.isVisible = function () {
	return this.visible;
};

/**
 * Get related item sources.
 *
 * Result is cached, and cleared when the model or selection changes.
 *
 * @abstract
 * @return {Object[]} List of objects containing `type`, `name` and `model` properties,
 *   representing each compatible type (either `item` or `tool`), symbolic name of the item or tool
 *   and the model the item or tool is compatible with
 */
ve.ui.Context.prototype.getRelatedSources = null;

/**
 * Get the surface the context is being used with.
 *
 * @return {ve.ui.Surface}
 */
ve.ui.Context.prototype.getSurface = function () {
	return this.surface;
};

/**
 * Toggle the menu.
 *
 * @param {boolean} [show] Show the menu, omit to toggle
 * @return {ve.ui.Context}
 * @chainable
 */
ve.ui.Context.prototype.toggleMenu = function ( show ) {
	show = show === undefined ? !this.choosing : !!show;

	if ( show !== this.choosing ) {
		this.choosing = show;
		this.$element.toggleClass( 've-ui-context-choosing', show );
		if ( show ) {
			this.setupMenuItems();
		} else {
			this.teardownMenuItems();
		}
	}

	return this;
};

/**
 * Setup menu items.
 *
 * @protected
 * @return {ve.ui.Context}
 * @chainable
 */
ve.ui.Context.prototype.setupMenuItems = function () {
	var i, len, source,
		sources = this.getRelatedSources(),
		items = [];

	for ( i = 0, len = sources.length; i < len; i++ ) {
		source = sources[ i ];
		if ( source.type === 'item' ) {
			items.push( ve.ui.contextItemFactory.create(
				sources[ i ].name, this, sources[ i ].model
			) );
		} else if ( source.type === 'tool' ) {
			items.push( new ve.ui.ToolContextItem(
				this, sources[ i ].model, ve.ui.toolFactory.lookup( sources[ i ].name )
			) );
		}
	}

	this.addItems( items );
	for ( i = 0, len = items.length; i < len; i++ ) {
		items[ i ].connect( this, { command: 'onContextItemCommand' } );
		items[ i ].setup();
	}

	return this;
};

/**
 * Teardown menu items.
 *
 * @protected
 * @return {ve.ui.Context}
 * @chainable
 */
ve.ui.Context.prototype.teardownMenuItems = function () {
	var i, len;

	for ( i = 0, len = this.items.length; i < len; i++ ) {
		this.items[ i ].teardown();
	}
	this.clearItems();

	return this;
};

/**
 * Handle command events from context items
 */
ve.ui.Context.prototype.onContextItemCommand = function () {};

/**
 * Toggle the visibility of the context.
 *
 * @param {boolean} [show] Show the context, omit to toggle
 * @return {jQuery.Promise} Promise resolved when context is finished showing/hiding
 * @fires resize
 */
ve.ui.Context.prototype.toggle = function ( show ) {
	show = show === undefined ? !this.visible : !!show;
	if ( show !== this.visible ) {
		this.visible = show;
		this.$element.toggleClass( 've-ui-context-hidden', !this.visible );
	}
	this.emit( 'resize' );
	return ve.createDeferred().resolve().promise();
};

/**
 * Update the size and position of the context.
 *
 * @return {ve.ui.Context}
 * @chainable
 * @fires resize
 */
ve.ui.Context.prototype.updateDimensions = function () {
	// Override in subclass if context is positioned relative to content
	this.emit( 'resize' );
	return this;
};

/**
 * Destroy the context, removing all DOM elements.
 *
 * @return {ve.ui.Context}
 * @chainable
 */
ve.ui.Context.prototype.destroy = function () {
	// Disconnect events
	this.surface.getModel().disconnect( this );

	this.$element.remove();
	return this;
};
