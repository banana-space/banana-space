/*!
 * VisualEditor UserInterface Surface class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * A surface is a top-level object which contains both a surface model and a surface view.
 *
 * @class
 * @abstract
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {HTMLDocument|Array|ve.dm.ElementLinearData|ve.dm.Document|ve.dm.Surface} dataOrDocOrSurface Document data, document model, or surface model to edit
 * @param {Object} [config] Configuration options
 * @cfg {ve.dm.BranchNode} [attachedRoot] Node to surface, if ve.dm.Document passed in
 * @cfg {string} [mode] Editing mode
 * @cfg {jQuery} [$scrollContainer] The scroll container of the surface
 * @cfg {jQuery} [$overlayContainer] Clipping container for local overlays, defaults to surface view
 * @cfg {ve.ui.CommandRegistry} [commandRegistry] Command registry to use
 * @cfg {ve.ui.SequenceRegistry} [sequenceRegistry] Sequence registry to use
 * @cfg {ve.ui.DataTransferHandlerFactory} [dataTransferHandlerFactory] Data transfer handler factory to use
 * @cfg {string[]|null} [includeCommands] List of commands to include, null for all registered commands
 * @cfg {string[]} [excludeCommands] List of commands to exclude
 * @cfg {Object} [importRules] Import rules
 * @cfg {boolean} [multiline=true] Multi-line surface
 * @cfg {string} [placeholder] Placeholder text to display when the surface is empty
 * @cfg {string} [readOnly] Surface is read-only
 * @cfg {string} [nullSelectionOnBlur=true] Surface selection is set to null on blur
 * @cfg {string} [inDialog] The name of the dialog this surface is in
 */
ve.ui.Surface = function VeUiSurface( dataOrDocOrSurface, config ) {
	var documentModel;

	config = config || {};

	// Parent constructor
	ve.ui.Surface.super.call( this, config );

	// Properties
	this.$scrollContainer = config.$scrollContainer || $( this.getClosestScrollableElementContainer() );
	this.inDialog = config.inDialog || '';
	this.mode = config.mode;

	// The following classes are used here:
	// * ve-ui-overlay-global-mobile
	// * ve-ui-overlay-global-desktop
	this.globalOverlay = new ve.ui.Overlay( { classes: [ 've-ui-overlay-global', 've-ui-overlay-global-' + ( OO.ui.isMobile() ? 'mobile' : 'desktop' ) ] } );
	this.localOverlay = new ve.ui.Overlay( { classes: [ 've-ui-overlay-local' ] } );
	this.$selections = $( '<div>' );
	this.$blockers = $( '<div>' );
	this.$controls = $( '<div>' );
	this.$menus = $( '<div>' );
	this.$placeholder = $( '<div>' ).addClass( 've-ui-surface-placeholder' );
	this.commandRegistry = config.commandRegistry || ve.ui.commandRegistry;
	this.sequenceRegistry = config.sequenceRegistry || ve.ui.sequenceRegistry;
	this.dataTransferHandlerFactory = config.dataTransferHandlerFactory || ve.ui.dataTransferHandlerFactory;
	this.commands = OO.simpleArrayDifference(
		config.includeCommands || this.commandRegistry.getNames(), config.excludeCommands || []
	);
	this.triggerListener = new ve.TriggerListener( this.commands, this.commandRegistry );
	if ( dataOrDocOrSurface instanceof ve.dm.Surface ) {
		this.model = dataOrDocOrSurface;
	} else {
		if ( dataOrDocOrSurface instanceof ve.dm.Document ) {
			// ve.dm.Document
			documentModel = dataOrDocOrSurface;
		} else if ( dataOrDocOrSurface instanceof ve.dm.ElementLinearData || Array.isArray( dataOrDocOrSurface ) ) {
			// LinearData or raw linear data
			documentModel = new ve.dm.Document( dataOrDocOrSurface );
		} else {
			// HTMLDocument
			documentModel = ve.dm.converter.getModelFromDom( dataOrDocOrSurface );
		}
		this.model = this.createModel( documentModel, config.attachedRoot );
	}
	this.view = this.createView( this.model );
	this.dialogs = this.createDialogWindowManager();
	this.importRules = config.importRules || {};
	this.multiline = config.multiline !== false;
	this.context = this.createContext( { $popupContainer: config.$overlayContainer } );
	this.progresses = [];
	this.showProgressDebounced = ve.debounce( this.showProgress.bind( this ) );
	this.scrollSelectionIntoViewDebounced = ve.debounce( this.scrollSelectionIntoView.bind( this ), 500 );
	this.debugBar = null;
	this.placeholder = null;
	this.placeholderVisible = false;
	this.setPlaceholder( config.placeholder );
	this.setReadOnly( !!config.readOnly );
	this.nullSelectionOnBlur = config.nullSelectionOnBlur !== false;
	this.completion = new ve.ui.CompletionWidget( this );

	// Deprecated, use this.padding.top
	this.toolbarHeight = 0;
	this.padding = {
		top: 0,
		right: 0,
		bottom: 0,
		left: 0
	};
	this.toolbarDialogs = new ve.ui.ToolbarDialogWindowManager( this, {
		factory: ve.ui.windowFactory,
		modal: false
	} );

	// Events
	this.getModel().connect( this, {
		select: 'onModelSelect',
		blur: 'onModelBlur',
		focus: 'onModelFocus'
	} );
	this.getModel().getDocument().connect( this, { transact: 'onDocumentTransact' } );
	this.getView().connect( this, {
		position: 'onViewPosition',
		activation: 'onViewActivation'
	} );
	this.getContext().connect( this, { resize: 'onContextResize' } );

	// Initialization
	this.$menus.append( this.context.$element, this.completion.$element );
	this.$element
		// The following classes are used here:
		// * ve-ui-surface-visual
		// * ve-ui-surface-source
		.addClass( 've-ui-surface ve-ui-surface-' + this.mode )
		.append( this.view.$element );
	this.view.$element.after( this.localOverlay.$element );
	this.localOverlay.$element.append( this.$selections, this.$blockers, this.$controls, this.$menus );
	this.globalOverlay.$element.append( this.dialogs.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.Surface, OO.ui.Widget );

/* Events */

/**
 * When a surface has been initialized
 *
 * @event ready
 */

/**
 * When a surface is destroyed.
 *
 * @event destroy
 */

/**
 * The surface was scrolled programmatically
 * as a result of a native selection change
 *
 * @event scroll
 */

/**
 * The surface has been submitted by user action, e.g. Ctrl+Enter
 *
 * @event submit
 */

/**
 * The surface read-only state has changed
 *
 * @event readOnly
 * @param {boolean} readOnly The surface is read-only
 */

/* Methods */

/**
 * Destroy the surface, releasing all memory and removing all DOM elements.
 *
 * @return {ve.ui.Surface}
 * @chainable
 * @fires destroy
 */
ve.ui.Surface.prototype.destroy = function () {
	// Destroy the ce.Surface, the ui.Context and window managers
	this.context.destroy();
	this.dialogs.destroy();
	this.toolbarDialogs.destroy();
	this.view.destroy();
	if ( this.debugBar ) {
		this.debugBar.destroy();
	}

	// Disconnect events
	this.dialogs.disconnect( this );
	this.context.getInspectors().disconnect( this );

	// Remove DOM elements
	this.$element.remove();
	this.globalOverlay.$element.remove();

	// Let others know we have been destroyed
	this.emit( 'destroy' );

	return this;
};

/**
 * Initialize surface.
 *
 * This must be called after the surface has been attached to the DOM.
 *
 * @return {ve.ui.Surface}
 * @chainable
 */
ve.ui.Surface.prototype.initialize = function () {
	// Attach globalOverlay to the global <body>, not the local frame's <body>
	$( document.body ).append( this.globalOverlay.$element );

	if ( ve.debug ) {
		this.setupDebugBar();
	}

	// The following classes are used here:
	// * ve-ui-surface-dir-ltr
	// * ve-ui-surface-dir-rtl
	this.$element.addClass( 've-ui-surface-dir-' + this.getDir() );

	this.getView().initialize();
	this.getModel().initialize();
	this.emit( 'ready' );
	return this;
};

/**
 * Get the DOM representation of the surface's current state.
 *
 * @return {HTMLDocument|string} HTML document (visual mode) or text (source mode)
 */
ve.ui.Surface.prototype.getDom = function () {
	return this.getModel().getDom();
};

/**
 * Get the HTML representation of the surface's current state.
 *
 * @return {string} HTML
 */
ve.ui.Surface.prototype.getHtml = function () {
	return this.getModel().getHtml();
};

/**
 * Get the surface's editing mode
 *
 * @return {string} Editing mode
 */
ve.ui.Surface.prototype.getMode = function () {
	return this.mode;
};

/**
 * Create a context.
 *
 * @param {Object} config Configuration options
 * @return {ve.ui.Context} Context
 */
ve.ui.Surface.prototype.createContext = function ( config ) {
	return OO.ui.isMobile() ? new ve.ui.MobileContext( this, config ) : new ve.ui.DesktopContext( this, config );
};

/**
 * Create a dialog window manager.
 *
 * @return {ve.ui.WindowManager} Dialog window manager
 */
ve.ui.Surface.prototype.createDialogWindowManager = function () {
	return OO.ui.isMobile() ?
		new ve.ui.MobileWindowManager( this, {
			factory: ve.ui.windowFactory,
			overlay: this.globalOverlay
		} ) :
		new ve.ui.SurfaceWindowManager( this, { factory: ve.ui.windowFactory } );
};

/**
 * Create a surface model
 *
 * @param {ve.dm.Document} doc Document model
 * @param {ve.dm.BranchNode} [attachedRoot] Node to surface
 * @return {ve.dm.Surface} Surface model
 */
ve.ui.Surface.prototype.createModel = function ( doc, attachedRoot ) {
	return new ve.dm.Surface( doc, attachedRoot, { sourceMode: this.getMode() === 'source' } );
};

/**
 * Create a surface view
 *
 * @param {ve.dm.Surface} model Surface model
 * @return {ve.ce.Surface} Surface view
 */
ve.ui.Surface.prototype.createView = function ( model ) {
	return new ve.ce.Surface( model, this );
};

/**
 * Set up the debug bar and insert it into the DOM.
 */
ve.ui.Surface.prototype.setupDebugBar = function () {
	this.debugBar = new ve.ui.DebugBar( this );
	this.$element.append( this.debugBar.$element );
};

/**
 * Get the bounding rectangle of the surface, relative to the viewport.
 *
 * @return {Object|null} Object with top, bottom, left, right, width and height properties.
 *  Null if the surface is not attached.
 */
ve.ui.Surface.prototype.getBoundingClientRect = function () {
	// We would use getBoundingClientRect(), but in iOS7 that's relative to the
	// document rather than to the viewport
	return this.$element[ 0 ].getClientRects()[ 0 ] || null;
};

/**
 * Get vertical measurements of the visible area of the surface viewport
 *
 * @return {Object|null} Object with top, left, bottom, and height properties. Null if the surface is not attached.
 */
ve.ui.Surface.prototype.getViewportDimensions = function () {
	var top, bottom,
		rect = this.getBoundingClientRect();

	if ( !rect ) {
		return null;
	}

	top = Math.max( this.padding.top - rect.top, 0 );
	bottom = $( this.getElementWindow() ).height() - rect.top;

	return {
		top: top,
		left: rect.left,
		bottom: bottom,
		height: bottom - top
	};
};

/**
 * Check if editing is enabled.
 *
 * @deprecated Use #isDisabled
 * @return {boolean} Editing is enabled
 */
ve.ui.Surface.prototype.isEnabled = function () {
	return !this.isDisabled();
};

/**
 * Get the surface model.
 *
 * @return {ve.dm.Surface} Surface model
 */
ve.ui.Surface.prototype.getModel = function () {
	return this.model;
};

/**
 * Get the surface view.
 *
 * @return {ve.ce.Surface} Surface view
 */
ve.ui.Surface.prototype.getView = function () {
	return this.view;
};

/**
 * Get the context menu.
 *
 * @return {ve.ui.Context} Context user interface
 */
ve.ui.Surface.prototype.getContext = function () {
	return this.context;
};

/**
 * Get dialogs window set.
 *
 * @return {ve.ui.WindowManager} Dialogs window set
 */
ve.ui.Surface.prototype.getDialogs = function () {
	return this.dialogs;
};

/**
 * Get toolbar dialogs window set.
 *
 * @return {ve.ui.WindowManager} Toolbar dialogs window set
 */
ve.ui.Surface.prototype.getToolbarDialogs = function () {
	return this.toolbarDialogs;
};

/**
 * Get the local overlay.
 *
 * Local overlays are attached to the same frame as the surface.
 *
 * @return {ve.ui.Overlay} Local overlay
 */
ve.ui.Surface.prototype.getLocalOverlay = function () {
	return this.localOverlay;
};

/**
 * Get the global overlay.
 *
 * Global overlays are attached to the top-most frame.
 *
 * @return {ve.ui.Overlay} Global overlay
 */
ve.ui.Surface.prototype.getGlobalOverlay = function () {
	return this.globalOverlay;
};

/**
 * @inheritdoc
 */
ve.ui.Surface.prototype.setDisabled = function ( disabled ) {
	if ( disabled ) {
		OO.ui.warnDeprecation( 'Surfaces can\'t be disabled, only set to readOnly' );
	}
};

/**
 * Set the read-only state of the surface
 *
 * @param {boolean} readOnly Make surface read-only
 */
ve.ui.Surface.prototype.setReadOnly = function ( readOnly ) {
	this.readOnly = !!readOnly;
	this.model.setReadOnly( readOnly );
	this.view.setReadOnly( readOnly );
	this.emit( 'readOnly', readOnly );
};

/**
 * Check if the surface is read-only
 *
 * @return {boolean}
 */
ve.ui.Surface.prototype.isReadOnly = function () {
	return this.readOnly;
};

/**
 * Give focus to the surface
 */
ve.ui.Surface.prototype.focus = function () {
	this.getView().focus();
};

/**
 * Handle transact events from the document model
 *
 * @param {ve.dm.Transaction} Transaction
 */
ve.ui.Surface.prototype.onDocumentTransact = function () {
	if ( this.placeholder ) {
		this.updatePlaceholder();
	}
};

/**
 * Handle select events from the model
 */
ve.ui.Surface.prototype.onModelSelect = function () {
	// eslint-disable-next-line no-bitwise
	if ( this.getView().dragging ^ OO.ui.isMobile() ) {
		// Allow native scroll behavior while dragging, as the start/end
		// points are unreliable until we're finished. Without this, trying to
		// drag a selection larger than a single screen will sometimes lock
		// the viewport in place, as it tries to keep the wrong end of the
		// selection on-screen.
		// On mobile the dragging flag is essentially reversed in meaning, as
		// it is set during mouse down, which happens when you are tapping
		// to select, but when you drag selection handles no mousedown event
		// occurs (or any event other 'selectionchange') so the flag is unset.
		return;
	}
	this.scrollSelectionIntoViewDebounced();
};

/**
 * Scroll the selection into view
 *
 * Called in response to selection events.
 *
 * This is done for all selections, even native ones, to account
 * for the extra padding of the floating toolbar.
 *
 * @fires scroll
 */
ve.ui.Surface.prototype.scrollSelectionIntoView = function () {
	var profile, clientRect, surfaceRect, padding,
		animate = true,
		view = this.getView(),
		selection = view.getSelection(),
		surface = this;

	// We only care about the focus end of the selection, the anchor never
	// moves and should be allowed off screen.
	clientRect = selection.getSelectionFocusRect();
	surfaceRect = this.getBoundingClientRect();
	if ( !clientRect || !surfaceRect ) {
		return;
	}

	// We want viewport-relative coordinates, so we need to translate it
	clientRect = ve.translateRect( clientRect, surfaceRect.left, surfaceRect.top );

	padding = ve.copy( this.padding );

	if ( selection.isNativeCursor() ) {
		animate = false;
		if (
			OO.ui.isMobile() &&
			!this.getModel().getSelection().isCollapsed()
		) {
			profile = $.client.profile();
			// Assume that if the selection has been expanded, then a context menu is visible
			// above the selection. We don't want this to obscure the toolbar so add on an
			// estimate of its height.
			// Previously we applied this fix to iOS, even though scrolling closed the context,
			// because the user could touch the selection to re-open it. However sometime between
			// iOS 12 and 12.3, scrolling stopped closing the context, but it doesn't move it either,
			// so this fix became useless.
			// Older versions of Android draw the context menu in the address bar and so
			// don't need to be fixed.
			if ( profile.name === 'android' && profile.versionNumber >= 6 ) {
				padding.top += 60;
			}
			// Also assume there are selection handles below on Android. (T204718)
			if ( profile.name === 'android' || profile.name === 'firefox' ) {
				padding.bottom += 30;
			}
		}

		clientRect.top -= 5;
		clientRect.bottom += 5;
	}

	ve.scrollIntoView( clientRect, {
		animate: animate,
		scrollContainer: this.$scrollContainer[ 0 ],
		padding: padding
	} ).then( function () {
		if ( selection.isNativeCursor() ) {
			// TODO: This event has only even been emitted for native selection
			// scroll changes. Perhaps rename it.
			surface.emit( 'scroll' );
		}
	} );
};

// Deprecated alias
ve.ui.Surface.prototype.scrollCursorIntoView = ve.ui.Surface.prototype.scrollSelectionIntoView;

/**
 * Set placeholder text
 *
 * @param {string} [placeholder] Placeholder text, clears placeholder if not set
 */
ve.ui.Surface.prototype.setPlaceholder = function ( placeholder ) {
	this.placeholder = placeholder;
	if ( this.placeholder ) {
		this.$placeholder.prependTo( this.$element );
		this.updatePlaceholder();
	} else {
		this.$placeholder.detach();
		this.placeholderVisible = false;
		this.getView().$element.css( 'min-height', '' );
	}
};

/**
 * Update placeholder rendering
 */
ve.ui.Surface.prototype.updatePlaceholder = function () {
	var firstNode, $wrapper,
		hasContent = this.getModel().getDocument().data.hasContent();

	this.$placeholder.toggleClass( 'oo-ui-element-hidden', hasContent );
	this.placeholderVisible = !hasContent;
	if ( !hasContent ) {
		// Use a clone of the first node in the document so the placeholder
		// styling matches the text the users sees when they start typing
		firstNode = this.getView().attachedRoot.children[ 0 ];
		if ( firstNode ) {
			$wrapper = firstNode.$element.clone();
			if ( ve.debug ) {
				// In debug mode a background colour from the render animation may be present
				$wrapper.removeAttr( 'style' );
			}
		} else {
			$wrapper = $( '<p>' );
		}
		this.$placeholder.empty().append( $wrapper.text( this.placeholder ) );
	} else {
		this.getView().$element.css( 'min-height', '' );
	}
};

/**
 * Handle position events from the view
 */
ve.ui.Surface.prototype.onViewPosition = function () {
	if ( this.placeholderVisible ) {
		this.getView().$element.css( 'min-height', this.$placeholder.outerHeight() );
	}
};

/**
 * Get list of commands available on this surface.
 *
 * @return {string[]} Commands
 */
ve.ui.Surface.prototype.getCommands = function () {
	return this.commands;
};

/**
 * Execute an action or command.
 *
 * @param {ve.ui.Trigger|string} triggerOrAction Trigger or symbolic name of action
 * @param {string} [method] Action method name
 * @param {...Mixed} [args] Additional arguments for action
 * @return {boolean} Action or command was executed
 */
ve.ui.Surface.prototype.execute = function ( triggerOrAction, method ) {
	var command, obj, ret;

	if ( triggerOrAction instanceof ve.ui.Trigger ) {
		command = this.triggerListener.getCommandByTrigger( triggerOrAction.toString() );
		if ( command ) {
			// Have command call execute with action arguments
			return command.execute( this );
		}
	} else if ( typeof triggerOrAction === 'string' && typeof method === 'string' ) {
		// Validate method
		if ( ve.ui.actionFactory.doesActionSupportMethod( triggerOrAction, method ) ) {
			// Create an action object and execute the method on it
			obj = ve.ui.actionFactory.create( triggerOrAction, this );
			ret = obj[ method ].apply( obj, Array.prototype.slice.call( arguments, 2 ) );
			return ret === undefined || !!ret;
		}
	}
	return false;
};

/**
 * Execute a command by name
 *
 * @param {string} commandName Command name
 * @return {boolean} The command was executed
 */
ve.ui.Surface.prototype.executeCommand = function ( commandName ) {
	var command = this.commandRegistry.lookup( commandName );
	if ( command ) {
		return command.execute( this );
	}
	return false;
};

// Deprecated, use #setPadding
ve.ui.Surface.prototype.setToolbarHeight = function ( toolbarHeight ) {
	this.setPadding( { top: toolbarHeight } );
};

/**
 * Set content area padding.
 *
 * When UI components obscure the surface (e.g. the toolbar),
 * set the appropriate amount of padding here so that
 * scroll-into-view calculations can be adjusted.
 *
 * @param {Object} padding Padding object containing top, right, bottom
 *  and/or left values. Omit properties to leave unchanged.
 */
ve.ui.Surface.prototype.setPadding = function ( padding ) {
	ve.extendObject( this.padding, padding );
	// Deprecated, use this.padding.top
	this.toolbarHeight = this.padding.top;
};

/**
 * Handle resize events from the context
 */
ve.ui.Surface.prototype.onContextResize = function () {
	this.setPadding( { bottom: this.context.$element[ 0 ].clientHeight } );
	this.adjustVisiblePadding();
	this.scrollSelectionIntoView();
};

/**
 * Handle surface model blur events
 */
ve.ui.Surface.prototype.onModelBlur = function () {
	this.adjustVisiblePadding();
};

/**
 * Handle surface model focus events
 */
ve.ui.Surface.prototype.onModelFocus = function () {
	this.adjustVisiblePadding();
};

/**
 * Handle surface view activation events
 */
ve.ui.Surface.prototype.onViewActivation = function () {
	this.adjustVisiblePadding();
};

/**
 * Adjust visible padding on the surface to allow the whole document
 * to be scrolled to.
 */
ve.ui.Surface.prototype.adjustVisiblePadding = function () {
	var bottom, keyboardShown;
	if ( OO.ui.isMobile() && !this.getInDialog() ) {
		keyboardShown = this.getView().getSelection().isNativeCursor() &&
			!this.getView().isShownAsDeactivated();
		if ( ve.init.platform.constructor.static.isIos() && keyboardShown ) {
			// iOS needs a whole extra page of padding when the virtual keyboard is shown.
			// Note: we keep this padding when surface is deactivated-but-shown-as-activated
			// so that the view doesn't shift when e.g. opening a toolbar toolgroup popup.
			bottom = $( window ).height() - this.padding.top;
		} else {
			// otherwise just add padding to account for the context
			bottom = this.padding.bottom;
		}
		this.getView().$attachedRootNode.css( 'padding-bottom', bottom );
		this.scrollSelectionIntoView();
	}
};

/**
 * Create a progress bar in the progress dialog
 *
 * @param {jQuery.Promise} progressCompletePromise Promise which resolves when the progress action is complete
 * @param {jQuery|string|Function} label Progress bar label
 * @param {boolean} nonCancellable Progress item can't be cancelled
 * @return {jQuery.Promise} Promise which resolves with a progress bar widget and a promise which fails if cancelled
 */
ve.ui.Surface.prototype.createProgress = function ( progressCompletePromise, label, nonCancellable ) {
	var progressBarDeferred = ve.createDeferred();

	this.progresses.push( {
		label: label,
		cancellable: !nonCancellable,
		progressCompletePromise: progressCompletePromise,
		progressBarDeferred: progressBarDeferred
	} );

	this.showProgressDebounced();

	return progressBarDeferred.promise();
};

ve.ui.Surface.prototype.showProgress = function () {
	var progresses = this.progresses;

	this.dialogs.openWindow( 'progress', { progresses: progresses, $returnFocusTo: null } );
	this.progresses = [];
};

/**
 * Get sanitization rules for rich paste
 *
 * @return {Object} Import rules
 */
ve.ui.Surface.prototype.getImportRules = function () {
	return this.importRules;
};

/**
 * Check if the surface is multi-line
 *
 * @return {boolean} Surface is multi-line
 */
ve.ui.Surface.prototype.isMultiline = function () {
	return this.multiline;
};

/**
 * Surface 'dir' property (GUI/User-Level Direction)
 *
 * @return {string} 'ltr' or 'rtl'
 */
ve.ui.Surface.prototype.getDir = function () {
	return this.$element.css( 'direction' );
};

/**
 * Get the name of the dialog this surface is in
 *
 * @return {string} The name of the dialog this surface is in
 */
ve.ui.Surface.prototype.getInDialog = function () {
	return this.inDialog;
};
