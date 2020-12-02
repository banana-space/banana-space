/*!
 * VisualEditor Initialization Target class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Generic Initialization target.
 *
 * @class
 * @abstract
 * @extends OO.ui.Element
 * @mixins OO.EventEmitter
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @cfg {Object} [toolbarConfig] Configuration options for the toolbar
 * @cfg {Object} [toolbarGroups] Toolbar groups, defaults to this.constructor.static.toolbarGroups
 * @cfg {Object} [actionGroups] Toolbar groups, defaults to this.constructor.static.actionGroups
 * @cfg {Object} [modes] Available editing modes. Defaults to static.modes
 * @cfg {Object} [defaultMode] Default mode for new surfaces. Must be in this.modes and defaults to first item.
 * @cfg {Object} [register=true] Register the target at ve.init.target
 */
ve.init.Target = function VeInitTarget( config ) {
	var isIe = ve.init.platform.constructor.static.isInternetExplorer(),
		isEdge = ve.init.platform.constructor.static.isEdge();

	config = config || {};

	// Parent constructor
	ve.init.Target.super.call( this, config );

	// Mixin constructors
	OO.EventEmitter.call( this );

	// Register
	if ( config.register !== false ) {
		ve.init.target = this;
	}

	// Properties
	this.surfaces = [];
	this.surface = null;
	this.toolbar = null;
	this.actionsToolbar = null;
	this.toolbarConfig = config.toolbarConfig || {};
	this.toolbarGroups = config.toolbarGroups || this.constructor.static.toolbarGroups;
	this.actionGroups = config.actionGroups || this.constructor.static.actionGroups;
	this.$scrollContainer = this.getScrollContainer();
	this.$scrollListener = this.$scrollContainer.is( 'html, body' ) ?
		$( OO.ui.Element.static.getWindow( this.$scrollContainer[ 0 ] ) ) :
		this.$scrollContainer;

	this.toolbarScrollOffset = 0;
	this.activeToolbars = 0;
	this.wasSurfaceActive = null;
	this.teardownPromise = null;

	this.modes = config.modes || this.constructor.static.modes;
	this.setDefaultMode( config.defaultMode );

	this.setupTriggerListeners();

	// Initialization
	this.$element.addClass( 've-init-target' );

	if ( isIe ) {
		this.$element.addClass( 've-init-target-ie' );
	}

	// We don't have any Edge CSS bugs that aren't present in IE, so
	// use a combined class to simplify selectors.
	if ( isIe || isEdge ) {
		this.$element.addClass( 've-init-target-ie-or-edge' );
	}

	if ( ve.init.platform.constructor.static.isIos() ) {
		this.$element.addClass( 've-init-target-ios' );
	}

	// Events
	this.onDocumentKeyDownHandler = this.onDocumentKeyDown.bind( this );
	this.onDocumentKeyUpHandler = this.onDocumentKeyUp.bind( this );
	this.onDocumentVisibilityChangeHandler = this.onDocumentVisibilityChange.bind( this );
	this.onTargetKeyDownHandler = this.onTargetKeyDown.bind( this );
	this.onContainerScrollHandler = this.onContainerScroll.bind( this );
	this.bindHandlers();
};

/* Inheritance */

OO.inheritClass( ve.init.Target, OO.ui.Element );

OO.mixinClass( ve.init.Target, OO.EventEmitter );

/* Events */

/**
 * @event surfaceReady
 *
 * Must be fired after the surface is initialized
 */

/* Static Properties */

/**
 * Editing modes available in the target.
 *
 * Must contain at least one mode. Overridden if the #modes config option is used.
 *
 * @static
 * @property {string[]}
 * @inheritable
 */
ve.init.Target.static.modes = [ 'visual' ];

ve.init.Target.static.toolbarGroups = [
	{
		name: 'history',
		include: [ 'undo', 'redo' ]
	},
	{
		name: 'format',
		header: OO.ui.deferMsg( 'visualeditor-toolbar-paragraph-format' ),
		title: OO.ui.deferMsg( 'visualeditor-toolbar-format-tooltip' ),
		type: 'menu',
		include: [ { group: 'format' } ],
		promote: [ 'paragraph' ],
		demote: [ 'preformatted', 'blockquote' ]
	},
	{
		name: 'style',
		header: OO.ui.deferMsg( 'visualeditor-toolbar-text-style' ),
		title: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
		include: [ 'bold', 'italic', 'moreTextStyle' ]
	},
	{
		name: 'link',
		include: [ 'link' ]
	},
	{
		name: 'structure',
		header: OO.ui.deferMsg( 'visualeditor-toolbar-structure' ),
		title: OO.ui.deferMsg( 'visualeditor-toolbar-structure' ),
		label: OO.ui.deferMsg( 'visualeditor-toolbar-structure' ),
		invisibleLabel: true,
		type: 'list',
		icon: 'listBullet',
		include: [ { group: 'structure' } ],
		demote: [ 'outdent', 'indent' ]
	},
	{
		name: 'insert',
		header: OO.ui.deferMsg( 'visualeditor-toolbar-insert' ),
		title: OO.ui.deferMsg( 'visualeditor-toolbar-insert' ),
		label: OO.ui.deferMsg( 'visualeditor-toolbar-insert' ),
		invisibleLabel: true,
		type: 'list',
		icon: 'add',
		include: '*'
	},
	{
		name: 'specialCharacter',
		include: [ 'specialCharacter' ]
	}
];

ve.init.Target.static.actionGroups = [];

/**
 * List of commands which can be triggered anywhere from within the document
 *
 * @type {string[]} List of command names
 */
ve.init.Target.static.documentCommands = [ 'commandHelp' ];

/**
 * List of commands which can be triggered from within the target element
 *
 * @type {string[]} List of command names
 */
ve.init.Target.static.targetCommands = [ 'findAndReplace', 'findNext', 'findPrevious' ];

/**
 * List of commands to include in the target
 *
 * Null means all commands in the registry are used (excluding excludeCommands)
 *
 * @type {string[]|null} List of command names
 */
ve.init.Target.static.includeCommands = null;

/**
 * List of commands to exclude from the target entirely
 *
 * @type {string[]} List of command names
 */
ve.init.Target.static.excludeCommands = [];

/**
 * Surface import rules
 *
 * One set for external (non-VE) paste sources and one for all paste sources.
 *
 * Most rules are handled in ve.dm.ElementLinearData#sanitize, but htmlBlacklist
 * is handled in ve.ce.Surface#afterPaste.
 *
 * @type {Object}
 */
ve.init.Target.static.importRules = {
	external: {
		blacklist: {
			// Annotations
			'textStyle/span': true,
			'textStyle/font': true,
			// Nodes
			alienInline: true,
			alienBlock: true,
			alienTableCell: true,
			comment: true,
			div: true
		},
		// Selectors to filter. Runs before model type blacklist above.
		htmlBlacklist: {
			// remove: { '.selectorToRemove': true }
			unwrap: {
				fieldset: true,
				legend: true,
				// Unsupported sectioning tags
				main: true,
				nav: true,
				aside: true
			}
		},
		nodeSanitization: true
	},
	all: null
};

/* Static methods */

/**
 * Create a document model from an HTML document.
 *
 * @param {HTMLDocument|string} doc HTML document or source text
 * @param {string} mode Editing mode
 * @param {Object} options Conversion options
 * @return {ve.dm.Document} Document model
 */
ve.init.Target.static.createModelFromDom = function ( doc, mode, options ) {
	if ( mode === 'source' ) {
		return ve.dm.sourceConverter.getModelFromSourceText( doc, options );
	} else {
		return ve.dm.converter.getModelFromDom( doc, options );
	}
};

/**
 * Parse document string into an HTML document
 *
 * @param {string} documentString Document. Note that this must really be a whole document
 *   with a single root tag.
 * @param {string} mode Editing mode
 * @return {HTMLDocument|string} HTML document, or document string (source mode)
 */
ve.init.Target.static.parseDocument = function ( documentString, mode ) {
	if ( mode === 'source' ) {
		return documentString;
	}
	return ve.createDocumentFromHtml( documentString );
};

// Deprecated alias
ve.init.Target.prototype.parseDocument = function () {
	return this.constructor.static.parseDocument.apply( this.constructor.static, arguments );
};

/* Methods */

/**
 * Set default editing mode for new surfaces
 *
 * @param {string} defaultMode Editing mode, see static.modes
 */
ve.init.Target.prototype.setDefaultMode = function ( defaultMode ) {
	// Mode is not available
	if ( !this.isModeAvailable( defaultMode ) ) {
		if ( !this.defaultMode ) {
			// Use default mode if nothing has been set
			defaultMode = this.modes[ 0 ];
		} else {
			// Fail if we already have a valid mode
			return;
		}
	}
	if ( defaultMode !== this.defaultMode ) {
		if ( this.defaultMode ) {
			// Documented below
			// eslint-disable-next-line mediawiki/class-doc
			this.$element.removeClass( 've-init-target-' + this.defaultMode );
		}
		// The following classes are used here:
		// * ve-init-target-visual
		// * ve-init-target-[modename]
		this.$element.addClass( 've-init-target-' + defaultMode );
		this.defaultMode = defaultMode;
	}
};

/**
 * Get default editing mode for new surfaces
 *
 * @return {string} Editing mode
 */
ve.init.Target.prototype.getDefaultMode = function () {
	return this.defaultMode;
};

/**
 * Check if a specific editing mode is available
 *
 * @param {string} mode Editing mode
 * @return {boolean} Editing mode is available
 */
ve.init.Target.prototype.isModeAvailable = function ( mode ) {
	return this.modes.indexOf( mode ) !== -1;
};

/**
 * Bind event handlers to target and document
 */
ve.init.Target.prototype.bindHandlers = function () {
	$( this.getElementDocument() ).on( {
		keydown: this.onDocumentKeyDownHandler,
		keyup: this.onDocumentKeyUpHandler,
		visibilitychange: this.onDocumentVisibilityChangeHandler
	} );
	this.$element.on( 'keydown', this.onTargetKeyDownHandler );
	ve.addPassiveEventListener( this.$scrollListener[ 0 ], 'scroll', this.onContainerScrollHandler );
};

/**
 * Unbind event handlers on target and document
 */
ve.init.Target.prototype.unbindHandlers = function () {
	$( this.getElementDocument() ).off( {
		keydown: this.onDocumentKeyDownHandler,
		keyup: this.onDocumentKeyUpHandler,
		visibilitychange: this.onDocumentVisibilityChangeHandler
	} );
	this.$element.off( 'keydown', this.onTargetKeyDownHandler );
	ve.removePassiveEventListener( this.$scrollListener[ 0 ], 'scroll', this.onContainerScrollHandler );
};

/**
 * Teardown the target, removing all surfaces, toolbars and handlers
 *
 * @return {jQuery.Promise} Promise which resolves when the target has been torn down
 */
ve.init.Target.prototype.teardown = function () {
	if ( !this.teardownPromise ) {
		this.unbindHandlers();
		// Wait for the toolbar to teardown before clearing surfaces,
		// as it may want to transition away
		this.teardownPromise = this.teardownToolbar().then( this.clearSurfaces.bind( this ) );
	}
	return this.teardownPromise;
};

/**
 * Destroy the target
 *
 * @return {jQuery.Promise} Promise which resolves when the target has been destroyed
 */
ve.init.Target.prototype.destroy = function () {
	var target = this;
	return this.teardown().then( function () {
		target.$element.remove();
		if ( ve.init.target === target ) {
			ve.init.target = null;
		}
	} );
};

/**
 * Set up trigger listeners
 */
ve.init.Target.prototype.setupTriggerListeners = function () {
	var surfaceOrSurfaceConfig = this.getSurface() || this.getSurfaceConfig();
	this.documentTriggerListener = new ve.TriggerListener( this.constructor.static.documentCommands, surfaceOrSurfaceConfig.commandRegistry );
	this.targetTriggerListener = new ve.TriggerListener( this.constructor.static.targetCommands, surfaceOrSurfaceConfig.commandRegistry );
};

/**
 * Get the target's scroll container
 *
 * @return {jQuery} The target's scroll container
 */
ve.init.Target.prototype.getScrollContainer = function () {
	return $( OO.ui.Element.static.getClosestScrollableContainer( document.body ) );
};

/**
 * Handle scroll container scroll events
 */
ve.init.Target.prototype.onContainerScroll = function () {
	var scrollTop, wasFloating,
		toolbar = this.getToolbar();

	if ( toolbar.isFloatable() ) {
		wasFloating = toolbar.isFloating();
		scrollTop = this.$scrollContainer.scrollTop();

		if ( scrollTop + this.toolbarScrollOffset > toolbar.getElementOffset().top ) {
			toolbar.float();
		} else {
			toolbar.unfloat();
		}

		if ( toolbar.isFloating() !== wasFloating ) {
			// HACK: Re-position any active toolgroup popups. We can't rely on normal event handler order
			// because we're mixing jQuery and non-jQuery events. T205924#4657203
			toolbar.getItems().forEach( function ( toolgroup ) {
				if ( toolgroup instanceof OO.ui.PopupToolGroup && toolgroup.isActive() ) {
					toolgroup.position();
				}
			} );
		}
	}
};

/**
 * Handle key down events on the document
 *
 * @param {jQuery.Event} e Key down event
 */
ve.init.Target.prototype.onDocumentKeyDown = function ( e ) {
	var command, surface, trigger = new ve.ui.Trigger( e );
	if ( trigger.isComplete() ) {
		command = this.documentTriggerListener.getCommandByTrigger( trigger.toString() );
		surface = this.getSurface();
		if ( surface && command && command.execute( surface, undefined, 'trigger' ) ) {
			e.preventDefault();
		}
	}
	// Allows elements to re-style for ctrl+click behaviour, e.g. ve.ce.LinkAnnotation
	this.$element.toggleClass( 've-init-target-ctrl-meta-down', e.ctrlKey || e.metaKey );
};

/**
 * Handle key up events on the document
 *
 * @param {jQuery.Event} e Key up event
 */
ve.init.Target.prototype.onDocumentKeyUp = function ( e ) {
	this.$element.toggleClass( 've-init-target-ctrl-meta-down', e.ctrlKey || e.metaKey );
};

/**
 * Handle visibility change events on the document
 *
 * @param {jQuery.Event} e Visibility change event
 */
ve.init.Target.prototype.onDocumentVisibilityChange = function () {
	// keyup event will be missed if you ctrl+tab away from the page
	this.$element.removeClass( 've-init-target-ctrl-meta-down' );
};

/**
 * Handle key down events on the target
 *
 * @param {jQuery.Event} e Key down event
 */
ve.init.Target.prototype.onTargetKeyDown = function ( e ) {
	var command, surface, trigger = new ve.ui.Trigger( e );
	if ( trigger.isComplete() ) {
		command = this.targetTriggerListener.getCommandByTrigger( trigger.toString() );
		surface = this.getSurface();
		if ( surface && command && command.execute( surface, undefined, 'trigger' ) ) {
			e.preventDefault();
		}
	}
};

/**
 * Handle toolbar resize events
 */
ve.init.Target.prototype.onToolbarResize = function () {
	this.getSurface().setPadding( {
		top: this.getToolbar().getHeight() + this.toolbarScrollOffset
	} );
};

/**
 * Create a target widget.
 *
 * @param {Object} [config] Configuration options
 * @return {ve.ui.TargetWidget}
 */
ve.init.Target.prototype.createTargetWidget = function ( config ) {
	return new ve.ui.TargetWidget( ve.extendObject( {
		toolbarGroups: this.toolbarGroups
	}, config ) );
};

/**
 * Create a surface.
 *
 * @param {ve.dm.Document|ve.dm.Surface} dmDocOrSurface Document model or surface model
 * @param {Object} [config] Configuration options
 * @return {ve.ui.Surface}
 */
ve.init.Target.prototype.createSurface = function ( dmDocOrSurface, config ) {
	return new ve.ui.Surface( dmDocOrSurface, this.getSurfaceConfig( config ) );
};

/**
 * Get surface configuration options
 *
 * @param {Object} config Configuration option overrides
 * @return {Object} Surface configuration options
 */
ve.init.Target.prototype.getSurfaceConfig = function ( config ) {
	return ve.extendObject( {
		$scrollContainer: this.$scrollContainer,
		commandRegistry: ve.ui.commandRegistry,
		sequenceRegistry: ve.ui.sequenceRegistry,
		dataTransferHandlerFactory: ve.ui.dataTransferHandlerFactory,
		includeCommands: this.constructor.static.includeCommands,
		excludeCommands: OO.simpleArrayUnion(
			this.constructor.static.excludeCommands,
			this.constructor.static.documentCommands,
			this.constructor.static.targetCommands
		),
		importRules: this.constructor.static.importRules
	}, config );
};

/**
 * Add a surface to the target
 *
 * @param {ve.dm.Document|ve.dm.Surface} dmDocOrSurface Document model or surface model
 * @param {Object} [config] Configuration options
 * @return {ve.ui.Surface}
 */
ve.init.Target.prototype.addSurface = function ( dmDocOrSurface, config ) {
	var surface = this.createSurface( dmDocOrSurface, ve.extendObject( { mode: this.getDefaultMode() }, config ) );
	this.surfaces.push( surface );
	surface.getView().connect( this, {
		focus: this.onSurfaceViewFocus.bind( this, surface )
	} );
	// Sub-classes should initialize the surface when possible, then fire 'surfaceReady'
	return surface;
};

/**
 * Destroy and remove all surfaces from the target
 */
ve.init.Target.prototype.clearSurfaces = function () {
	// We're about to destroy this.surface, so unset it for sanity
	// Otherwise, getSurface() could return a destroyed surface
	this.surface = null;
	while ( this.surfaces.length ) {
		this.surfaces.pop().destroy();
	}
};

/**
 * Handle focus events from a surface's view
 *
 * @param {ve.ui.Surface} surface Surface firing the event
 */
ve.init.Target.prototype.onSurfaceViewFocus = function ( surface ) {
	this.setSurface( surface );
};

/**
 * Set the target's active surface
 *
 * @param {ve.ui.Surface} surface Surface
 */
ve.init.Target.prototype.setSurface = function ( surface ) {
	if ( this.surfaces.indexOf( surface ) === -1 ) {
		throw new Error( 'Active surface must have been added first' );
	}
	if ( surface !== this.surface ) {
		this.surface = surface;
		this.setupToolbar( surface );
	}
};

/**
 * Get the target's active surface, if it exists
 *
 * @return {ve.ui.Surface|null} Surface
 */
ve.init.Target.prototype.getSurface = function () {
	return this.surface;
};

/**
 * Get the target's toolbar
 *
 * @return {ve.ui.TargetToolbar} Toolbar
 */
ve.init.Target.prototype.getToolbar = function () {
	if ( !this.toolbar ) {
		this.toolbar = new ve.ui.PositionedTargetToolbar( this, this.toolbarConfig );
	}
	return this.toolbar;
};

/**
 * Get the actions toolbar
 *
 * @return {ve.ui.TargetToolbar} Actions toolbar
 */
ve.init.Target.prototype.getActions = function () {
	if ( !this.actionsToolbar ) {
		this.actionsToolbar = new ve.ui.TargetToolbar( this, {
			position: this.toolbarConfig.position,
			$overlay: this.toolbarConfig.$overlay
		} );
	}
	return this.actionsToolbar;
};

/**
 * Set up the toolbar, attaching it to a surface.
 *
 * @param {ve.ui.Surface} surface Surface
 */
ve.init.Target.prototype.setupToolbar = function ( surface ) {
	var toolbar = this.getToolbar(),
		actions = this.getActions(),
		rAF = window.requestAnimationFrame || setTimeout;

	toolbar.connect( this, {
		resize: 'onToolbarResize',
		active: 'onToolbarActive'
	} );
	actions.connect( this, { active: 'onToolbarActive' } );

	toolbar.setup( this.toolbarGroups, surface );
	actions.setup( this.actionGroups, surface );
	this.attachToolbar();
	toolbar.$actions.append( actions.$element );
	rAF( this.onContainerScrollHandler );
};

/**
 * Deactivate the surface. Maybe save some properties that should be restored when it's activated.
 *
 * @protected
 */
ve.init.Target.prototype.deactivateSurfaceForToolbar = function () {
	var view = this.getSurface().getView();
	// Surface may already be deactivated (e.g. link inspector is open)
	this.wasSurfaceActive = !view.deactivated;
	if ( this.wasSurfaceActive ) {
		view.deactivate();
	}
};

/**
 * Activate the surface. Restore any properties saved in #deactivate.
 *
 * @protected
 */
ve.init.Target.prototype.activateSurfaceForToolbar = function () {
	var view = this.getSurface().getView();
	// For non-collapsed mobile selections, don't reactivate
	if ( this.wasSurfaceActive && !( OO.ui.isMobile() && !view.getModel().getSelection().isCollapsed() ) ) {
		view.activate();
	}
};

/**
 * Handle active events from the toolbar
 *
 * @param {boolean} active The toolbar is active
 */
ve.init.Target.prototype.onToolbarActive = function ( active ) {
	// Deactivate the surface when the toolbar is active (T109529, T201329)
	if ( active ) {
		this.activeToolbars++;
		if ( this.activeToolbars === 1 ) {
			this.deactivateSurfaceForToolbar();
		}
	} else {
		this.activeToolbars--;
		if ( !this.activeToolbars ) {
			this.activateSurfaceForToolbar();
		}
	}
};

/**
 * Teardown the toolbar
 *
 * @return {jQuery.Promise} Promise which resolves when the toolbar has been torn down
 */
ve.init.Target.prototype.teardownToolbar = function () {
	if ( this.toolbar ) {
		this.toolbar.destroy();
		this.toolbar = null;
	}
	if ( this.actionsToolbar ) {
		this.actionsToolbar.destroy();
		this.actionsToolbar = null;
	}
	return ve.createDeferred().resolve().promise();
};

/**
 * Attach the toolbar to the DOM
 */
ve.init.Target.prototype.attachToolbar = function () {
	var toolbar = this.getToolbar();
	toolbar.$element.insertBefore( toolbar.getSurface().$element );
	toolbar.initialize();
	this.getActions().initialize();
};
