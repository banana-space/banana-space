( function () {
	'use strict';

	mw.flow.ve = {
		ui: {}
	};

	/**
	 * Flow-specific target, inheriting from the stand-alone target
	 *
	 * @class
	 * @extends ve.init.mw.Target
	 *
	 * @param {Object} config Configuration options
	 */
	mw.flow.ve.Target = function FlowVeTarget( config ) {
		config = config || {};

		// Parent constructor
		mw.flow.ve.Target.super.call( this, ve.extendObject( {
			toolbarConfig: { actions: true, $overlay: true, position: 'bottom' }
		}, config ) );

		this.id = config.id;
		this.switchingPromise = null;
	};

	OO.inheritClass( mw.flow.ve.Target, ve.init.mw.Target );

	/**
	 * @event switchMode
	 * @param {jQuery.Promise} promise Promise resolved when switch is complete
	 * @param {string} newMode Mode being switched to ('visual' or 'source')
	 */

	/**
	 * @event submit
	 */

	// Static

	mw.flow.ve.Target.static.name = 'flow';

	mw.flow.ve.Target.static.modes = [ 'visual', 'source' ];

	mw.flow.ve.Target.static.toolbarGroups = [
		{
			name: 'style',
			type: 'list',
			icon: 'textStyle',
			title: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
			include: [ { group: 'textStyle' }, 'language', 'clear' ],
			forceExpand: [ 'bold', 'italic' ],
			demote: [ 'strikethrough', 'code', 'underline', 'language', 'big', 'small', 'clear' ]
		},

		{
			name: 'link',
			include: [ 'link' ]
		},

		{
			name: 'flowMention',
			include: [ 'flowMention' ]
		}
	];

	// Allow pasting links
	mw.flow.ve.Target.static.importRules = ve.copy( mw.flow.ve.Target.static.importRules );
	mw.flow.ve.Target.static.importRules.external.blacklist[ 'link/mwExternal' ] = false;

	mw.flow.ve.Target.static.actionGroups = [ {
		name: 'editMode',
		type: 'list',
		icon: 'edit',
		title: mw.msg( 'visualeditor-mweditmode-tooltip' ),
		include: [ 'editModeVisual', 'editModeSource' ]
	} ];

	// Methods

	mw.flow.ve.Target.prototype.addSurface = function ( dmDoc, config ) {
		// eslint-disable-next-line no-jquery/no-global-selector
		config = ve.extendObject( { $overlayContainer: $( '#content' ) }, config );
		// Parent method
		return mw.flow.ve.Target.super.prototype.addSurface.call( this, dmDoc, config );
	};

	/**
	 * Load content into the editor
	 *
	 * @param {string} content HTML or wikitext
	 */
	mw.flow.ve.Target.prototype.loadContent = function ( content ) {
		var doc,
			sessionState = ve.init.platform.sessionStorage.getObject( this.id + '/ve-docstate' );

		if ( sessionState && !this.switchingDeferred ) {
			content = ve.init.platform.sessionStorage.get( this.id + '/ve-dochtml' );
			this.setDefaultMode( sessionState.mode );
			this.recovered = true;
		} else {
			// TODO: If recovery data is from the wrong mode, switch mode
			this.recovered = false;
		}

		// We have to pass null for the section parameter so that <section> tags get unwrapped
		doc = this.constructor.static.parseDocument( content, this.getDefaultMode(), null );
		this.originalHtml = content;

		this.documentReady( doc );
	};

	mw.flow.ve.Target.prototype.attachToolbar = function () {
		this.$element.after( this.getToolbar().$element );
	};

	mw.flow.ve.Target.prototype.setDisabled = function ( disabled ) {
		var i, len;
		for ( i = 0, len = this.surfaces.length; i < len; i++ ) {
			this.surfaces[ i ].setReadOnly( disabled );
		}
	};

	/**
	 * Switch between visual and source mode.
	 *
	 * If a switch is already in progress, the promise for that switch is returned,
	 * and no new switch is initiated.
	 *
	 * @return {jQuery.Promise} Promise that is resolved when the switch is complete
	 */
	mw.flow.ve.Target.prototype.switchMode = function () {
		var newMode, oldFormat, newFormat, doc, content;

		if ( this.switchingDeferred ) {
			return this.switchingDeferred;
		}

		newMode = this.getDefaultMode() === 'visual' ? 'source' : 'visual';
		oldFormat = newMode === 'visual' ? 'wikitext' : 'html';
		newFormat = newMode === 'visual' ? 'html' : 'wikitext';
		doc = this.getSurface().getDom();
		// When coming from visual mode, getDom() returns an HTMLDocument, otherwise a string
		content = oldFormat === 'html' ? this.getHtml( doc ) : doc;

		this.setDefaultMode( newMode );
		this.switchingDeferred = $.Deferred();
		this.convertContent( content, oldFormat, newFormat )
			.then( this.loadContent.bind( this ) )
			.fail( this.switchingDeferred.reject );

		this.emit( 'switchMode', this.switchingDeferred, newMode );
		return this.switchingDeferred;
	};

	mw.flow.ve.Target.prototype.surfaceReady = function () {
		var deferred,
			surfaceModel = this.getSurface().getModel();

		if ( this.switchingDeferred ) {
			deferred = this.switchingDeferred;
			this.switchingDeferred = null;
			deferred.resolve();
		}

		surfaceModel.setAutosaveDocId( this.id );
		this.initAutosave();

		// Parent method
		mw.flow.ve.Target.super.prototype.surfaceReady.apply( this, arguments );

		// Re-emit main surface 'submit' as target 'submit'
		this.getSurface().on( 'submit', this.emit.bind( this, 'submit' ) );
	};

	/**
	 * Convert content from one format to another.
	 *
	 * It's safe to call this function with fromFormat=toFormat: in that case, the returned promise
	 * will be resolved immediately with the original content.
	 *
	 * @param {string} content Content in old format
	 * @param {string} fromFormat Old format name
	 * @param {string} toFormat New format name
	 * @return {jQuery.Promise} Promise resolved with content converted to new format
	 */
	mw.flow.ve.Target.prototype.convertContent = function ( content, fromFormat, toFormat ) {
		if ( fromFormat === toFormat ) {
			return $.Deferred().resolve( content ).promise();
		}
		if (
			content === '' ||
			( fromFormat === 'html' && content === '<!doctype html><html><head></head><body></body></html>' )
		) {
			return $.Deferred().resolve( '' ).promise();
		}

		return new mw.Api().post( {
			action: 'flow-parsoid-utils',
			from: fromFormat,
			to: toFormat,
			content: content,
			title: mw.config.get( 'wgPageName' )
		} )
			.then( function ( data ) {
				return data[ 'flow-parsoid-utils' ].content;
			}, function () {
				return mw.msg( 'flow-error-parsoid-failure' );
			} );
	};

	// Registration

	ve.init.mw.targetFactory.register( mw.flow.ve.Target );

}() );
