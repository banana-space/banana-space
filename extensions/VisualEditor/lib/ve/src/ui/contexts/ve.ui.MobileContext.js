/*!
 * VisualEditor UserInterface MobileContext class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * UserInterface context that displays inspector full screen.
 *
 * @class
 * @extends ve.ui.LinearContext
 *
 * @constructor
 * @param {ve.ui.Surface} surface
 * @param {Object} [config] Configuration options
 */
ve.ui.MobileContext = function VeUiMobileContext() {
	// Parent constructor
	ve.ui.MobileContext.super.apply( this, arguments );

	// Events
	this.inspectors.connect( this, {
		setup: [ 'toggle', true ],
		teardown: [ 'toggle', false ]
	} );

	this.openingTimeout = null;

	// Initialization
	this.$element.addClass( 've-ui-mobileContext' );
	this.$group.addClass( 've-ui-mobileContext-menu' );
	this.surface.getGlobalOverlay().$element.append( this.inspectors.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.MobileContext, ve.ui.LinearContext );

/* Static Properties */

ve.ui.MobileContext.static.isMobile = true;

ve.ui.MobileContext.static.showDeleteButton = true;

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MobileContext.prototype.createInspectorWindowManager = function () {
	return new ve.ui.MobileWindowManager( this.surface, {
		factory: ve.ui.windowFactory,
		overlay: this.surface.getGlobalOverlay()
	} );
};

/**
 * Stripped down version of onInspectorOpening as we don't need to hide/show
 * the context because the inspector is full screen.
 *
 * @inheritdoc
 */
ve.ui.MobileContext.prototype.onInspectorOpening = function ( win, opening ) {
	var context = this,
		observer = this.surface.getView().surfaceObserver;

	this.inspector = win;

	// Shut down the SurfaceObserver as soon as possible, so it doesn't get confused
	// by the selection moving around in IE. Will be reenabled when inspector closes.
	// FIXME this should be done in a nicer way, managed by the Surface classes
	observer.pollOnce();
	observer.stopTimerLoop();

	opening
		.then( function ( opened ) {
			opened.then( function ( closed ) {
				closed.always( function () {
					context.inspector = null;
					// Reenable observer
					observer.startTimerLoop();
					context.afterContextChange();
				} );
			} );
		} );
};

/**
 * @inheritdoc
 */
ve.ui.MobileContext.prototype.toggleMenu = function ( show ) {
	var context = this;
	show = show === undefined ? !this.choosing : !!show;

	if ( show !== this.choosing ) {
		if ( show ) {
			if ( this.hideMenuTimeout ) {
				clearTimeout( this.hideMenuTimeout );
				// Ensure menu is torn down before setting up again.
				ve.ui.MobileContext.super.prototype.toggleMenu.call( this, false );
			}
			// Parent method
			ve.ui.MobileContext.super.prototype.toggleMenu.call( this, true );
		} else {
			this.hideMenuTimeout = setTimeout( function () {
				// Parent method
				ve.ui.MobileContext.super.prototype.toggleMenu.call( context, false );
			}, 100 );
		}
	}

	return this;
};

/**
 * @inheritdoc
 */
ve.ui.MobileContext.prototype.toggle = function ( show ) {
	var deferred,
		context = this;

	show = show === undefined ? !this.visible : !!show;
	if ( show && !this.visible ) {
		deferred = ve.createDeferred();
		// Set opening flag immediately
		this.openingTimeout = setTimeout( function () {
			// Parent method
			ve.ui.MobileContext.super.prototype.toggle.call( context, true );
			context.emit( 'resize' );
			deferred.resolve();
			context.openingTimeout = null;
		}, 250 );
		return deferred;
	} else {
		if ( this.openingTimeout ) {
			clearTimeout( this.openingTimeout );
			this.openingTimeout = null;
		}
		setTimeout( function () {
			context.emit( 'resize' );
		}, 100 );
		// Parent method
		return ve.ui.MobileContext.super.prototype.toggle.call( context, show );
	}
};

/**
 * @inheritdoc
 */
ve.ui.MobileContext.prototype.isVisible = function () {
	return this.visible || !!this.openingTimeout;
};

/**
 * @inheritdoc
 */
ve.ui.MobileContext.prototype.isInspectable = function () {
	return ve.ui.MobileContext.super.prototype.isInspectable.call( this ) &&
		// Suppress context when surface is active (virtual keyboard)
		this.surface.getView().isDeactivated();
};
