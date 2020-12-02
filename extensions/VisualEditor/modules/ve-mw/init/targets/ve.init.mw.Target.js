/*!
 * VisualEditor MediaWiki Initialization Target class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Initialization MediaWiki target.
 *
 * @class
 * @extends ve.init.Target
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.init.mw.Target = function VeInitMwTarget( config ) {
	// Parent constructor
	ve.init.mw.Target.super.call( this, config );

	this.active = false;
	this.pageName = mw.config.get( 'wgRelevantPageName' );
	this.recovered = false;
	this.fromEditedState = false;
	this.originalHtml = null;

	// Initialization
	this.$element.addClass( 've-init-mw-target' );
};

/* Inheritance */

OO.inheritClass( ve.init.mw.Target, ve.init.Target );

/* Static Properties */

/**
 * Symbolic name for this target class.
 *
 * @static
 * @property {string}
 * @inheritable
 */
ve.init.mw.Target.static.name = null;

ve.init.mw.Target.static.toolbarGroups = [
	{
		name: 'history',
		include: [ 'undo', 'redo' ]
	},
	{
		name: 'format',
		type: 'menu',
		title: OO.ui.deferMsg( 'visualeditor-toolbar-format-tooltip' ),
		include: [ { group: 'format' } ],
		promote: [ 'paragraph' ],
		demote: [ 'preformatted', 'blockquote', 'heading1' ]
	},
	{
		name: 'style',
		type: 'list',
		icon: 'textStyle',
		title: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
		label: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
		invisibleLabel: true,
		include: [ { group: 'textStyle' }, 'language', 'clear' ],
		forceExpand: [ 'bold', 'italic', 'clear' ],
		promote: [ 'bold', 'italic' ],
		demote: [ 'strikethrough', 'code', 'underline', 'language', 'big', 'small', 'clear' ]
	},
	{
		name: 'link',
		include: [ 'link' ]
	},
	// Placeholder for reference tools (e.g. Cite and/or Citoid)
	{
		name: 'reference'
	},
	{
		name: 'structure',
		type: 'list',
		icon: 'listBullet',
		title: OO.ui.deferMsg( 'visualeditor-toolbar-structure' ),
		label: OO.ui.deferMsg( 'visualeditor-toolbar-structure' ),
		invisibleLabel: true,
		include: [ { group: 'structure' } ],
		demote: [ 'outdent', 'indent' ]
	},
	{
		name: 'insert',
		label: OO.ui.deferMsg( 'visualeditor-toolbar-insert' ),
		title: OO.ui.deferMsg( 'visualeditor-toolbar-insert' ),
		include: '*',
		forceExpand: [ 'media', 'transclusion', 'insertTable' ],
		promote: [ 'media', 'transclusion', 'insertTable' ]
	},
	{
		name: 'specialCharacter',
		include: [ 'specialCharacter' ]
	}
];

ve.init.mw.Target.static.importRules = ve.copy( ve.init.mw.Target.static.importRules );

ve.init.mw.Target.static.importRules.external.removeOriginalDomElements = true;

ve.init.mw.Target.static.importRules.external.blacklist = ve.extendObject( {
	// Annotations
	'textStyle/underline': true,
	'meta/language': true,
	'textStyle/datetime': true,
	'link/mwExternal': !mw.config.get( 'wgVisualEditorConfig' ).allowExternalLinkPaste,
	// Node
	article: true,
	section: true
}, ve.init.mw.Target.static.importRules.external.blacklist );

ve.init.mw.Target.static.importRules.external.htmlBlacklist.remove = ve.extendObject( {
	// TODO: Create a plugin system for extending the blacklist, so this code
	// can be moved to the Cite extension.
	// Remove reference numbers copied from MW read mode (T150418)
	'sup.reference:not( [typeof] )': true,
	// ...sometimes we need a looser match if the HTML has been mangled
	// in a third-party editor e.g. LibreOffice (T232461)
	// This selector would fail if the "cite_reference_link_prefix" message
	// were ever modified, but currently it isn't.
	'a[ href *= "#cite_note" ]': true
}, ve.init.mw.Target.static.importRules.external.htmlBlacklist.remove );

/**
 * Type of integration. Used by ve.init.mw.trackSubscriber.js for event tracking.
 *
 * @static
 * @property {string}
 * @inheritable
 */
ve.init.mw.Target.static.integrationType = null;

/**
 * Type of platform. Used by ve.init.mw.trackSubscriber.js for event tracking.
 *
 * @static
 * @property {string}
 * @inheritable
 */
ve.init.mw.Target.static.platformType = null;

/* Static Methods */

/**
 * Fix the base URL from Parsoid if necessary.
 *
 * Absolutizes the base URL if it's relative, and sets a base URL based on wgArticlePath
 * if there was no base URL at all.
 *
 * @param {HTMLDocument} doc Parsoid document
 */
ve.init.mw.Target.static.fixBase = function ( doc ) {
	ve.fixBase( doc, document, ve.resolveUrl(
		// Don't replace $1 with the page name, because that'll break if
		// the page name contains a slash
		mw.config.get( 'wgArticlePath' ).replace( '$1', '' ),
		document
	) );
};

/**
 * @inheritdoc
 */
ve.init.mw.Target.static.createModelFromDom = function ( doc, mode, options ) {
	var conf = mw.config.get( 'wgVisualEditor' );

	options = ve.extendObject( {
		lang: conf.pageLanguageCode,
		dir: conf.pageLanguageDir
	}, options );

	// Parent method
	return ve.init.mw.Target.super.static.createModelFromDom.call( this, doc, mode, options );
};

// Deprecated alias
ve.init.mw.Target.prototype.createModelFromDom = function () {
	return this.constructor.static.createModelFromDom.apply( this.constructor.static, arguments );
};

/**
 * @inheritdoc
 * @param {string} documentString
 * @param {string} mode
 * @param {string|null} [section] Section. Use null to unwrap all sections.
 * @param {boolean} [onlySection] Only return the requested section, otherwise returns the
 *  whole document with just the requested section still wrapped (visual mode only).
 * @return {HTMLDocument|string} HTML document, or document string (source mode)
 */
ve.init.mw.Target.static.parseDocument = function ( documentString, mode, section, onlySection ) {
	var doc, sectionNode;
	if ( mode === 'source' ) {
		// Parent method
		doc = ve.init.mw.Target.super.static.parseDocument.call( this, documentString, mode );
	} else {
		// Parsoid documents are XHTML so we can use parseXhtml which fixed some IE issues.
		doc = ve.parseXhtml( documentString );
		if ( section !== undefined ) {
			if ( onlySection ) {
				sectionNode = doc.body.querySelector( '[data-mw-section-id="' + section + '"]' );
				doc.body.innerHTML = '';
				if ( sectionNode ) {
					doc.body.appendChild( sectionNode );
				}
			} else {
				// Strip Parsoid sections
				mw.libs.ve.unwrapParsoidSections( doc.body, section );
			}
		}
		// Strip legacy IDs, for example in section headings
		mw.libs.ve.stripParsoidFallbackIds( doc.body );
		// Fix relative or missing base URL if needed
		this.fixBase( doc );
	}

	return doc;
};

/* Methods */

/**
 * Handle both DOM and modules being loaded and ready.
 *
 * @param {HTMLDocument|string} doc HTML document or source text
 */
ve.init.mw.Target.prototype.documentReady = function ( doc ) {
	this.setupSurface( doc );
};

/**
 * Once surface is ready, initialize the UI
 *
 * @fires surfaceReady
 */
ve.init.mw.Target.prototype.surfaceReady = function () {
	this.emit( 'surfaceReady' );
};

/**
 * @deprecated Moved to mw.libs.ve.targetSaver.getHtml
 * @param {HTMLDocument} newDoc
 * @param {HTMLDocument} [oldDoc]
 * @return {string}
 */
ve.init.mw.Target.prototype.getHtml = function ( newDoc, oldDoc ) {
	OO.ui.warnDeprecation( 've.init.mw.Target#getHtml is deprecated. Use mw.libs.ve.targetSaver.getHtml.' );
	return mw.libs.ve.targetSaver.getHtml( newDoc, oldDoc );
};

/**
 * Track an event
 *
 * @param {string} name Event name
 */
ve.init.mw.Target.prototype.track = function () {};

/**
 * @inheritdoc
 */
ve.init.mw.Target.prototype.createTargetWidget = function ( config ) {
	return new ve.ui.MWTargetWidget( ve.extendObject( {
		// Reset to visual mode for target widgets
		modes: [ 'visual' ],
		defaultMode: 'visual',
		toolbarGroups: this.toolbarGroups
	}, config ) );
};

/**
 * @inheritdoc
 */
ve.init.mw.Target.prototype.createSurface = function ( dmDoc, config ) {
	var importRules;

	if ( config && config.mode === 'source' ) {
		importRules = ve.copy( this.constructor.static.importRules );
		importRules.all = importRules.all || {};
		// Preserve empty linebreaks on paste in source editor
		importRules.all.keepEmptyContentBranches = true;
		config = this.getSurfaceConfig( ve.extendObject( {}, config, {
			importRules: importRules
		} ) );
		return new ve.ui.MWWikitextSurface( dmDoc, config );
	}

	return new ve.ui.MWSurface( dmDoc, this.getSurfaceConfig( config ) );
};

/**
 * @inheritdoc
 */
ve.init.mw.Target.prototype.getSurfaceConfig = function ( config ) {
	// If we're not asking for a specific mode's config, use the default mode.
	config = ve.extendObject( { mode: this.defaultMode }, config );
	return ve.init.mw.Target.super.prototype.getSurfaceConfig.call( this, ve.extendObject( {
		// Provide the wikitext versions of the registries, if we're using source mode
		commandRegistry: config.mode === 'source' ? ve.ui.wikitextCommandRegistry : ve.ui.commandRegistry,
		sequenceRegistry: config.mode === 'source' ? ve.ui.wikitextSequenceRegistry : ve.ui.sequenceRegistry,
		dataTransferHandlerFactory: config.mode === 'source' ? ve.ui.wikitextDataTransferHandlerFactory : ve.ui.dataTransferHandlerFactory
	}, config ) );
};

/**
 * Switch to editing mode.
 *
 * @param {HTMLDocument|string} doc HTML document or source text
 */
ve.init.mw.Target.prototype.setupSurface = function ( doc ) {
	var target = this;
	setTimeout( function () {
		// Build model
		var dmDoc;

		target.track( 'trace.convertModelFromDom.enter' );
		dmDoc = target.constructor.static.createModelFromDom( doc, target.getDefaultMode() );
		target.track( 'trace.convertModelFromDom.exit' );

		// Build DM tree now (otherwise it gets lazily built when building the CE tree)
		target.track( 'trace.buildModelTree.enter' );
		dmDoc.buildNodeTree();
		target.track( 'trace.buildModelTree.exit' );

		setTimeout( function () {
			target.addSurface( dmDoc );
		} );
	} );
};

/**
 * @inheritdoc
 */
ve.init.mw.Target.prototype.addSurface = function () {
	var surface,
		target = this;

	// Clear dummy surfaces
	// TODO: Move to DesktopArticleTarget
	this.clearSurfaces();

	// Create ui.Surface (also creates ce.Surface and dm.Surface and builds CE tree)
	this.track( 'trace.createSurface.enter' );
	// Parent method
	surface = ve.init.mw.Target.super.prototype.addSurface.apply( this, arguments );
	// Add classes specific to surfaces attached directly to the target,
	// as opposed to TargetWidget surfaces
	surface.$element.addClass( 've-init-mw-target-surface' );
	this.track( 'trace.createSurface.exit' );

	this.setSurface( surface );

	setTimeout( function () {
		// Initialize surface
		target.track( 'trace.initializeSurface.enter' );

		target.active = true;
		// Now that the surface is attached to the document and ready,
		// let it initialize itself
		surface.initialize();

		target.track( 'trace.initializeSurface.exit' );
		target.surfaceReady();
	} );

	return surface;
};

/**
 * @inheritdoc
 */
ve.init.mw.Target.prototype.setSurface = function ( surface ) {
	if ( !surface.$element.parent().length ) {
		this.$element.append( surface.$element );
	}

	// Parent method
	ve.init.mw.Target.super.prototype.setSurface.apply( this, arguments );
};

/**
 * Intiailise autosave, recovering changes if applicable
 */
ve.init.mw.Target.prototype.initAutosave = function () {
	var target = this,
		surfaceModel = this.getSurface().getModel();
	if ( this.recovered ) {
		// Restore auto-saved transactions if document state was recovered
		try {
			surfaceModel.restoreChanges();
			ve.init.platform.notify(
				ve.msg( 'visualeditor-autosave-recovered-text' ),
				ve.msg( 'visualeditor-autosave-recovered-title' )
			);
		} catch ( e ) {
			mw.log.warn( e );
			ve.init.platform.notify(
				ve.msg( 'visualeditor-autosave-not-recovered-text' ),
				ve.msg( 'visualeditor-autosave-not-recovered-title' ),
				{ type: 'error' }
			);
		}
	} else {
		// ...otherwise store this document state for later recovery
		if ( this.fromEditedState ) {
			// Store immediately if the document was previously edited
			// (e.g. in a different mode)
			this.storeDocState( this.originalHtml );
		} else {
			// Only store after the first change if this is an unmodified document
			surfaceModel.once( 'undoStackChange', function () {
				// Check the surface hasn't been destroyed
				if ( target.getSurface() ) {
					target.storeDocState( target.originalHtml );
				}
			} );
		}
	}
	// Start auto-saving transactions
	surfaceModel.startStoringChanges();
	// TODO: Listen to autosaveFailed event to notify user
};

/**
 * Store a snapshot of the current document state.
 *
 * @param {string} [html] Document HTML, will generate from current state if not provided
 */
ve.init.mw.Target.prototype.storeDocState = function ( html ) {
	var mode = this.getSurface().getMode();
	this.getSurface().getModel().storeDocState( { mode: mode }, html );
};

/**
 * Clear any stored document state
 */
ve.init.mw.Target.prototype.clearDocState = function () {
	if ( this.getSurface() ) {
		this.getSurface().getModel().removeDocStateAndChanges();
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.Target.prototype.teardown = function () {
	// If target is closed cleanly (after save or deliberate close) then remove autosave state
	this.clearDocState();

	// Parent method
	return ve.init.mw.Target.super.prototype.teardown.call( this );
};

/**
 * Refresh our stored edit/csrf token
 *
 * This should be called in response to a badtoken error, to resolve whether the
 * token was expired / the user changed. If the user did change, this updates
 * the current user.
 *
 * @param {ve.dm.Document} [doc] Document to associate with the API request
 * @return {jQuery.Promise} Promise resolved with new username, or null if anonymous
 */
ve.init.mw.Target.prototype.refreshUser = function ( doc ) {
	return this.getContentApi( doc ).get( {
		action: 'query',
		meta: 'userinfo'
	} ).then( function ( data ) {
		var userInfo = data.query && data.query.userinfo;

		if ( !userInfo ) {
			return ve.createDeferred().reject();
		}

		if ( userInfo.anon !== undefined ) {
			// New session is an anonymous user
			mw.config.set( {
				// wgUserId is unset for anonymous users, not set to null
				wgUserId: undefined,
				// wgUserName is explicitly set to null for anonymous users,
				// functions like mw.user.isAnon rely on this.
				wgUserName: null
			} );
		} else {
			// New session is a logged in user
			mw.config.set( {
				wgUserId: userInfo.id,
				wgUserName: userInfo.name
			} );
		}

		return mw.user.getName();
	} );
};

/**
 * Get a wikitext fragment from a document
 *
 * @param {ve.dm.Document} doc Document
 * @param {boolean} [useRevision=true] Whether to use the revision ID + ETag
 * @return {jQuery.Promise} Abortable promise which resolves with a wikitext string
 */
ve.init.mw.Target.prototype.getWikitextFragment = function ( doc, useRevision ) {
	var xhr, params;

	// Shortcut for empty document
	if ( !doc.data.hasContent() ) {
		return ve.createDeferred().resolve( '' );
	}

	params = {
		action: 'visualeditoredit',
		paction: 'serialize',
		html: ve.dm.converter.getDomFromModel( doc ).body.innerHTML,
		page: this.getPageName()
	};

	if ( useRevision === undefined || useRevision ) {
		params.oldid = this.revid;
		params.etag = this.etag;
	}

	xhr = this.getContentApi( doc ).postWithToken( 'csrf',
		params,
		{ contentType: 'multipart/form-data' }
	);

	return xhr.then( function ( response ) {
		if ( response.visualeditoredit ) {
			return response.visualeditoredit.content;
		}
		return ve.createDeferred().reject();
	} ).promise( { abort: xhr.abort } );
};

/**
 * Parse a fragment of wikitext into HTML
 *
 * @param {string} wikitext Wikitext
 * @param {boolean} pst Perform pre-save transform
 * @param {ve.dm.Document} [doc] Parse for a specific document, defaults to current surface's
 * @return {jQuery.Promise} Abortable promise
 */
ve.init.mw.Target.prototype.parseWikitextFragment = function ( wikitext, pst, doc ) {
	return this.getContentApi( doc ).post( {
		action: 'visualeditor',
		paction: 'parsefragment',
		page: this.getPageName( doc ),
		wikitext: wikitext,
		pst: pst
	} );
};

/**
 * Get the page name associated with a specific document
 *
 * @param {ve.dm.Document} [doc] Document, defaults to current surface's
 * @return {string} Page name
 */
ve.init.mw.Target.prototype.getPageName = function () {
	return this.pageName;
};

/**
 * Get an API object associated with the wiki where the document
 * content is hosted.
 *
 * This would be overridden if editing content on another wiki.
 *
 * @param {ve.dm.Document} [doc] API for a specific document, should default to document of current surface.
 * @param {Object} [options] API options
 * @return {mw.Api} API object
 */
ve.init.mw.Target.prototype.getContentApi = function ( doc, options ) {
	options = options || {};
	options.parameters = ve.extendObject( { formatversion: 2 }, options.parameters );
	return new mw.Api( options );
};

/**
 * Get an API object associated with the local wiki.
 *
 * For example you would always use getLocalApi for actions
 * associated with the current user.
 *
 * @param {Object} [options] API options
 * @return {mw.Api} API object
 */
ve.init.mw.Target.prototype.getLocalApi = function ( options ) {
	options = options || {};
	options.parameters = ve.extendObject( { formatversion: 2 }, options.parameters );
	return new mw.Api( options );
};
