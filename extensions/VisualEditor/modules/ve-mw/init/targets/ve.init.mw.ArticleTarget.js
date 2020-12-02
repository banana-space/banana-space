/*!
 * VisualEditor MediaWiki Initialization ArticleTarget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/* eslint-disable no-jquery/no-global-selector */

/**
 * Initialization MediaWiki article target.
 *
 * @class
 * @extends ve.init.mw.Target
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.init.mw.ArticleTarget = function VeInitMwArticleTarget( config ) {
	var enableVisualSectionEditing;

	config = config || {};
	config.toolbarConfig = $.extend( {
		shadow: true,
		actions: true,
		floatable: true
	}, config.toolbarConfig );

	// Parent constructor
	ve.init.mw.ArticleTarget.super.call( this, config );

	// Register
	if ( config.register !== false ) {
		// ArticleTargets are never destroyed, but we can't trust ve.init.target to
		// not get overridden by other targets that may get created on the page.
		ve.init.articleTarget = this;
	}

	// Properties
	this.saveDialog = null;
	this.saveDeferred = null;
	this.saveFields = {};
	this.docToSave = null;
	this.originalDmDocPromise = null;
	this.originalHtml = null;
	this.toolbarSaveButton = null;
	this.pageExists = mw.config.get( 'wgRelevantArticleId', 0 ) !== 0;
	enableVisualSectionEditing = mw.config.get( 'wgVisualEditorConfig' ).enableVisualSectionEditing;
	this.enableVisualSectionEditing = enableVisualSectionEditing === true || enableVisualSectionEditing === this.constructor.static.trackingName;
	this.toolbarScrollOffset = mw.config.get( 'wgVisualEditorToolbarScrollOffset', 0 );
	// A workaround, as default URI does not get updated after pushState (T74334)
	this.currentUri = new mw.Uri( location.href );
	this.section = null;
	this.sectionTitle = null;
	this.editSummaryValue = null;
	this.initialEditSummary = null;
	this.initialCheckboxes = {};

	this.checkboxFields = null;
	this.checkboxesByName = null;
	this.$saveAccessKeyElements = null;

	// Sometimes we actually don't want to send a useful oldid
	// if we do, PostEdit will give us a 'page restored' message
	this.requestedRevId = mw.config.get( 'wgRevisionId' );
	this.currentRevisionId = mw.config.get( 'wgCurRevisionId' );
	this.revid = this.requestedRevId || this.currentRevisionId;

	this.edited = false;
	this.restoring = !!this.requestedRevId && this.requestedRevId !== this.currentRevisionId;
	this.pageDeletedWarning = false;
	this.submitUrl = ( new mw.Uri( mw.util.getUrl( this.getPageName() ) ) )
		.extend( {
			action: 'submit',
			veswitched: 1
		} );
	this.events = {
		track: function () {},
		trackActivationStart: function () {},
		trackActivationComplete: function () {}
	};

	this.preparedCacheKeyPromise = null;
	this.clearState();

	// Initialization
	this.$element.addClass( 've-init-mw-articleTarget' );
};

/* Inheritance */

OO.inheritClass( ve.init.mw.ArticleTarget, ve.init.mw.Target );

/* Events */

/**
 * @event editConflict
 */

/**
 * @event save
 * @param {Object} data Save data from the API, see ve.init.mw.ArticleTarget#saveComplete
 * Fired immediately after a save is successfully completed
 */

/**
 * @event showChanges
 */

/**
 * @event noChanges
 */

/**
 * @event saveErrorEmpty
 * Fired when save API returns no data object
 */

/**
 * @event saveErrorSpamBlacklist
 * Fired when save is considered spam or blacklisted
 * TODO: Move this to the extension
 */

/**
 * @event saveErrorAbuseFilter
 * Fired when AbuseFilter throws warnings
 * TODO: Move this to the extension
 */

/**
 * @event saveErrorBadToken
 * @param {boolean} willRetry Whether an automatic retry will occur
 * Fired on save if we have to fetch a new edit token.
 * This is mainly for analytical purposes.
 */

/**
 * @event saveErrorNewUser
 * Fired when user is logged in as a new user
 */

/**
 * @event saveErrorCaptcha
 * Fired when saveError indicates captcha field is required
 * TODO: Move this to the extension
 */

/**
 * @event saveErrorUnknown
 * @param {string} errorMsg Error message shown to the user
 * Fired for any other type of save error
 */

/**
 * @event saveErrorPageDeleted
 * Fired when user tries to save page that was deleted after opening VE
 */

/**
 * @event saveErrorTitleBlacklist
 * Fired when the user tries to save page in violation of the TitleBlacklist
 */

/**
 * @event saveErrorHookAborted
 * Fired when the user tries to save page in violation of an extension
 */

/**
 * @event saveErrorReadOnly
 * Fired when the user tries to save page but the database is locked
 */

/**
 * @event loadError
 */

/**
 * @event showChangesError
 */

/**
 * @event serializeError
 */

/**
 * @event serializeComplete
 * Fired when serialization is complete
 */

/* Static Properties */

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.static.name = 'article';

/**
 * Tracking name of target class. Used by ArticleTargetEvents to identify which target we are tracking.
 *
 * @static
 * @property {string}
 * @inheritable
 */
ve.init.mw.ArticleTarget.static.trackingName = 'mwTarget';

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.static.integrationType = 'page';

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.static.platformType = 'other';

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.static.documentCommands = ve.init.mw.ArticleTarget.super.static.documentCommands.concat( [
	// Make save commands triggerable from anywhere
	'showSave',
	'showChanges',
	'showPreview',
	'showMinoredit',
	'showWatchthis'
] );

/* Static methods */

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.static.parseDocument = function ( documentString, mode, section, onlySection ) {
	// Add trailing linebreak to non-empty wikitext documents for consistency
	// with old editor and usability. Will be stripped on save. T156609
	if ( mode === 'source' && documentString ) {
		documentString += '\n';
	}

	// Parent method
	return ve.init.mw.ArticleTarget.super.static.parseDocument.call( this, documentString, mode, section, onlySection );
};

/**
 * Build DOM for the redirect page subtitle (#redirectsub).
 *
 * @return {jQuery}
 */
ve.init.mw.ArticleTarget.static.buildRedirectSub = function () {
	// Page subtitle
	// Compare: Article::view()
	return $( '<span>' )
		.attr( 'id', 'redirectsub' )
		.append( mw.message( 'redirectpagesub' ).parseDom() );
};

/**
 * Build DOM for the redirect page content header (.redirectMsg).
 *
 * @param {string} title Redirect target
 * @return {jQuery}
 */
ve.init.mw.ArticleTarget.static.buildRedirectMsg = function ( title ) {
	var $link;

	$link = $( '<a>' )
		.attr( {
			href: mw.Title.newFromText( title ).getUrl(),
			title: mw.msg( 'visualeditor-redirect-description', title )
		} )
		.text( title );
	ve.init.platform.linkCache.styleElement( title, $link );

	// Page content header
	// Compare: Article::getRedirectHeaderHtml()
	return $( '<div>' )
		.addClass( 'redirectMsg' )
		// Hack: This is normally inside #mw-content-text, but we may insert it before, so we need this.
		// The following classes are used here:
		// * mw-content-ltr
		// * mw-content-rtl
		.addClass( 'mw-content-' + mw.config.get( 'wgVisualEditor' ).pageLanguageDir )
		.append(
			$( '<p>' ).text( mw.msg( 'redirectto' ) ),
			$( '<ul>' )
				.addClass( 'redirectText' )
				.append( $( '<li>' ).append( $link ) )
		);
};

/* Methods */

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.prototype.setDefaultMode = function () {
	var oldDefaultMode = this.defaultMode;
	// Parent method
	ve.init.mw.ArticleTarget.super.prototype.setDefaultMode.apply( this, arguments );

	if ( this.defaultMode !== oldDefaultMode ) {
		this.updateTabs( true );
		if ( mw.libs.ve.setEditorPreference ) {
			// only set up by DAT.init
			mw.libs.ve.setEditorPreference( this.defaultMode === 'visual' ? 'visualeditor' : 'wikitext' );
		}
	}
};

/**
 * Update state of editing tabs
 *
 * @param {boolean} editing Whether the editor is loaded.
 */
ve.init.mw.ArticleTarget.prototype.updateTabs = function ( editing ) {
	var $tab;

	if ( editing ) {
		if ( this.section === 'new' ) {
			$tab = $( '#ca-addsection' );
		} else if ( $( '#ca-ve-edit' ).length ) {
			if ( this.getDefaultMode() === 'visual' ) {
				$tab = $( '#ca-ve-edit' );
			} else {
				$tab = $( '#ca-edit' );
			}
		} else {
			// Single edit tab
			$tab = $( '#ca-edit' );
		}
	} else {
		$tab = $( '#ca-view' );
	}

	// Deselect current mode (e.g. "view" or "history") in skins that have
	// separate tab sections for content actions and namespaces, like Vector.
	$( '#p-views' ).find( 'li.selected' ).removeClass( 'selected' );
	// In skins like MonoBook that don't have the separate tab sections,
	// deselect the known tabs for editing modes (when switching or exiting editor).
	$( '#ca-edit, #ca-ve-edit, #ca-addsection' ).not( $tab ).removeClass( 'selected' );

	$tab.addClass( 'selected' );
};

/**
 * Handle response to a successful load request.
 *
 * This method is called within the context of a target instance. If successful the DOM from the
 * server will be parsed, stored in {this.doc} and then {this.documentReady} will be called.
 *
 * @param {Object} response API response data
 * @param {string} status Text status message
 */
ve.init.mw.ArticleTarget.prototype.loadSuccess = function ( response ) {
	var mode, section,
		data = response ? ( response.visualeditor || response.visualeditoredit ) : null;

	if ( !data || typeof data.content !== 'string' ) {
		this.loadFail( 've-api', { errors: [ {
			code: 've-api',
			html: mw.message( 'api-clientside-error-invalidresponse' ).parse()
		} ] } );
	} else if ( response.veMode && response.veMode !== this.getDefaultMode() ) {
		this.loadFail( 've-mode', { errors: [ {
			code: 've-mode',
			html: mw.message( 'visualeditor-loaderror-wrongmode',
				response.veMode, this.getDefaultMode() ).parse()
		} ] } );
	} else {
		this.track( 'trace.parseResponse.enter' );
		this.originalHtml = data.content;
		this.etag = data.etag;
		this.fromEditedState = !!data.fromEditedState;
		this.switched = data.switched || 'wteswitched' in new mw.Uri( location.href ).query;
		mode = this.getDefaultMode();
		section = ( mode === 'source' || this.enableVisualSectionEditing ) ? this.section : null;
		this.doc = this.constructor.static.parseDocument( this.originalHtml, mode, section );

		// Properties that don't come from the API
		this.initialSourceRange = data.initialSourceRange;
		this.recovered = data.recovered;

		// Parse data this not available in RESTBase
		if ( !this.parseMetadata( response ) ) {
			// Invalid metadata, loadFail() or load() has been called
			return;
		}

		this.track( 'trace.parseResponse.exit' );

		// Everything worked, the page was loaded, continue initializing the editor
		this.documentReady( this.doc );
	}

	if ( [ 'edit', 'submit' ].indexOf( mw.util.getParamValue( 'action' ) ) !== -1 ) {
		$( '#firstHeading' ).text(
			mw.Title.newFromText( this.getPageName() ).getPrefixedText()
		);
	}
};

/**
 * Parse document metadata from the API response
 *
 * @param {Object} response API response data
 * @return {boolean} Whether metadata was loaded successfully. If true, you should call
 *   loadSuccess(). If false, either that loadFail() has been called or we're retrying via load().
 */
ve.init.mw.ArticleTarget.prototype.parseMetadata = function ( response ) {
	var aboutDoc, docRevIdMatches, docRevId, checkboxes,
		data = response ? ( response.visualeditor || response.visualeditoredit ) : null;

	if ( !data ) {
		this.loadFail( 've-api', { errors: [ {
			code: 've-api',
			html: mw.message( 'api-clientside-error-invalidresponse' ).parse()
		} ] } );
		return false;
	}

	this.remoteNotices = ve.getObjectValues( data.notices );
	this.protectedClasses = data.protectedClasses;

	this.baseTimeStamp = data.basetimestamp;
	this.startTimeStamp = data.starttimestamp;
	this.revid = data.oldid;
	this.preloaded = !!data.preloaded;

	this.checkboxesDef = data.checkboxesDef;
	this.checkboxesMessages = data.checkboxesMessages;
	mw.messages.set( data.checkboxesMessages );

	this.canEdit = data.canEdit;

	aboutDoc = this.doc.documentElement && this.doc.documentElement.getAttribute( 'about' );
	if ( aboutDoc ) {
		docRevIdMatches = aboutDoc.match( /revision\/([0-9]*)$/ );
		if ( docRevIdMatches.length >= 2 ) {
			docRevId = parseInt( docRevIdMatches[ 1 ] );
		}
	}
	if ( docRevId && docRevId !== this.revid ) {
		if ( this.retriedRevIdConflict ) {
			// Retried already, just error the second time.
			this.loadFail( 've-api', { errors: [ {
				code: 've-api',
				html: mw.message( 'visualeditor-loaderror-revidconflict',
					String( docRevId ), String( this.revid ) ).parse()
			} ] } );
		} else {
			this.retriedRevIdConflict = true;
			// TODO this retries both requests, in RESTbase mode we should only retry
			// the request that gave us the lower revid
			this.loading = null;
			// HACK: Load with explicit revid to hopefully prevent this from happening again
			this.requestedRevId = Math.max( docRevId, this.revid );
			this.load();
		}
		return false;
	} else {
		// Set this to false after a successful load, so we don't immediately give up
		// if a subsequent load mismatches again
		this.retriedRevIdConflict = false;
	}

	checkboxes = mw.libs.ve.targetLoader.createCheckboxFields( this.checkboxesDef );
	this.checkboxFields = checkboxes.checkboxFields;
	this.checkboxesByName = checkboxes.checkboxesByName;

	this.checkboxFields.forEach( function ( field ) {
		// TODO: This method should be upstreamed or moved so that targetLoader
		// can use it safely.
		ve.targetLinksToNewWindow( field.$label[ 0 ] );
	} );

	return true;
};

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.prototype.documentReady = function () {
	// We need to wait until documentReady as local notices may require special messages
	this.editNotices = this.remoteNotices.concat(
		this.localNoticeMessages.map( function ( msgKey ) {
			return '<p>' + ve.init.platform.getParsedMessage( msgKey ) + '</p>';
		} )
	);

	this.loading = null;
	this.edited = this.fromEditedState;

	// Parent method
	ve.init.mw.ArticleTarget.super.prototype.documentReady.apply( this, arguments );
};

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.prototype.surfaceReady = function () {
	var name, i, triggers,
		accessKeyPrefix = $.fn.updateTooltipAccessKeys.getAccessKeyPrefix().replace( /-/g, '+' ),
		accessKeyModifiers = new ve.ui.Trigger( accessKeyPrefix + '-' ).modifiers,
		surfaceModel = this.getSurface().getModel();

	// loadSuccess() may have called setAssumeExistence( true );
	ve.init.platform.linkCache.setAssumeExistence( false );
	surfaceModel.connect( this, {
		history: 'updateToolbarSaveButtonState'
	} );

	// Iterate over the trigger registry and resolve any access key conflicts
	for ( name in ve.ui.triggerRegistry.registry ) {
		triggers = ve.ui.triggerRegistry.registry[ name ];
		for ( i = 0; i < triggers.length; i++ ) {
			if ( ve.compare( triggers[ i ].modifiers, accessKeyModifiers ) ) {
				this.disableAccessKey( triggers[ i ].primary );
			}
		}
	}

	if ( !this.canEdit ) {
		this.getSurface().setReadOnly( true );
	} else {
		// Auto-save
		this.initAutosave();

		setTimeout( function () {
			mw.libs.ve.targetSaver.preloadDeflate();
		}, 500 );
	}

	// Parent method
	ve.init.mw.ArticleTarget.super.prototype.surfaceReady.apply( this, arguments );

	// Do this after window is made scrollable on mobile
	// ('surfaceReady' handler in VisualEditorOverlay in MobileFrontend)
	this.restoreEditSection();

	mw.hook( 've.activationComplete' ).fire();
};

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.prototype.storeDocState = function ( html ) {
	var mode = this.getSurface().getMode();
	this.getSurface().getModel().storeDocState( {
		request: {
			pageName: this.getPageName(),
			mode: mode,
			// Check true section editing is in use
			section: ( mode === 'source' || this.enableVisualSectionEditing ) ? this.section : null
		},
		response: {
			etag: this.etag,
			fromEditedState: this.fromEditedState,
			switched: this.switched,
			preloaded: this.preloaded,
			notices: this.remoteNotices,
			protectedClasses: this.protectedClasses,
			basetimestamp: this.baseTimeStamp,
			starttimestamp: this.startTimeStamp,
			oldid: this.revid,
			canEdit: this.canEdit,
			checkboxesDef: this.checkboxesDef,
			checkboxesMessages: this.checkboxesMessages
		}
	}, html );
};

/**
 * Disable an access key by removing the attribute from any element containing it
 *
 * @param {string} key Access key
 */
ve.init.mw.ArticleTarget.prototype.disableAccessKey = function ( key ) {
	$( '[accesskey=' + key + ']' ).each( function () {
		var $this = $( this );

		$this
			.attr( 'data-old-accesskey', $this.attr( 'accesskey' ) )
			.removeAttr( 'accesskey' );
	} );
};

/**
 * Re-enable all access keys
 */
ve.init.mw.ArticleTarget.prototype.restoreAccessKeys = function () {
	$( '[data-old-accesskey]' ).each( function () {
		var $this = $( this );

		$this
			.attr( 'accesskey', $this.attr( 'data-old-accesskey' ) )
			.removeAttr( 'data-old-accesskey' );
	} );
};

/**
 * Handle an unsuccessful load request.
 *
 * This method is called within the context of a target instance.
 *
 * @param {string} code Error code from mw.Api
 * @param {Object} errorDetails API response
 * @fires loadError
 */
ve.init.mw.ArticleTarget.prototype.loadFail = function () {
	this.loading = null;
	this.emit( 'loadError' );
};

/**
 * Handle successful DOM save event.
 *
 * @param {Object} data Save data from the API
 * @param {string} data.content Rendered page HTML from server
 * @param {string} data.categorieshtml Rendered categories HTML from server
 * @param {number} data.newrevid New revision id, undefined if unchanged
 * @param {boolean} data.isRedirect Whether this page is a redirect or not
 * @param {string} data.displayTitleHtml What HTML to show as the page title
 * @param {Object} data.lastModified Object containing user-formatted date
 *  and time strings, or undefined if we made no change.
 * @param {string} data.contentSub HTML to show as the content subtitle
 * @param {Array} data.modules The modules to be loaded on the page
 * @param {Object} data.jsconfigvars The mw.config values needed on the page
 * @fires save
 */
ve.init.mw.ArticleTarget.prototype.saveComplete = function ( data ) {
	this.editSummaryValue = null;
	this.initialEditSummary = null;

	this.saveDeferred.resolve();
	this.emit( 'save', data );
};

/**
 * Handle an unsuccessful save request.
 *
 * TODO: This code should be mostly moved to ArticleTargetSaver,
 * in particular the badtoken error handling.
 *
 * @param {HTMLDocument} doc HTML document we tried to save
 * @param {Object} saveData Options that were used
 * @param {boolean} wasRetry Whether this was a retry after a 'badtoken' error
 * @param {string} code Error code
 * @param {Object|null} data Full API response data, or XHR error details
 */
ve.init.mw.ArticleTarget.prototype.saveFail = function ( doc, saveData, wasRetry, code, data ) {
	var name, handler, i, error,
		saveErrorHandlerFactory = ve.init.mw.saveErrorHandlerFactory,
		target = this;

	this.pageDeletedWarning = false;

	// Handle empty response
	if ( !data ) {
		this.saveErrorEmpty();
		return;
	}

	if ( data.errors ) {
		for ( i = 0; i < data.errors.length; i++ ) {
			error = data.errors[ i ];

			if ( error.code === 'badtoken' ) {
				this.saveErrorBadToken();
			} else if ( error.code === 'assertanonfailed' || error.code === 'assertuserfailed' || error.code === 'assertnameduserfailed' ) {
				this.refreshUser().then( function ( username ) {
					target.saveErrorNewUser( username );
				}, function () {
					target.saveErrorUnknown( data );
				} );
				return;
			} else if ( error.code === 'editconflict' ) {
				this.editConflict();
				return;
			} else if ( error.code === 'pagedeleted' ) {
				this.saveErrorPageDeleted();
				return;
			} else if ( error.code === 'hookaborted' ) {
				this.saveErrorHookAborted( data );
				return;
			} else if ( error.code === 'readonly' ) {
				this.saveErrorReadOnly( data );
				return;
			}
		}
	}

	for ( name in saveErrorHandlerFactory.registry ) {
		handler = saveErrorHandlerFactory.lookup( name );
		if ( handler.static.matchFunction( data ) ) {
			handler.static.process( data, this );
			// Error was handled
			return;
		}
	}

	// Handle (other) unknown and/or unrecoverable errors
	this.saveErrorUnknown( data );
};

/**
 * Show an save process error message
 *
 * @param {string|jQuery|Node[]} msg Message content (string of HTML, jQuery object or array of
 *  Node objects)
 * @param {boolean} [allowReapply=true] Whether or not to allow the user to reapply.
 *  Reset when swapping panels. Assumed to be true unless explicitly set to false.
 * @param {boolean} [warning=false] Whether or not this is a warning.
 */
ve.init.mw.ArticleTarget.prototype.showSaveError = function ( msg, allowReapply, warning ) {
	this.saveDeferred.reject( [ new OO.ui.Error( msg, { recoverable: allowReapply, warning: warning } ) ] );
};

/**
 * Extract the error messages from an erroneous API response
 *
 * @param {Object} data API response data
 * @return {jQuery}
 */
ve.init.mw.ArticleTarget.prototype.extractErrorMessages = function ( data ) {
	var $errorMsgs = ( new mw.Api() ).getErrorMessage( data );
	$errorMsgs.each( function () {
		ve.targetLinksToNewWindow( this );
	} );
	return $errorMsgs;
};

/**
 * Handle general save error
 *
 * @fires saveErrorEmpty
 */
ve.init.mw.ArticleTarget.prototype.saveErrorEmpty = function () {
	this.showSaveError(
		ve.msg( 'visualeditor-saveerror', ve.msg( 'visualeditor-error-invalidresponse' ) ),
		false /* prevents reapply */
	);
	this.emit( 'saveErrorEmpty' );
};

/**
 * Handle hook abort save error
 *
 * @param {Object} data API response data
 * @fires saveErrorHookAborted
 */
ve.init.mw.ArticleTarget.prototype.saveErrorHookAborted = function ( data ) {
	this.showSaveError( this.extractErrorMessages( data ) );
	this.emit( 'saveErrorHookAborted' );
};

/**
 * Handle assert error indicating another user is logged in.
 *
 * @param {string|null} username Name of newly logged-in user, or null if anonymous
 * @fires saveErrorNewUser
 */
ve.init.mw.ArticleTarget.prototype.saveErrorNewUser = function ( username ) {
	var $msg;

	this.emit( 'saveErrorNewUser' );

	// TODO: Improve this message, concatenating it this way is a bad practice.
	// This should read more like 'session_fail_preview' in MediaWiki core
	// (but with the caveat that we know already whether you're logged in or not).
	$msg = $( document.createTextNode( mw.msg( 'visualeditor-savedialog-error-badtoken' ) + ' ' ) ).add(
		mw.message(
			username === null ?
				'visualeditor-savedialog-identify-anon' :
				'visualeditor-savedialog-identify-user',
			username
		).parseDom()
	);

	this.showSaveError( $msg );
};

/**
 * Handle token fetch errors.
 *
 * @fires saveErrorBadToken
 */
ve.init.mw.ArticleTarget.prototype.saveErrorBadToken = function () {
	this.emit( 'saveErrorBadToken', false );

	// TODO: Improve this message, concatenating it this way is a bad practice.
	// Also, it's not always true that you're "no longer logged in".
	// This should read more like 'session_fail_preview' in MediaWiki core.
	this.showSaveError(
		mw.msg( 'visualeditor-savedialog-error-badtoken' ) + ' ' +
		mw.msg( 'visualeditor-savedialog-identify-trylogin' )
	);
};

/**
 * Handle unknown save error
 *
 * @param {Object|null} data API response data
 * @fires saveErrorUnknown
 */
ve.init.mw.ArticleTarget.prototype.saveErrorUnknown = function ( data ) {
	var errorCodes;

	this.showSaveError( this.extractErrorMessages( data ), false );

	if ( data.errors ) {
		errorCodes = data.errors.map( function ( err ) {
			return err.code;
		} ).join( ',' );
	} else {
		errorCodes = 'http-' + ( ( data.xhr && data.xhr.status ) || 0 );
	}
	this.emit( 'saveErrorUnknown', errorCodes );
};

/**
 * Handle page deleted error
 *
 * @fires saveErrorPageDeleted
 */
ve.init.mw.ArticleTarget.prototype.saveErrorPageDeleted = function () {
	this.pageDeletedWarning = true;
	// The API error message 'apierror-pagedeleted' is poor, make our own
	this.showSaveError( mw.msg( 'visualeditor-recreate', mw.msg( 'ooui-dialog-process-continue' ) ), true, true );
	this.emit( 'saveErrorPageDeleted' );
};

/**
 * Handle read only error
 *
 * @param {Object} data API response data
 * @fires saveErrorReadOnly
 */
ve.init.mw.ArticleTarget.prototype.saveErrorReadOnly = function ( data ) {
	this.showSaveError( this.extractErrorMessages( data ), true, true );
	this.emit( 'saveErrorReadOnly' );
};

/**
 * Handle an edit conflict
 *
 * @fires editConflict
 */
ve.init.mw.ArticleTarget.prototype.editConflict = function () {
	this.emit( 'editConflict' );
	this.saveDialog.popPending();
	this.saveDialog.swapPanel( 'conflict' );
};

/**
 * Handle clicks on the review button in the save dialog.
 *
 * @fires saveReview
 */
ve.init.mw.ArticleTarget.prototype.onSaveDialogReview = function () {
	var target = this;
	if ( !this.saveDialog.hasDiff ) {
		this.emit( 'saveReview' );
		this.saveDialog.pushPending();
		if ( this.pageExists ) {
			// Has no callback, handled via target.showChangesDiff
			this.showChanges( this.getDocToSave() );
		} else {
			this.serialize( this.getDocToSave() ).then( function ( data ) {
				target.onSaveDialogReviewComplete( data.content );
			} );
		}
	} else {
		this.saveDialog.swapPanel( 'review' );
	}
};

/**
 * Handle clicks on the show preview button in the save dialog.
 *
 * @fires savePreview
 */
ve.init.mw.ArticleTarget.prototype.onSaveDialogPreview = function () {
	var wikitext,
		api = this.getContentApi(),
		target = this;

	if ( !this.saveDialog.$previewViewer.children().length ) {
		this.emit( 'savePreview' );
		this.saveDialog.pushPending();

		wikitext = this.getDocToSave();
		if ( this.sectionTitle && this.sectionTitle.getValue() ) {
			wikitext = '== ' + this.sectionTitle.getValue() + ' ==\n\n' + wikitext;
		}

		api.post( {
			action: 'visualeditor',
			paction: 'parsedoc',
			page: this.getPageName(),
			wikitext: wikitext,
			pst: true
		} ).then( function ( response ) {
			var doc, baseDoc;
			baseDoc = target.getSurface().getModel().getDocument().getHtmlDocument();
			doc = target.constructor.static.parseDocument( response.visualeditor.content, 'visual' );
			target.saveDialog.showPreview( doc, baseDoc );
		}, function ( errorCode, details ) {
			target.saveDialog.showPreview( target.extractErrorMessages( details ) );
		} ).always( function () {
			target.bindSaveDialogClearDiff();
		} );
	} else {
		this.saveDialog.swapPanel( 'preview' );
	}
};

/**
 * Clear the save dialog's diff cache when the document changes
 */
ve.init.mw.ArticleTarget.prototype.bindSaveDialogClearDiff = function () {
	// Invalidate the viewer wikitext on next change
	this.getSurface().getModel().getDocument().once( 'transact',
		this.saveDialog.clearDiff.bind( this.saveDialog )
	);
	if ( this.sectionTitle ) {
		this.sectionTitle.once( 'change', this.saveDialog.clearDiff.bind( this.saveDialog ) );
	}
};

/**
 * Handle completed serialize request for diff views for new page creations.
 *
 * @param {string} wikitext Wikitext
 */
ve.init.mw.ArticleTarget.prototype.onSaveDialogReviewComplete = function ( wikitext ) {
	this.bindSaveDialogClearDiff();
	this.saveDialog.setDiffAndReview(
		ve.createDeferred().resolve( $( '<pre>' ).text( wikitext ) ).promise(),
		this.getVisualDiffGeneratorPromise(),
		this.getSurface().getModel().getDocument().getHtmlDocument()
	);
};

/**
 * Get a visual diff object for the current document state
 *
 * @return {jQuery.Promise} Promise resolving with a generator for a ve.dm.VisualDiff visual diff
 */
ve.init.mw.ArticleTarget.prototype.getVisualDiffGeneratorPromise = function () {
	var target = this;

	return mw.loader.using( 'ext.visualEditor.diffLoader' ).then( function () {
		var newRevPromise, doc;

		if ( !target.originalDmDocPromise ) {
			if ( !target.fromEditedState && target.getSurface().getMode() === 'visual' ) {
				// If this.doc was loaded from an un-edited state and in visual mode,
				// then just parse it to get originalDmDoc, otherwise we need to
				// re-fetch the HTML
				if ( target.section !== null && target.enableVisualSectionEditing ) {
					// Create a document with just the section
					doc = target.doc.cloneNode( true );
					// Empty
					while ( doc.body.firstChild ) {
						doc.body.removeChild( doc.body.firstChild );
					}
					// Append section and unwrap
					doc.body.appendChild( target.doc.body.querySelectorAll( 'section[data-mw-section-id]' )[ 0 ] );
					mw.libs.ve.unwrapParsoidSections( doc.body );
				} else {
					doc = target.doc;
				}
				target.originalDmDocPromise = ve.createDeferred().resolve( target.constructor.static.createModelFromDom( doc, 'visual' ) ).promise();
			} else {
				target.originalDmDocPromise = mw.libs.ve.diffLoader.fetchRevision( target.revid, target.getPageName(), target.section );
			}
		}

		if ( target.getSurface().getMode() === 'source' ) {
			newRevPromise = target.getContentApi().post( {
				action: 'visualeditor',
				paction: 'parsedoc',
				page: target.getPageName(),
				wikitext: target.getSurface().getDom(),
				pst: true
			} ).then( function ( response ) {
				// Use anonymous function to avoid passing through API promise argument
				return mw.libs.ve.diffLoader.getModelFromResponse( response, target.section === null ? null : undefined );
			} );

			return mw.libs.ve.diffLoader.getVisualDiffGeneratorPromise( target.originalDmDocPromise, newRevPromise );
		} else {
			return target.originalDmDocPromise.then( function ( originalDmDoc ) {
				return function () {
					return new ve.dm.VisualDiff( originalDmDoc, target.getSurface().getModel().getAttachedRoot() );
				};
			} );
		}
	} );
};

/**
 * Handle clicks on the resolve conflict button in the conflict dialog.
 */
ve.init.mw.ArticleTarget.prototype.onSaveDialogResolveConflict = function () {
	var fields = { wpSave: 1 },
		target = this;

	if ( this.getSurface().getMode() === 'source' && this.section !== null ) {
		// TODO: This should happen in #getSaveFields, check if moving it there breaks anything
		fields.section = this.section;
	}
	// Get Wikitext from the DOM, and set up a submit call when it's done
	this.serialize( this.getDocToSave() ).then( function ( data ) {
		target.submitWithSaveFields( fields, data.content );
	} );
};

/**
 * Handle dialog retry events
 * So we can handle trying to save again after page deletion warnings
 */
ve.init.mw.ArticleTarget.prototype.onSaveDialogRetry = function () {
	if ( this.pageDeletedWarning ) {
		this.recreating = true;
		this.pageExists = false;
	}
};

/**
 * Handle dialog close events.
 *
 * @fires saveWorkflowEnd
 */
ve.init.mw.ArticleTarget.prototype.onSaveDialogClose = function () {
	this.emit( 'saveWorkflowEnd' );
};

/**
 * Load the editor.
 *
 * This method initiates an API request for the page data unless dataPromise is passed in,
 * in which case it waits for that promise instead.
 *
 * @param {jQuery.Promise} [dataPromise] Promise for pending request, if any
 * @return {jQuery.Promise} Data promise
 */
ve.init.mw.ArticleTarget.prototype.load = function ( dataPromise ) {
	// Prevent duplicate requests
	if ( this.loading ) {
		return this.loading;
	}
	this.events.trackActivationStart( mw.libs.ve.activationStart );
	mw.libs.ve.activationStart = null;

	dataPromise = dataPromise || mw.libs.ve.targetLoader.requestPageData( this.getDefaultMode(), this.getPageName(), {
		sessionStore: true,
		section: this.section,
		oldId: this.requestedRevId,
		targetName: this.constructor.static.trackingName
	} );

	this.loading = dataPromise;
	dataPromise
		.done( this.loadSuccess.bind( this ) )
		.fail( this.loadFail.bind( this ) );

	return dataPromise;
};

/**
 * Clear the state of this target, preparing it to be reactivated later.
 */
ve.init.mw.ArticleTarget.prototype.clearState = function () {
	this.restoreAccessKeys();
	this.clearPreparedCacheKey();
	this.loading = null;
	this.saving = null;
	this.clearDiff();
	this.serializing = false;
	this.submitting = false;
	this.baseTimeStamp = null;
	this.startTimeStamp = null;
	this.checkboxes = null;
	this.initialSourceRange = null;
	this.doc = null;
	this.originalDmDocPromise = null;
	this.originalHtml = null;
	this.toolbarSaveButton = null;
	this.section = null;
	this.editNotices = [];
	this.remoteNotices = [];
	this.localNoticeMessages = [];
	this.recovered = false;
	this.teardownPromise = null;
};

/**
 * Switch to edit source mode
 *
 * Opens a confirmation dialog if the document is modified or VE wikitext mode
 * is not available.
 */
ve.init.mw.ArticleTarget.prototype.editSource = function () {
	var modified = this.fromEditedState || this.getSurface().getModel().hasBeenModified();

	this.switchToWikitextEditor( modified );
};

/**
 * Get a document to save, cached until the surface is modified
 *
 * The default implementation returns an HTMLDocument, but other targets
 * may use a different document model (e.g. plain text for source mode).
 *
 * @return {Object} Document to save
 */
ve.init.mw.ArticleTarget.prototype.getDocToSave = function () {
	var surface;
	if ( !this.docToSave ) {
		this.docToSave = this.createDocToSave();
		// Cache clearing events
		surface = this.getSurface();
		surface.getModel().getDocument().once( 'transact', this.clearDocToSave.bind( this ) );
		surface.once( 'destroy', this.clearDocToSave.bind( this ) );
	}
	return this.docToSave;
};

/**
 * Create a document to save
 *
 * @return {Object} Document to save
 */
ve.init.mw.ArticleTarget.prototype.createDocToSave = function () {
	return this.getSurface().getDom();
};

/**
 * Clear the document to save from the cache
 */
ve.init.mw.ArticleTarget.prototype.clearDocToSave = function () {
	this.docToSave = null;
	this.clearPreparedCacheKey();
};

/**
 * Serialize the current document and store the result in the serialization cache on the server.
 *
 * This function returns a promise that is resolved once serialization is complete, with the
 * cache key passed as the first parameter.
 *
 * If there's already a request pending for the same (reference-identical) HTMLDocument, this
 * function will not initiate a new request but will return the promise for the pending request.
 * If a request for the same document has already been completed, this function will keep returning
 * the same promise (which will already have been resolved) until clearPreparedCacheKey() is called.
 *
 * @param {HTMLDocument} doc Document to serialize
 */
ve.init.mw.ArticleTarget.prototype.prepareCacheKey = function ( doc ) {
	var xhr,
		aborted = false,
		start = ve.now(),
		target = this;

	if ( this.getSurface().getMode() === 'source' ) {
		return;
	}

	if ( this.preparedCacheKeyPromise && this.preparedCacheKeyPromise.doc === doc ) {
		return;
	}
	this.clearPreparedCacheKey();

	this.preparedCacheKeyPromise = mw.libs.ve.targetSaver.deflateDoc( doc, this.doc )
		.then( function ( deflatedHtml ) {
			if ( aborted ) {
				return ve.createDeferred().reject();
			}
			xhr = target.getContentApi().postWithToken( 'csrf',
				{
					action: 'visualeditoredit',
					paction: 'serializeforcache',
					html: deflatedHtml,
					page: target.getPageName(),
					oldid: target.revid,
					etag: target.etag
				},
				{ contentType: 'multipart/form-data' }
			);
			return xhr.then(
				function ( response ) {
					var trackData = { duration: ve.now() - start };
					if ( response.visualeditoredit && typeof response.visualeditoredit.cachekey === 'string' ) {
						target.events.track( 'performance.system.serializeforcache', trackData );
						return {
							cacheKey: response.visualeditoredit.cachekey,
							// Pass the HTML for retries.
							html: deflatedHtml
						};
					} else {
						target.events.track( 'performance.system.serializeforcache.nocachekey', trackData );
						return ve.createDeferred().reject();
					}
				},
				function () {
					target.events.track( 'performance.system.serializeforcache.fail', { duration: ve.now() - start } );
					return ve.createDeferred().reject();
				}
			);
		} )
		.promise( {
			abort: function () {
				if ( xhr ) {
					xhr.abort();
				}
				aborted = true;
			},
			doc: doc
		} );
};

/**
 * Get the prepared wikitext, if any. Same as prepareWikitext() but does not initiate a request
 * if one isn't already pending or finished. Instead, it returns a rejected promise in that case.
 *
 * @param {HTMLDocument} doc Document to serialize
 * @return {jQuery.Promise} Abortable promise, resolved with a plain object containing `cacheKey`,
 * and `html` for retries.
 */
ve.init.mw.ArticleTarget.prototype.getPreparedCacheKey = function ( doc ) {
	if ( this.preparedCacheKeyPromise && this.preparedCacheKeyPromise.doc === doc ) {
		return this.preparedCacheKeyPromise;
	}
	return ve.createDeferred().reject().promise();
};

/**
 * Clear the promise for the prepared wikitext cache key, and abort it if it's still in progress.
 */
ve.init.mw.ArticleTarget.prototype.clearPreparedCacheKey = function () {
	if ( this.preparedCacheKeyPromise ) {
		this.preparedCacheKeyPromise.abort();
		this.preparedCacheKeyPromise = null;
	}
};

/**
 * Try submitting an API request with a cache key for prepared wikitext, falling back to submitting
 * HTML directly if there is no cache key present or pending, or if the request for the cache key
 * fails, or if using the cache key fails with a badcachekey error.
 *
 * This function will use mw.Api#postWithToken to retry automatically when encountering a 'badtoken'
 * error.
 *
 * @param {HTMLDocument|string} doc Document to submit or string in source mode
 * @param {Object} extraData POST parameters to send. Do not include 'html', 'cachekey' or 'format'.
 * @param {string} [eventName] If set, log an event when the request completes successfully. The
 *  full event name used will be 'performance.system.{eventName}.withCacheKey' or .withoutCacheKey
 *  depending on whether or not a cache key was used.
 * @return {jQuery.Promise} Promise which resolves/rejects when saving is complete/fails
 */
ve.init.mw.ArticleTarget.prototype.tryWithPreparedCacheKey = function ( doc, extraData, eventName ) {
	var data, htmlOrCacheKeyPromise,
		target = this;

	if ( this.getSurface().getMode() === 'source' ) {
		data = ve.copy( extraData );

		// TODO: This should happen in #getSaveOptions, check if moving it there breaks anything
		if ( this.section !== null ) {
			data.section = this.section;
		}
		if ( this.sectionTitle ) {
			data.sectiontitle = this.sectionTitle.getValue();
			data.summary = undefined;
		}

		return mw.libs.ve.targetSaver.postWikitext(
			doc,
			data,
			{ api: target.getContentApi() }
		);
	}

	// getPreparedCacheKey resolves with { cacheKey: ..., html: ... } or rejects.
	// After modification it never rejects, just resolves with { html: ... } instead
	htmlOrCacheKeyPromise = this.getPreparedCacheKey( doc ).then(
		// Success, use promise as-is.
		null,
		// Fail, get deflatedHtml promise
		function () {
			return mw.libs.ve.targetSaver.deflateDoc( doc, target.doc ).then( function ( html ) {
				return { html: html };
			} );
		} );

	return htmlOrCacheKeyPromise.then( function ( htmlOrCacheKey ) {
		return mw.libs.ve.targetSaver.postHtml(
			htmlOrCacheKey.html,
			htmlOrCacheKey.cacheKey,
			extraData,
			{
				onCacheKeyFail: target.clearPreparedCacheKey.bind( target ),
				api: target.getContentApi(),
				track: target.events.track.bind( target.events ),
				eventName: eventName,
				now: ve.now
			}
		);
	} );
};

/**
 * Handle the save dialog's save event
 *
 * Validates the inputs then starts the save process
 *
 * @param {jQuery.Deferred} saveDeferred Deferred object to resolve/reject when the save
 *  succeeds/fails.
 * @fires saveInitiated
 */
ve.init.mw.ArticleTarget.prototype.onSaveDialogSave = function ( saveDeferred ) {
	var saveOptions;

	if ( this.deactivating ) {
		return;
	}

	saveOptions = this.getSaveOptions();

	if (
		+mw.user.options.get( 'forceeditsummary' ) &&
		( saveOptions.summary === '' || saveOptions.summary === this.initialEditSummary ) &&
		!this.saveDialog.messages.missingsummary
	) {
		this.saveDialog.showMessage(
			'missingsummary',
			// Wrap manually since this core message already includes a bold "Warning:" label
			$( '<p>' ).append( ve.init.platform.getParsedMessage( 'missingsummary' ) ),
			{ wrap: false }
		);
		this.saveDialog.popPending();
	} else {
		this.emit( 'saveInitiated' );
		this.startSave( saveOptions );
		this.saveDeferred = saveDeferred;
	}
};

/**
 * Start the save process
 *
 * @param {Object} saveOptions Save options
 */
ve.init.mw.ArticleTarget.prototype.startSave = function ( saveOptions ) {
	this.save( this.getDocToSave(), saveOptions );
};

/**
 * Get save form fields from the save dialog form.
 *
 * @return {Object} Form data for submission to the MediaWiki action=edit UI
 */
ve.init.mw.ArticleTarget.prototype.getSaveFields = function () {
	var name,
		fields = {};

	if ( this.section === 'new' ) {
		// MediaWiki action=edit UI doesn't have separate parameters for edit summary and new section
		// title. The edit summary parameter is supposed to contain the section title, and the real
		// summary is autogenerated.
		fields.wpSummary = this.sectionTitle ? this.sectionTitle.getValue() : '';
	} else {
		fields.wpSummary = this.saveDialog ?
			this.saveDialog.editSummaryInput.getValue() :
			( this.editSummaryValue || this.initialEditSummary );
	}

	// Extra save fields added by extensions
	for ( name in this.saveFields ) {
		fields[ name ] = this.saveFields[ name ]();
	}

	if ( this.recreating ) {
		fields.wpRecreate = true;
	}

	for ( name in this.checkboxesByName ) {
		if ( this.checkboxesByName[ name ].isSelected() ) {
			fields[ name ] = this.checkboxesByName[ name ].getValue();
		}
	}

	return fields;
};

/**
 * Invoke #submit with the data from #getSaveFields
 *
 * @param {Object} fields Fields to add in addition to those from #getSaveFields
 * @param {string} wikitext Wikitext to submit
 * @return {boolean} Whether submission was started
 */
ve.init.mw.ArticleTarget.prototype.submitWithSaveFields = function ( fields, wikitext ) {
	return this.submit( wikitext, $.extend( this.getSaveFields(), fields ) );
};

/**
 * Get edit API options from the save dialog form.
 *
 * @return {Object} Save options for submission to the MediaWiki API
 */
ve.init.mw.ArticleTarget.prototype.getSaveOptions = function () {
	var key,
		options = this.getSaveFields(),
		fieldMap = {
			wpSummary: 'summary',
			wpMinoredit: 'minor',
			wpWatchthis: 'watchlist',
			wpCaptchaId: 'captchaid',
			wpCaptchaWord: 'captchaword'
		};

	for ( key in fieldMap ) {
		if ( options[ key ] !== undefined ) {
			options[ fieldMap[ key ] ] = options[ key ];
			delete options[ key ];
		}
	}

	options.watchlist = 'watchlist' in options ? 'watch' : 'unwatch';

	return options;
};

/**
 * Post DOM data to the Parsoid API.
 *
 * This method performs an asynchronous action and uses a callback function to handle the result.
 *
 *     target.save( dom, { summary: 'test', minor: true, watch: false } );
 *
 * @param {HTMLDocument} doc Document to save
 * @param {Object} options Saving options. All keys are passed through, including unrecognized ones.
 *  - {string} summary Edit summary
 *  - {boolean} minor Edit is a minor edit
 *  - {boolean} watch Watch the page
 * @param {boolean} [isRetry=false] Whether this is a retry after a 'badtoken' error
 * @return {jQuery.Promise} Save promise, see mw.libs.ve.targetSaver.postHtml
 */
ve.init.mw.ArticleTarget.prototype.save = function ( doc, options, isRetry ) {
	var data, promise,
		target = this;

	// Prevent duplicate requests
	if ( this.saving ) {
		return this.saving;
	}

	data = ve.extendObject( {}, options, {
		page: this.getPageName(),
		oldid: this.revid,
		basetimestamp: this.baseTimeStamp,
		starttimestamp: this.startTimeStamp,
		etag: this.etag,
		assert: mw.user.isAnon() ? 'anon' : 'user',
		assertuser: mw.user.getName() || undefined
	} );

	if ( mw.config.get( 'wgVisualEditorConfig' ).useChangeTagging && !data.vetags ) {
		if ( this.getSurface().getMode() === 'source' ) {
			data.vetags = 'visualeditor-wikitext';
		} else {
			data.vetags = 'visualeditor';
		}
	}

	promise = this.saving = this.tryWithPreparedCacheKey( doc, data, 'save' )
		.done( this.saveComplete.bind( this ) )
		.fail( this.saveFail.bind( this, doc, data, !!isRetry ) )
		.always( function () {
			target.saving = null;
		} );

	return promise;
};

/**
 * Show changes in the save dialog
 *
 * @param {Object} doc Document
 */
ve.init.mw.ArticleTarget.prototype.showChanges = function ( doc ) {
	var target = this;
	// Invalidate the viewer diff on next change
	this.getSurface().getModel().getDocument().once( 'transact', function () {
		target.clearDiff();
	} );
	this.saveDialog.setDiffAndReview(
		this.getWikitextDiffPromise( doc ),
		this.getVisualDiffGeneratorPromise(),
		this.getSurface().getModel().getDocument().getHtmlDocument()
	);
};

/**
 * Clear all state associated with the diff
 */
ve.init.mw.ArticleTarget.prototype.clearDiff = function () {
	if ( this.saveDialog ) {
		this.saveDialog.clearDiff();
	}
	this.wikitextDiffPromise = null;
};

/**
 * Post DOM data to the Parsoid API to retrieve wikitext diff.
 *
 * @param {HTMLDocument} doc Document to compare against (via wikitext)
 * @return {jQuery.Promise} Promise which resolves with the wikitext diff, or rejects with an error
 * @fires showChanges
 * @fires showChangesError
 */
ve.init.mw.ArticleTarget.prototype.getWikitextDiffPromise = function ( doc ) {
	var target = this;
	if ( !this.wikitextDiffPromise ) {
		this.wikitextDiffPromise = this.tryWithPreparedCacheKey( doc, {
			paction: 'diff',
			page: this.getPageName(),
			oldid: this.revid,
			etag: this.etag
		}, 'diff' ).then( function ( data ) {
			if ( !data.diff ) {
				target.emit( 'noChanges' );
			}
			return data.diff;
		} );
		this.wikitextDiffPromise
			.done( this.emit.bind( this, 'showChanges' ) )
			.fail( this.emit.bind( this, 'showChangesError' ) );
	}
	return this.wikitextDiffPromise;
};

/**
 * Post wikitext to MediaWiki.
 *
 * This method performs a synchronous action and will take the user to a new page when complete.
 *
 *     target.submit( wikitext, { wpSummary: 'test', wpMinorEdit: 1, wpSave: 1 } );
 *
 * @param {string} wikitext Wikitext to submit
 * @param {Object} fields Other form fields to add (e.g. wpSummary, wpWatchthis, etc.). To actually
 *  save the wikitext, add { wpSave: 1 }. To go to the diff view, add { wpDiff: 1 }.
 * @return {boolean} Submitting has been started
 */
ve.init.mw.ArticleTarget.prototype.submit = function ( wikitext, fields ) {
	var key, $form, params;

	// Prevent duplicate requests
	if ( this.submitting ) {
		return false;
	}
	// Clear autosave now that we don't expect to need it again.
	// FIXME: This isn't transactional, so if the save fails we're left with no recourse.
	this.clearDocState();
	// Save DOM
	this.submitting = true;
	$form = $( '<form>' ).attr( { method: 'post', enctype: 'multipart/form-data' } ).addClass( 'oo-ui-element-hidden' );
	params = ve.extendObject( {
		format: 'text/x-wiki',
		model: 'wikitext',
		oldid: this.requestedRevId,
		wpStarttime: this.startTimeStamp,
		wpEdittime: this.baseTimeStamp,
		wpTextbox1: wikitext,
		wpEditToken: mw.user.tokens.get( 'csrfToken' ),
		// MediaWiki function-verification parameters, mostly relevant to the
		// classic editpage, but still required here:
		wpUnicodeCheck: 'ℳ𝒲♥𝓊𝓃𝒾𝒸ℴ𝒹ℯ',
		wpUltimateParam: true
	}, fields );
	// Add params as hidden fields
	for ( key in params ) {
		$form.append( $( '<input>' ).attr( { type: 'hidden', name: key, value: params[ key ] } ) );
	}
	// Submit the form, mimicking a traditional edit
	// Firefox requires the form to be attached
	$form.attr( 'action', this.submitUrl ).appendTo( 'body' ).trigger( 'submit' );
	return true;
};

/**
 * Get Wikitext data from the Parsoid API.
 *
 * This method performs an asynchronous action and uses a callback function to handle the result.
 *
 *     target.serialize( doc ).then( function ( data ) {
 *         // Do something with data.content (wikitext)
 *     } );
 *
 * @param {HTMLDocument} doc Document to serialize
 * @param {Function} [callback] Optional callback to run after.
 *  Deprecated in favor of using the returned promise.
 * @return {jQuery.Promise} Serialize promise, see mw.libs.ve.targetSaver.postHtml
 */
ve.init.mw.ArticleTarget.prototype.serialize = function ( doc, callback ) {
	var promise,
		target = this;
	// Prevent duplicate requests
	if ( this.serializing ) {
		return this.serializing;
	}
	promise = this.serializing = this.tryWithPreparedCacheKey( doc, {
		paction: 'serialize',
		page: this.getPageName(),
		oldid: this.revid,
		etag: this.etag
	}, 'serialize' )
		.done( this.emit.bind( this, 'serializeComplete' ) )
		.fail( this.emit.bind( this, 'serializeError' ) )
		.always( function () {
			target.serializing = null;
		} );

	if ( callback ) {
		OO.ui.warnDeprecation( 'Passing a callback to ve.init.mw.ArticleTarget#serialize is deprecated. Use the returned promise instead.' );
		promise.then( function ( data ) {
			callback.call( target, data.content );
		} );
	}

	return promise;
};

/**
 * Get list of edit notices.
 *
 * @return {Array} List of edit notices
 */
ve.init.mw.ArticleTarget.prototype.getEditNotices = function () {
	return this.editNotices;
};

// FIXME: split out view specific functionality, emit to subclass

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.prototype.track = function ( name ) {
	var mode = this.surface ? this.surface.getMode() : this.getDefaultMode();
	ve.track( name, { mode: mode } );
};

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.prototype.createSurface = function ( dmDoc, config ) {
	var surface, sections, attachedRoot;

	sections = dmDoc.getNodesByType( 'section' );
	if ( sections.length && sections.length === 1 ) {
		attachedRoot = sections[ 0 ];
		if ( !attachedRoot.isSurfaceable() ) {
			throw new Error( 'Not a surfaceable node' );
		}
	}

	// Parent method
	surface = ve.init.mw.ArticleTarget.super.prototype.createSurface.call(
		this,
		dmDoc,
		ve.extendObject( { attachedRoot: attachedRoot }, config )
	);

	// The following classes are used here:
	// * mw-textarea-proteced
	// * mw-textarea-cproteced
	// * mw-textarea-sproteced
	surface.$element.addClass( this.protectedClasses );

	return surface;
};

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.prototype.getSurfaceConfig = function ( config ) {
	return ve.init.mw.ArticleTarget.super.prototype.getSurfaceConfig.call( this, ve.extendObject( {
		// Don't null selection on blur when editing a document.
		// Do use it in new section mode as there are multiple inputs
		// on the surface (header+content).
		nullSelectionOnBlur: this.section === 'new'
	}, config ) );
};

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.prototype.teardown = function () {
	var surface,
		target = this;
	if ( !this.teardownPromise ) {
		surface = this.getSurface();

		// Restore access keys
		if ( this.$saveAccessKeyElements ) {
			this.$saveAccessKeyElements.attr( 'accesskey', ve.msg( 'accesskey-save' ) );
			this.$saveAccessKeyElements = null;
		}
		if ( surface ) {
			// Disconnect history listener
			surface.getModel().disconnect( this );
		}
		// Parent method
		this.teardownPromise = ve.init.mw.ArticleTarget.super.prototype.teardown.call( this ).then( function () {
			mw.hook( 've.deactivationComplete' ).fire( target.edited );
		} );
	}
	return this.teardownPromise;
};

/**
 * Try to tear down the target, but leave ready for re-activation later
 *
 * Will first prompt the user if required, then call #teardown.
 *
 * @param {boolean} [noPrompt] Do not display a prompt to the user
 * @param {string} [trackMechanism] Abort mechanism; used for event tracking if present
 * @return {jQuery.Promise} Promise which resolves when the target has been torn down
 */
ve.init.mw.ArticleTarget.prototype.tryTeardown = function ( noPrompt, trackMechanism ) {
	var target = this;

	if ( noPrompt || !this.edited ) {
		return this.teardown( trackMechanism );
	} else {
		return this.getSurface().dialogs.openWindow( 'abandonedit' )
			.closed.then( function ( data ) {
				if ( data && data.action === 'discard' ) {
					return target.teardown( trackMechanism );
				}
				return ve.createDeferred().reject().promise();
			} );
	}
};

/**
 * @inheritdoc
 */
ve.init.mw.ArticleTarget.prototype.setupToolbar = function () {
	// Parent method
	ve.init.mw.ArticleTarget.super.prototype.setupToolbar.apply( this, arguments );

	this.setupToolbarSaveButton();
	this.updateToolbarSaveButtonState();

	if ( this.saveDialog ) {
		this.editSummaryValue = this.saveDialog.editSummaryInput.getValue();
		this.saveDialog.disconnect( this );
		this.saveDialog = null;
	}
};

/**
 * Getting the message for the toolbar / save dialog save / publish button
 *
 * @param {boolean} [startProcess] Use version of the label for starting that process, i.e. with an ellipsis after it
 * @return {Function|string} An i18n message or resolveable function
 */
ve.init.mw.ArticleTarget.prototype.getSaveButtonLabel = function ( startProcess ) {
	var suffix = startProcess ? '-start' : '';
	// The following messages can be used here
	// * publishpage
	// * publishpage-start
	// * publishchanges
	// * publishchanges-start
	// * savearticle
	// * savearticle-start
	// * savechanges
	// * savechanges-start
	if ( mw.config.get( 'wgEditSubmitButtonLabelPublish' ) ) {
		return OO.ui.deferMsg( ( !this.pageExists ? 'publishpage' : 'publishchanges' ) + suffix );
	}

	return OO.ui.deferMsg( ( !this.pageExists ? 'savearticle' : 'savechanges' ) + suffix );
};

/**
 * Setup the toolbarSaveButton property to point to the save tool
 *
 * @method
 * @abstract
 */
ve.init.mw.ArticleTarget.prototype.setupToolbarSaveButton = null;

/**
 * Re-evaluate whether the article can be saved
 *
 * @return {boolean} The article can be saved
 */
ve.init.mw.ArticleTarget.prototype.isSaveable = function () {
	var surface = this.getSurface();
	if ( !surface ) {
		// Called before we're attached, so meaningless; abandon for now
		return false;
	}

	this.edited =
		// Document was edited before loading
		this.fromEditedState || this.preloaded ||
		// Document was edited
		surface.getModel().hasBeenModified() ||
		// Section title (if it exists) was edited
		( !!this.sectionTitle && this.sectionTitle.getValue() !== '' );

	return this.edited || this.restoring;
};

/**
 * Update the toolbar save button to reflect if the article can be saved
 */
ve.init.mw.ArticleTarget.prototype.updateToolbarSaveButtonState = function () {
	// This should really be an emit('updateState') but that would cause
	// every tool to be updated on every transaction.
	this.toolbarSaveButton.onUpdateState();
};

/**
 * Show a save dialog
 *
 * @param {string} [action] Window action to trigger after opening
 * @param {string} [checkboxName] Checkbox to toggle after opening
 *
 * @fires saveWorkflowBegin
 */
ve.init.mw.ArticleTarget.prototype.showSaveDialog = function ( action, checkboxName ) {
	var checkbox, name, currentWindow, openPromise,
		firstLoad = false,
		target = this;

	if ( !this.isSaveable() || this.saveDialogIsOpening ) {
		return;
	}

	currentWindow = this.getSurface().getDialogs().getCurrentWindow();
	if ( currentWindow && currentWindow.constructor.static.name === 'mwSave' && ( action === 'save' || action === null ) ) {
		// The current window is the save dialog, and we've gotten here via
		// the save action. Trigger a save. We're doing this here instead of
		// relying on an accesskey on the save button, because that has some
		// cross-browser issues that makes it not work in Firefox.
		currentWindow.executeAction( 'save' );
		return;
	}

	this.saveDialogIsOpening = true;

	this.emit( 'saveWorkflowBegin' );

	// Preload the serialization
	this.prepareCacheKey( this.getDocToSave() );

	// Get the save dialog
	this.getSurface().getDialogs().getWindow( 'mwSave' ).done( function ( win ) {
		var data, checked,
			windowAction = ve.ui.actionFactory.create( 'window', target.getSurface() );

		if ( !target.saveDialog ) {
			target.saveDialog = win;
			firstLoad = true;

			// Connect to save dialog
			target.saveDialog.connect( target, {
				save: 'onSaveDialogSave',
				review: 'onSaveDialogReview',
				preview: 'onSaveDialogPreview',
				resolve: 'onSaveDialogResolveConflict',
				retry: 'onSaveDialogRetry',
				close: 'onSaveDialogClose'
			} );
		}

		data = target.getSaveDialogOpeningData();

		if (
			( action === 'review' && !data.canReview ) ||
			( action === 'preview' && !data.canPreview )
		) {
			target.saveDialogIsOpening = false;
			return;
		}

		if ( firstLoad ) {
			for ( name in target.checkboxesByName ) {
				if ( target.initialCheckboxes[ name ] !== undefined ) {
					target.checkboxesByName[ name ].setSelected( target.initialCheckboxes[ name ] );
				}
			}
		}

		if ( checkboxName && ( checkbox = target.checkboxesByName[ checkboxName ] ) ) {
			checked = !checkbox.isSelected();
			// Wait for native access key change to happen
			setTimeout( function () {
				checkbox.setSelected( checked );
			} );
		}

		// When calling review/preview action, switch to those panels immediately
		if ( action === 'review' || action === 'preview' ) {
			data.initialPanel = action;
		}

		// Open the dialog
		openPromise = windowAction.open( 'mwSave', data, action );
		if ( openPromise ) {
			openPromise.always( function () {
				target.saveDialogIsOpening = false;
			} );
		}
	} );
};

/**
 * Get opening data to pass to the save dialog
 *
 * @return {Object} Opening data
 */
ve.init.mw.ArticleTarget.prototype.getSaveDialogOpeningData = function () {
	var mode = this.getSurface().getMode();
	return {
		canPreview: mode === 'source',
		canReview: !( mode === 'source' && this.section === 'new' ),
		sectionTitle: this.sectionTitle && this.sectionTitle.getValue(),
		saveButtonLabel: this.getSaveButtonLabel(),
		checkboxFields: this.checkboxFields,
		checkboxesByName: this.checkboxesByName
	};
};

/**
 * Move the cursor in the editor to section specified by this.section.
 * Do nothing if this.section is undefined.
 */
ve.init.mw.ArticleTarget.prototype.restoreEditSection = function () {
	var dmDoc, headingModel, headingView, headingText,
		section = this.section,
		surface = this.getSurface(),
		mode = surface.getMode();

	if ( section !== null && section !== 'new' && section !== '0' && section !== 'T-0' ) {
		if ( mode === 'visual' ) {
			dmDoc = surface.getModel().getDocument();
			// In mw.libs.ve.unwrapParsoidSections we copy the data-mw-section-id from the section element
			// to the heading. Iterate over headings to find the one with the correct attribute
			// in originalDomElements.
			dmDoc.getNodesByType( 'mwHeading' ).some( function ( heading ) {
				var domElements = heading.getOriginalDomElements( dmDoc.getStore() );
				if (
					domElements && domElements[ 0 ].nodeType === Node.ELEMENT_NODE &&
					domElements[ 0 ].getAttribute( 'data-mw-section-id' ) === section
				) {
					headingModel = heading;
					return true;
				}
				return false;
			} );
			if ( headingModel ) {
				headingView = surface.getView().getDocument().getDocumentNode().getNodeFromOffset( headingModel.getRange().start );
				if ( new mw.Uri().query.summary === undefined ) {
					headingText = headingView.$element.text();
				}
				if ( !this.enableVisualSectionEditing ) {
					this.goToHeading( headingView );
				}
			}
		} else if ( mode === 'source' ) {
			// With elements of extractSectionTitle + stripSectionName TODO:
			// Arguably, we should just throw this through the API and then do
			// the same extract-text pass we do in visual mode. Would save us
			// having to think about wikitext here.
			headingText = surface.getModel().getDocument().data.getText(
				false,
				surface.getModel().getDocument().getDocumentNode().children[ 0 ].getRange()
			)
				// Extract the title
				.replace( /^\s*=+\s*(.*?)\s*=+\s*$/, '$1' )
				// Remove links
				.replace( /\[\[:?([^[|]+)\|([^[]+)\]\]/, '$2' )
				.replace( /\[\[:?([^[]+)\|?\]\]/, '$1' )
				.replace( new RegExp( '\\[(?:' + ve.init.platform.getUnanchoredExternalLinkUrlProtocolsRegExp().source + ')([^ ]+?) ([^\\[]+)\\]', 'i' ), '$3' )
				// Cheap HTML removal
				.replace( /<[^>]+?>/g, '' );
		}
		if ( headingText ) {
			this.initialEditSummary =
				'/* ' +
				ve.graphemeSafeSubstring( headingText, 0, 244 ) +
				' */ ';
		}
	}
};

/**
 * Move the cursor to a given heading and scroll to it.
 *
 * @param {ve.ce.HeadingNode} headingNode Heading node to scroll to
 */
ve.init.mw.ArticleTarget.prototype.goToHeading = function ( headingNode ) {
	var nextNode, offset,
		target = this,
		offsetNode = headingNode,
		surface = this.getSurface(),
		surfaceModel = surface.getModel(),
		surfaceView = surface.getView(),
		lastHeadingLevel = -1;

	// Find next sibling which isn't a heading
	while ( offsetNode instanceof ve.ce.HeadingNode && offsetNode.getModel().getAttribute( 'level' ) > lastHeadingLevel ) {
		lastHeadingLevel = offsetNode.getModel().getAttribute( 'level' );
		// Next sibling
		nextNode = offsetNode.parent.children[ offsetNode.parent.children.indexOf( offsetNode ) + 1 ];
		if ( !nextNode ) {
			break;
		}
		offsetNode = nextNode;
	}
	offset = surfaceModel.getDocument().data.getNearestContentOffset(
		offsetNode.getModel().getOffset(), 1
	);

	function scrollAndSetSelection() {
		surfaceModel.setLinearSelection( new ve.Range( offset ) );
		// Focussing the document triggers showSelection which calls scrollIntoView
		// which uses a jQuery animation, so make sure this is aborted.
		$( OO.ui.Element.static.getClosestScrollableContainer( surfaceView.$element[ 0 ] ) ).stop( true );
		target.scrollToHeading( headingNode );
	}

	if ( surfaceView.isFocused() ) {
		scrollAndSetSelection();
	} else {
		// onDocumentFocus is debounced, so wait for that to happen before setting
		// the model selection, otherwise it will get reset
		surfaceView.once( 'focus', scrollAndSetSelection );
	}
};

/**
 * Scroll to a given heading in the document.
 *
 * @param {ve.ce.HeadingNode} headingNode Heading node to scroll to
 */
ve.init.mw.ArticleTarget.prototype.scrollToHeading = function ( headingNode ) {
	var $window = $( OO.ui.Element.static.getWindow( this.$element ) );

	$window.scrollTop( headingNode.$element.offset().top - this.getToolbar().$element.height() );
};

/**
 * Get the hash fragment for the current section's ID using the page's PHP HTML.
 *
 * TODO: Do this in a less skin-dependent way
 *
 * @param {jQuery} [context] Page context to search in, if narrowing down the content is required
 * @return {string} Hash fragment, or empty string if not found
 */
ve.init.mw.ArticleTarget.prototype.getSectionFragmentFromPage = function ( context ) {
	var section, $sections, $section;

	// Assume there are section edit links, as the user just did a section edit. This also means
	// that the section numbers line up correctly, as not every H_ tag is a numbered section.
	$sections = $( '.mw-editsection', context );
	if ( this.section === 'new' ) {
		// A new section is appended to the end, so take the last one.
		section = $sections.length;
	} else {
		section = this.section;
	}
	if ( section > 0 ) {
		$section = $sections.eq( section - 1 ).parent().find( '.mw-headline' );

		if ( $section.length && $section.attr( 'id' ) ) {
			return $section.attr( 'id' ) || '';
		}
	}
	return '';
};

/**
 * Switches to the wikitext editor, either keeping (default) or discarding changes.
 *
 * @param {boolean} [modified] Whether there were any changes at all.
 */
ve.init.mw.ArticleTarget.prototype.switchToWikitextEditor = function ( modified ) {
	var dataPromise,
		target = this;

	// When switching with changes we always pass the full page as changes in visual section mode
	// can still affect the whole document (e.g. removing a reference)
	if ( modified ) {
		this.section = null;
	}

	if ( this.isModeAvailable( 'source' ) ) {
		if ( !modified ) {
			dataPromise = mw.libs.ve.targetLoader.requestPageData( 'source', this.getPageName(), {
				sessionStore: true,
				section: this.section,
				oldId: this.requestedRevId,
				targetName: this.constructor.static.trackingName
			} ).then(
				function ( response ) { return response; },
				function () {
					// TODO: Some sort of progress bar?
					target.switchToFallbackWikitextEditor( modified );
					// Keep everything else waiting so our error handler can do its business
					return ve.createDeferred().promise();
				}
			);
		} else {
			dataPromise = this.getWikitextDataPromiseForDoc( modified );
		}
		this.reloadSurface( 'source', dataPromise );
	} else {
		this.switchToFallbackWikitextEditor( modified );
	}
};

/**
 * Get a data promise for wikitext editing based on the current doc state
 *
 * @param {boolean} modified Whether there were any changes
 * @return {jQuery.Promise} Data promise
 */
ve.init.mw.ArticleTarget.prototype.getWikitextDataPromiseForDoc = function ( modified ) {
	var target = this;
	return this.serialize( this.getDocToSave() ).then( function ( data ) {
		// HACK - add parameters the API doesn't provide for a VE->WT switch
		data.etag = target.etag;
		data.fromEditedState = modified;
		data.notices = target.remoteNotices;
		data.protectedClasses = target.protectedClasses;
		data.basetimestamp = target.baseTimeStamp;
		data.starttimestamp = target.startTimeStamp;
		data.oldid = target.revid;
		data.canEdit = target.canEdit;
		data.checkboxesDef = target.checkboxesDef;
		// Wrap up like a response object as that is what dataPromise is expected to be
		return { visualeditoredit: data };
	} );
};

/**
 * Switches to the fallback wikitext editor, either keeping (default) or discarding changes.
 *
 * @param {boolean} [modified] Whether there were any changes at all.
 */
ve.init.mw.ArticleTarget.prototype.switchToFallbackWikitextEditor = function () {};

/**
 * Switch to the visual editor.
 */
ve.init.mw.ArticleTarget.prototype.switchToVisualEditor = function () {
	var dataPromise, windowManager, switchWindow,
		config = mw.config.get( 'wgVisualEditorConfig' ),
		canSwitch = config.fullRestbaseUrl || config.allowLossySwitching,
		target = this;

	if ( !this.edited ) {
		this.reloadSurface( 'visual' );
		return;
	}

	// Show a discard-only confirm dialog, and then reload the whole page, if
	// the server can't switch for us because that's not supported.
	if ( !canSwitch ) {
		windowManager = new OO.ui.WindowManager();
		switchWindow = new mw.libs.ve.SwitchConfirmDialog();
		$( document.body ).append( windowManager.$element );
		windowManager.addWindows( [ switchWindow ] );
		windowManager.openWindow( switchWindow, { mode: 'simple' } )
			.closed.then( function ( data ) {
				if ( data && data.action === 'discard' ) {
					target.section = null;
					target.reloadSurface( 'visual' );
				}
				windowManager.destroy();
			} );
	} else {
		dataPromise = mw.libs.ve.targetLoader.requestParsoidData( this.getPageName(), {
			oldId: this.revid,
			targetName: this.constructor.static.trackingName,
			modified: this.edited,
			wikitext: this.getDocToSave(),
			section: this.section
		} );

		this.reloadSurface( 'visual', dataPromise );
	}
};

/**
 * Switch to a different wikitext section
 *
 * @param {string|null} section Section to switch to: a number, 'T-'-prefixed number, 'new'
 *   or null (whole document)
 * @param {boolean} noConfirm Switch without prompting (changes will be lost either way)
 */
ve.init.mw.ArticleTarget.prototype.switchToWikitextSection = function ( section, noConfirm ) {
	var promise,
		target = this;
	if ( section === this.section ) {
		return;
	}
	if ( !noConfirm && this.edited && mw.user.options.get( 'useeditwarning' ) ) {
		promise = this.getSurface().dialogs.openWindow( 'abandonedit' )
			.closed.then( function ( data ) {
				return data && data.action === 'discard';
			} );
	} else {
		promise = ve.createDeferred().resolve( true ).promise();
	}
	promise.then( function ( confirmed ) {
		if ( confirmed ) {
			// Section has changed and edits have been discarded, so edit summary is no longer valid
			// TODO: Preserve summary if document changes can be preserved
			if ( target.saveDialog ) {
				target.saveDialog.reset();
			}
			// TODO: If switching to a non-null section, get the new section title
			target.initialEditSummary = null;
			target.section = section;
			target.reloadSurface( 'source' );
			target.updateTabs( true );
		}
	} );
};

/**
 * Reload the target surface in the new editor mode
 *
 * @param {string} newMode New mode
 * @param {jQuery.Promise} [dataPromise] Data promise, if any
 */
ve.init.mw.ArticleTarget.prototype.reloadSurface = function ( newMode, dataPromise ) {
	this.setDefaultMode( newMode );
	this.clearDiff();
	// Create progress - will be discarded when surface is destroyed.
	this.getSurface().createProgress(
		ve.createDeferred().promise(),
		ve.msg( newMode === 'source' ? 'visualeditor-mweditmodesource-progress' : 'visualeditor-mweditmodeve-progress' ),
		true /* non-cancellable */
	);
	this.load( dataPromise );
};

/**
 * Display the given redirect subtitle and redirect page content header on the page.
 *
 * @param {jQuery} $sub Redirect subtitle, see #buildRedirectSub
 * @param {jQuery} $msg Redirect page content header, see #buildRedirectMsg
 */
ve.init.mw.ArticleTarget.prototype.updateRedirectInterface = function ( $sub, $msg ) {
	var $currentSub, $currentMsg, $subtitle,
		target = this;

	// For the subtitle, replace the real one with ours.
	// This is more complicated than it should be because we have to fiddle with the <br>.
	$currentSub = $( '#redirectsub' );
	if ( $currentSub.length ) {
		if ( $sub.length ) {
			$currentSub.replaceWith( $sub );
		} else {
			$currentSub.prev().filter( 'br' ).remove();
			$currentSub.remove();
		}
	} else {
		$subtitle = $( '#contentSub' );
		if ( $sub.length ) {
			if ( $subtitle.children().length ) {
				$subtitle.append( $( '<br>' ) );
			}
			$subtitle.append( $sub );
		}
	}

	if ( $msg.length ) {
		$msg
			// We need to be able to tell apart the real one and our fake one
			.addClass( 've-redirect-header' )
			.on( 'click', function ( e ) {
				var windowAction = ve.ui.actionFactory.create( 'window', target.getSurface() );
				windowAction.open( 'meta', { page: 'settings' } );
				e.preventDefault();
			} );
	}
	// For the content header, the real one is hidden, insert ours before it.
	$currentMsg = $( '.ve-redirect-header' );
	if ( $currentMsg.length ) {
		$currentMsg.replaceWith( $msg );
	} else {
		// Hack: This is normally inside #mw-content-text, but that's hidden while editing.
		$( '#mw-content-text' ).before( $msg );
	}
};

/**
 * Render a list of categories
 *
 * Duplicate items are not shown.
 *
 * @param {ve.dm.MetaItem[]} categoryItems Array of category metaitems to display
 * @return {jQuery.Promise} A promise which will be resolved with the rendered categories
 */
ve.init.mw.ArticleTarget.prototype.renderCategories = function ( categoryItems ) {
	var $normal, $hidden, categoriesNormal, categoriesHidden,
		promises = [],
		categories = { hidden: {}, normal: {} };
	categoryItems.forEach( function ( categoryItem, index ) {
		var attributes = ve.cloneObject( ve.getProp( categoryItem, 'element', 'attributes' ) );
		attributes.index = index;
		promises.push( ve.init.platform.linkCache.get( attributes.category ).done( function ( result ) {
			var group = result.hidden ? categories.hidden : categories.normal;
			// In case of duplicates, first entry wins (like in MediaWiki)
			if ( !group[ attributes.category ] || group[ attributes.category ].index > attributes.index ) {
				group[ attributes.category ] = attributes;
			}
		} ) );
	} );
	return ve.promiseAll( promises ).then( function () {
		var $output = $( '<div>' ).addClass( 'catlinks' );
		function renderPageLink( page ) {
			var title = mw.Title.newFromText( page );
			return $( '<a>' ).attr( 'rel', 'mw:WikiLink' ).attr( 'href', title.getUrl() ).text( title.getMainText() );
		}
		function renderPageLinks( pages ) {
			var i, $list = $( '<ul>' );
			for ( i = 0; i < pages.length; i++ ) {
				$list.append( $( '<li>' ).append( renderPageLink( pages[ i ] ) ) );
			}
			return $list;
		}
		function categorySort( group, a, b ) {
			return group[ a ].index - group[ b ].index;
		}
		categoriesNormal = Object.keys( categories.normal );
		if ( categoriesNormal.length ) {
			categoriesNormal.sort( categorySort.bind( null, categories.normal ) );
			$normal = $( '<div>' ).addClass( 'mw-normal-catlinks' );
			$normal.append(
				renderPageLink( ve.msg( 'pagecategorieslink' ) ).text( ve.msg( 'pagecategories', categoriesNormal.length ) ),
				ve.msg( 'colon-separator' ),
				renderPageLinks( categoriesNormal )
			);
			$output.append( $normal );
		}
		categoriesHidden = Object.keys( categories.hidden );
		if ( categoriesHidden.length ) {
			categoriesHidden.sort( categorySort.bind( null, categories.hidden ) );
			$hidden = $( '<div>' ).addClass( 'mw-hidden-catlinks' );
			if ( mw.user.options.get( 'showhiddencats' ) ) {
				$hidden.addClass( 'mw-hidden-cats-user-shown' );
			} else if ( mw.config.get( 'wgNamespaceIds' ).category === mw.config.get( 'wgNamespaceNumber' ) ) {
				$hidden.addClass( 'mw-hidden-cats-ns-shown' );
			} else {
				$hidden.addClass( 'mw-hidden-cats-hidden' );
			}
			$hidden.append(
				ve.msg( 'hidden-categories', categoriesHidden.length ),
				ve.msg( 'colon-separator' ),
				renderPageLinks( categoriesHidden )
			);
			$output.append( $hidden );
		}
		return $output;
	} );
};

// Used in tryTeardown
ve.ui.windowFactory.register( mw.widgets.AbandonEditDialog );
