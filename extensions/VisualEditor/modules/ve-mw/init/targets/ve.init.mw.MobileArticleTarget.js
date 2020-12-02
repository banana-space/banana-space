/*!
 * VisualEditor MediaWiki Initialization MobileArticleTarget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * @class VisualEditorOverlay
 * TODO: Use @-external when we switch to JSDoc
 */

/**
 * MediaWiki mobile article target.
 *
 * @class
 * @extends ve.init.mw.ArticleTarget
 *
 * @constructor
 * @param {VisualEditorOverlay} overlay Mobile frontend overlay
 * @param {Object} [config] Configuration options
 * @cfg {string|null} [section] Number of the section target should scroll to
 */
ve.init.mw.MobileArticleTarget = function VeInitMwMobileArticleTarget( overlay, config ) {
	this.overlay = overlay;
	this.$overlay = overlay.$el;
	this.$overlaySurface = overlay.$el.find( '.surface' );

	config = config || {};
	config.toolbarConfig = $.extend( {
		actions: false
	}, config.toolbarConfig );

	// Parent constructor
	ve.init.mw.MobileArticleTarget.super.call( this, config );

	if ( config.section !== undefined ) {
		this.section = config.section;
	}

	// Initialization
	this.$element.addClass( 've-init-mw-mobileArticleTarget ve-init-mobileTarget' );
};

/* Inheritance */

OO.inheritClass( ve.init.mw.MobileArticleTarget, ve.init.mw.ArticleTarget );

/* Static Properties */

ve.init.mw.MobileArticleTarget.static.toolbarGroups = [
	// History
	{
		name: 'history',
		include: [ 'undo' ]
	},
	// Style
	{
		name: 'style',
		classes: [ 've-test-toolbar-style' ],
		type: 'list',
		icon: 'textStyle',
		title: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
		label: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
		invisibleLabel: true,
		include: [ { group: 'textStyle' }, 'language', 'clear' ],
		forceExpand: [ 'bold', 'italic', 'clear' ],
		promote: [ 'bold', 'italic' ],
		demote: [ 'strikethrough', 'code', 'underline', 'language', 'clear' ]
	},
	// Link
	{
		name: 'link',
		include: [ 'link' ]
	},
	// Placeholder for reference tools (e.g. Cite and/or Citoid)
	{
		name: 'reference'
	}
];

ve.init.mw.MobileArticleTarget.static.trackingName = 'mobile';

// FIXME Some of these users will be on tablets, check for this
ve.init.mw.MobileArticleTarget.static.platformType = 'phone';

/* Methods */

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.deactivateSurfaceForToolbar = function () {
	// Parent method
	ve.init.mw.MobileArticleTarget.super.prototype.deactivateSurfaceForToolbar.call( this );

	if ( this.wasSurfaceActive && ve.init.platform.constructor.static.isIos() ) {
		this.prevScrollPosition = this.getSurface().$scrollContainer.scrollTop();
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.activateSurfaceForToolbar = function () {
	// Parent method
	ve.init.mw.MobileArticleTarget.super.prototype.activateSurfaceForToolbar.call( this );

	if ( this.wasSurfaceActive && ve.init.platform.constructor.static.isIos() ) {
		// Setting the cursor can cause unwanted scrolling on iOS, so manually
		// restore the scroll offset from before the toolbar was opened (T218650).
		this.getSurface().$scrollContainer.scrollTop( this.prevScrollPosition );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.clearSurfaces = function () {
	if ( ve.init.platform.constructor.static.isIos() && this.viewportZoomHandler ) {
		this.viewportZoomHandler.detach();
		this.viewportZoomHandler = null;
	}
	// Parent method
	ve.init.mw.MobileArticleTarget.super.prototype.clearSurfaces.call( this );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.onContainerScroll = function () {
	var target = this,
		// Editor may not have loaded yet, in which case `this.surface` is undefined
		surfaceView = this.surface && this.surface.getView(),
		isActiveWithKeyboard = surfaceView && surfaceView.isFocused() && !surfaceView.isDeactivated();

	// On iOS Safari, when the keyboard is open, the layout viewport reported by the browser is not
	// updated to match the real viewport reduced by the keyboard (diagram: T218414#5027607). On all
	// modern non-iOS browsers the layout viewport is updated to match real viewport.
	//
	// This allows the fixed toolbar to be scrolled out of view, ignoring `position: fixed` (because
	// it refers to the layout viewport).
	//
	// When this happens, bring it back in by scrolling down a bit and back up until the top of the
	// fake viewport is aligned with the top of the real viewport.

	clearTimeout( this.onContainerScrollTimer );
	if ( !isActiveWithKeyboard ) {
		return;
	}

	// Wait until after the scroll, because 'scroll' events are not emitted for every frame the
	// browser paints, so the toolbar would lag behind in a very unseemly manner. Additionally,
	// getBoundingClientRect returns incorrect values during scrolling, so make sure to calculate
	// it only after the scrolling ends (https://openradar.appspot.com/radar?id=6668472289329152).
	this.onContainerScrollTimer = setTimeout( function () {
		var pos, viewportHeight, scrollX, scrollY, headerHeight, headerTranslateY,
			$header = target.overlay.$el.find( '.overlay-header-container' ),
			$overlaySurface = target.$overlaySurface;

		// Check if toolbar is offscreen. In a better world, this would reject all negative values
		// (pos >= 0), but getBoundingClientRect often returns funny small fractional values after
		// this function has done its job (which triggers another 'scroll' event) and before the
		// user scrolled again. If we allowed it to run, it would trigger a hilarious loop! Toolbar
		// being 1px offscreen is not a big deal anyway.
		pos = $header[ 0 ].getBoundingClientRect().top;
		if ( pos >= -1 ) {
			return;
		}

		// We don't know how much we have to scroll because we don't know how large the real
		// viewport is. This value is bigger than the screen height of all iOS devices.
		viewportHeight = 2000;
		// OK so this one is really weird. Normally on iOS, the scroll position is set on <body>.
		// But on our sites, when using iOS 13, it's on <html> instead - maybe due to some funny
		// CSS we set on html and body? Anyway, this seems to work...
		scrollY = document.body.scrollTop || document.documentElement.scrollTop;
		scrollX = document.body.scrollLeft || document.documentElement.scrollLeft;

		// Scroll down and translate the surface by the same amount, otherwise the content at new
		// scroll position visibly flashes.
		$overlaySurface.css( 'transform', 'translateY( ' + viewportHeight + 'px )' );
		window.scroll( scrollX, scrollY + viewportHeight );

		// (Note that the scrolling we just did will naturally trigger another 'scroll' event,
		// and run this handler again after 250ms. This is okay.)

		// Prepate to animate toolbar sliding into view
		$header.removeClass( 'toolbar-shown toolbar-shown-done' );
		headerHeight = $header[ 0 ].offsetHeight;
		headerTranslateY = Math.max( -headerHeight, pos );
		$header.css( 'transform', 'translateY( ' + headerTranslateY + 'px )' );

		// The scroll back up must be after a delay, otherwise no scrolling happens and the
		// viewports are not aligned.
		setTimeout( function () {
			// Scroll back up
			$overlaySurface.css( 'transform', '' );
			window.scroll( scrollX, scrollY );

			// Animate toolbar sliding into view
			$header.addClass( 'toolbar-shown' ).css( 'transform', '' );
			setTimeout( function () {
				$header.addClass( 'toolbar-shown-done' );
			}, 250 );
			// If the delays below are made any smaller, the weirdest graphical glitches happen,
			// so don't mess with them
		}, 50 );
	}, 250 );
};

/**
 * Handle surface scroll events
 */
ve.init.mw.MobileArticleTarget.prototype.onSurfaceScroll = function () {
	var nativeSelection, range;

	if ( ve.init.platform.constructor.static.isIos() && this.getSurface() ) {
		// iOS has a bug where if you change the scroll offset of a
		// contentEditable or textarea with a cursor visible, it disappears.
		// This function works around it by removing and reapplying the selection.
		nativeSelection = this.getSurface().getView().nativeSelection;
		if ( nativeSelection.rangeCount && document.activeElement.contentEditable === 'true' ) {
			range = nativeSelection.getRangeAt( 0 );
			nativeSelection.removeAllRanges();
			nativeSelection.addRange( range );
		}
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.createSurface = function ( dmDoc, config ) {
	var surface;
	if ( this.overlay.isNewPage ) {
		config = ve.extendObject( {
			placeholder: this.overlay.options.placeholder
		}, config );
	}

	// Parent method
	surface = ve.init.mw.MobileArticleTarget
		.super.prototype.createSurface.call( this, dmDoc, config );

	surface.connect( this, { scroll: 'onSurfaceScroll' } );

	return surface;
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.setSurface = function ( surface ) {
	var changed = surface !== this.surface;

	// Parent method
	// FIXME This actually skips ve.init.mw.Target.prototype.setSurface. Why?
	ve.init.mw.Target.super.prototype.setSurface.apply( this, arguments );

	if ( changed ) {
		surface.$element.addClass( 'content' );
		this.$overlaySurface.append( surface.$element );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.surfaceReady = function () {
	var surfaceModel;

	if ( this.teardownPromise ) {
		// Loading was cancelled, the overlay is already closed at this point. Do nothing.
		// Otherwise e.g. scrolling from #goToHeading would kick in and mess things up.
		return;
	}

	// Calls scrollSelectionIntoView so must be called before parent,
	// which calls goToHeading. (T225292)
	this.adjustContentPadding();

	// Deactivate the surface so any initial selection set in surfaceReady
	// listeners doesn't cause the keyboard to be shown.
	this.getSurface().getView().deactivate( false );

	// Parent method
	ve.init.mw.MobileArticleTarget.super.prototype.surfaceReady.apply( this, arguments );

	// If no selection has been set yet, set it to the start of the document.
	surfaceModel = this.getSurface().getModel();
	if ( surfaceModel.getSelection().isNull() ) {
		surfaceModel.selectFirstContentOffset();
	}

	this.events.trackActivationComplete();

	if ( ve.init.platform.constructor.static.isIos() ) {
		if ( this.viewportZoomHandler ) {
			this.viewportZoomHandler.detach();
		}
		this.viewportZoomHandler = new ve.init.mw.ViewportZoomHandler();
		this.viewportZoomHandler.attach( this.getSurface() );
	}
};

/**
 * Match the content padding to the toolbar height
 */
ve.init.mw.MobileArticleTarget.prototype.adjustContentPadding = function () {
	var surface = this.getSurface(),
		surfaceView = surface.getView(),
		toolbarHeight = this.getToolbar().$element[ 0 ].clientHeight;

	surface.setPadding( {
		top: toolbarHeight
	} );
	surfaceView.$attachedRootNode.css( 'padding-top', toolbarHeight );
	surface.$placeholder.css( 'padding-top', toolbarHeight );
	surfaceView.emit( 'position' );
	surface.scrollSelectionIntoView();
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.getSaveButtonLabel = function ( startProcess ) {
	var suffix = startProcess ? '-start' : '';
	// The following messages can be used here:
	// * visualeditor-savedialog-label-publish-short
	// * visualeditor-savedialog-label-publish-short-start
	// * visualeditor-savedialog-label-save-short
	// * visualeditor-savedialog-label-save-short-start
	if ( mw.config.get( 'wgEditSubmitButtonLabelPublish' ) ) {
		return OO.ui.deferMsg( 'visualeditor-savedialog-label-publish-short' + suffix );
	}

	return OO.ui.deferMsg( 'visualeditor-savedialog-label-save-short' + suffix );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.loadFail = function ( code, errorDetails ) {
	// Parent method
	ve.init.mw.MobileArticleTarget.super.prototype.loadFail.apply( this, arguments );

	window.history.back();
	mw.notify( this.extractErrorMessages( errorDetails ) );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.switchToFallbackWikitextEditor = function ( modified ) {
	var dataPromise;
	if ( modified ) {
		dataPromise = this.getWikitextDataPromiseForDoc( modified ).then( function ( response ) {
			var content = ve.getProp( response, 'visualeditoredit', 'content' );
			return { text: content };
		} );
	}
	this.overlay.switchToSourceEditor( dataPromise );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.save = function () {
	// Parent method
	ve.init.mw.MobileArticleTarget.super.prototype.save.apply( this, arguments );

	this.overlay.log( {
		action: 'saveAttempt'
	} );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.showSaveDialog = function () {
	// Parent method
	ve.init.mw.MobileArticleTarget.super.prototype.showSaveDialog.apply( this, arguments );

	this.overlay.log( {
		action: 'saveIntent'
	} );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.saveComplete = function ( data ) {
	// TODO: parsing this is expensive just for the section details. We should
	// change MobileFrontend+this to behave like desktop does and just rerender
	// the page with the provided HTML (T219420).
	var fragment = this.getSectionFragmentFromPage( $.parseHTML( data.content ) );
	// Parent method
	ve.init.mw.MobileArticleTarget.super.prototype.saveComplete.apply( this, arguments );

	this.overlay.sectionId = fragment;
	this.overlay.onSaveComplete( data.newrevid );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.saveFail = function ( doc, saveData, wasRetry, code, data ) {
	// parent method
	ve.init.mw.MobileArticleTarget.super.prototype.saveFail.apply( this, arguments );

	this.overlay.onSaveFailure( data );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.tryTeardown = function () {
	window.history.back();
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.load = function () {
	var surface;

	// Create dummy surface to show toolbar while loading
	// Call ve.init.Target directly to avoid firing surfaceReady
	surface = ve.init.Target.prototype.addSurface.call( this, new ve.dm.Document( [
		{ type: 'paragraph' }, { type: '/paragraph' },
		{ type: 'internalList' }, { type: '/internalList' }
	] ) );
	surface.setReadOnly( true );
	// setSurface creates dummy toolbar
	this.setSurface( surface );

	return ve.init.mw.MobileArticleTarget.super.prototype.load.apply( this, arguments );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.setupToolbar = function ( surface ) {
	var originalToolbarGroups = this.toolbarGroups;

	// We don't want any of these tools to show up in subordinate widgets, so we
	// temporarily add them here. We need to do it _here_ rather than in their
	// own static variable to make sure that other tools which meddle with
	// toolbarGroups (Cite, mostly) have a chance to do so.
	this.toolbarGroups = [].concat(
		[
			// Back
			{
				name: 'back',
				include: [ 'back' ]
			}
		],
		this.toolbarGroups,
		[
			{
				name: 'editMode',
				type: 'list',
				icon: 'edit',
				title: OO.ui.deferMsg( 'visualeditor-mweditmode-tooltip' ),
				label: OO.ui.deferMsg( 'visualeditor-mweditmode-tooltip' ),
				invisibleLabel: true,
				include: [ 'editModeVisual', 'editModeSource' ]
			},
			{
				name: 'save',
				type: 'bar',
				include: [ 'showMobileSave' ]
			}
		]
	);

	// Parent method
	ve.init.mw.MobileArticleTarget.super.prototype.setupToolbar.call( this, surface );

	this.toolbarGroups = originalToolbarGroups;

	this.toolbar.$group.addClass( 've-init-mw-mobileArticleTarget-editTools' );
	this.toolbar.$element.addClass( 've-init-mw-mobileArticleTarget-toolbar' );
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.attachToolbar = function () {
	// Move the toolbar to the overlay header
	this.overlay.$el.find( '.overlay-header > .toolbar' ).append( this.toolbar.$element );
	this.toolbar.initialize();
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.setupToolbarSaveButton = function () {
	this.toolbarSaveButton = this.toolbar.getToolGroupByName( 'save' ).items[ 0 ];
};

/**
 * @inheritdoc
 */
ve.init.mw.MobileArticleTarget.prototype.goToHeading = function ( headingNode ) {
	this.scrollToHeading( headingNode );
};

/**
 * Done with the editing toolbar
 */
ve.init.mw.MobileArticleTarget.prototype.done = function () {
	this.getSurface().getModel().setNullSelection();
	this.getSurface().getView().blur();
};

/* Registration */

ve.init.mw.targetFactory.register( ve.init.mw.MobileArticleTarget );

/**
 * Back tool
 */
ve.ui.MWBackTool = function VeUiMwBackTool() {
	// Parent constructor
	ve.ui.MWBackTool.super.apply( this, arguments );
};
OO.inheritClass( ve.ui.MWBackTool, ve.ui.Tool );
ve.ui.MWBackTool.static.name = 'back';
ve.ui.MWBackTool.static.group = 'navigation';
ve.ui.MWBackTool.static.icon = 'close';
ve.ui.MWBackTool.static.title =
	OO.ui.deferMsg( 'visualeditor-backbutton-tooltip' );
ve.ui.MWBackTool.static.commandName = 'back';

/** */
ve.ui.MWBackTool.prototype.onUpdateState = function () {
	// Parent method
	ve.ui.MWBackTool.super.prototype.onUpdateState.apply( this, arguments );

	this.setActive( false );
	this.setDisabled( false );
};

ve.ui.toolFactory.register( ve.ui.MWBackTool );

/**
 * Back command
 */
ve.ui.MWBackCommand = function VeUiMWBackCommand() {
	// Parent constructor
	ve.ui.MWBackCommand.super.call( this, 'back' );
};
OO.inheritClass( ve.ui.MWBackCommand, ve.ui.Command );
ve.ui.MWBackCommand.prototype.execute = function () {
	ve.init.target.tryTeardown();
};
ve.ui.commandRegistry.register( new ve.ui.MWBackCommand() );

/**
 * Mobile save tool
 */
ve.ui.MWMobileSaveTool = function VeUiMWMobileSaveTool() {
	// Parent Constructor
	ve.ui.MWMobileSaveTool.super.apply( this, arguments );
};
OO.inheritClass( ve.ui.MWMobileSaveTool, ve.ui.MWSaveTool );
ve.ui.MWMobileSaveTool.static.name = 'showMobileSave';
ve.ui.MWMobileSaveTool.static.icon = 'next';
ve.ui.MWMobileSaveTool.static.displayBothIconAndLabel = false;

ve.ui.toolFactory.register( ve.ui.MWMobileSaveTool );
