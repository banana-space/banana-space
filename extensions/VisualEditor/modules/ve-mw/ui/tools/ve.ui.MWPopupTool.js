/*!
 * VisualEditor MediaWiki UserInterface popup tool classes.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki UserInterface popup tool.
 *
 * @class
 * @abstract
 * @extends OO.ui.PopupTool
 * @constructor
 * @param {string} title Title
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config]
 * @cfg {number} [width] Popup width. Upstream default is 320.
 */
ve.ui.MWPopupTool = function VeUiMWPopupTool( title, toolGroup, config ) {
	// Configuration initialization
	config = ve.extendObject( { popup: { head: true, label: title, width: config && config.width } }, config );

	// Parent constructor
	ve.ui.MWPopupTool.super.call( this, toolGroup, config );

	this.popup.connect( this, {
		ready: 'onPopupOpened',
		closing: 'onPopupClosing'
	} );

	this.$element.addClass( 've-ui-mwPopupTool' );

	this.$link.on( 'click', this.onToolLinkClick.bind( this ) );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWPopupTool, OO.ui.PopupTool );

/**
 * Handle to call when popup is opened.
 */
ve.ui.MWPopupTool.prototype.onPopupOpened = function () {
	this.popup.closeButton.focus();
};

/**
 * Handle to call when popup is closing
 */
ve.ui.MWPopupTool.prototype.onPopupClosing = function () {
	this.$link.trigger( 'focus' );
};

/**
 * Handle clicks on the main tool button.
 *
 * @param {jQuery.Event} e Click event
 */
ve.ui.MWPopupTool.prototype.onToolLinkClick = function () {
	if ( this.popup.isVisible() ) {
		// Popup will be visible if this just opened, thanks to sequencing.
		// Can't just track this with toggle, because the notices popup is auto-opened and we
		// want to know about deliberate interactions.
		ve.track( 'activity.' + this.constructor.static.name + 'Popup', { action: 'show' } );
	}
};

/**
 * MediaWiki UserInterface notices popup tool.
 *
 * @class
 * @extends ve.ui.MWPopupTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config]
 */
ve.ui.MWNoticesPopupTool = function VeUiMWNoticesPopupTool( toolGroup, config ) {
	// Parent constructor
	ve.ui.MWNoticesPopupTool.super.call(
		this,
		ve.msg( 'visualeditor-editnotices-tooltip' ),
		toolGroup,
		ve.extendObject( config, { width: 380 } )
	);
};

/* Inheritance */

OO.inheritClass( ve.ui.MWNoticesPopupTool, ve.ui.MWPopupTool );

/* Static Properties */

ve.ui.MWNoticesPopupTool.static.name = 'notices';
ve.ui.MWNoticesPopupTool.static.group = 'utility';
ve.ui.MWNoticesPopupTool.static.icon = 'alert';
ve.ui.MWNoticesPopupTool.static.title = OO.ui.deferMsg( 'visualeditor-editnotices-tooltip' );
ve.ui.MWNoticesPopupTool.static.autoAddToCatchall = false;
ve.ui.MWNoticesPopupTool.static.autoAddToGroup = false;

/* Methods */

/**
 * Set notices to display
 *
 * @param {string[]} notices A (non-empty) list of notices
 */
ve.ui.MWNoticesPopupTool.prototype.setNotices = function ( notices ) {
	var tool = this,
		count = notices.length;

	this.popup.setLabel( ve.msg(
		'visualeditor-editnotices-tool',
		mw.language.convertNumber( count )
	) );

	if ( this.$items ) {
		this.$items.remove();
	}

	this.$items = $( '<div>' ).addClass( 've-ui-mwNoticesPopupTool-items' );
	this.noticeItems = [];

	notices.forEach( function ( item ) {
		var $element = $( '<div>' )
			.addClass( 've-ui-mwNoticesPopupTool-item' )
			.html( typeof item === 'string' ? item : item.message );
		ve.targetLinksToNewWindow( $element[ 0 ] );

		tool.noticeItems.push( {
			$element: $element,
			type: item.type
		} );

		tool.$items.append( $element );
	} );

	this.popup.$body.append( this.$items );
	// Fire content hook
	mw.hook( 'wikipage.content' ).fire( this.popup.$body );
};

/**
 * Get the tool title.
 *
 * @inheritdoc
 */
ve.ui.MWNoticesPopupTool.prototype.getTitle = function () {
	var items = this.toolbar.getTarget().getEditNotices();

	// eslint-disable-next-line mediawiki/msg-doc
	return ve.msg( this.constructor.static.title, items.length );
};

/* Registration */

ve.ui.toolFactory.register( ve.ui.MWNoticesPopupTool );

/**
 * MediaWiki UserInterface help popup tool.
 *
 * @class
 * @extends ve.ui.MWPopupTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.MWHelpPopupTool = function VeUiMWHelpPopupTool( toolGroup, config ) {
	// Parent constructor
	ve.ui.MWHelpPopupTool.super.call( this, ve.msg( 'visualeditor-help-tool' ), toolGroup, config );

	// Properties
	this.$items = $( '<div>' );
	this.feedbackPromise = null;
	this.helpButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'help',
		title: ve.msg( 'visualeditor-help-title' ),
		href: new mw.Title( ve.msg( 'visualeditor-help-link' ) ).getUrl(),
		target: '_blank',
		label: ve.msg( 'visualeditor-help-label' )
	} );
	this.keyboardShortcutsButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'keyboard',
		label: ve.msg( 'visualeditor-dialog-command-help-title' )
	} );
	this.feedbackButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'speechBubble',
		label: ve.msg( 'visualeditor-feedback-tool' )
	} );

	// Events
	this.feedbackButton.connect( this, { click: 'onFeedbackClick' } );
	this.keyboardShortcutsButton.connect( this, { click: 'onKeyboardShortcutsClick' } );

	// Initialization
	this.$items
		.addClass( 've-ui-mwHelpPopupTool-items' )
		.append(
			$( '<div>' )
				.addClass( 've-ui-mwHelpPopupTool-item' )
				.text( ve.msg( 'visualeditor-beta-warning' ) )
		)
		.append(
			$( '<div>' )
				.addClass( 've-ui-mwHelpPopupTool-item' )
				.append( this.helpButton.$element )
				.append( this.keyboardShortcutsButton.$element )
				.append( this.feedbackButton.$element )
		);
	ve.targetLinksToNewWindow( this.$items[ 0 ] );
	this.popup.$body.append( this.$items );
	this.popup.$element.attr( 'aria-label', ve.msg( 'visualeditor-help-tool' ) );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWHelpPopupTool, ve.ui.MWPopupTool );

/* Static Properties */

ve.ui.MWHelpPopupTool.static.name = 'help';
ve.ui.MWHelpPopupTool.static.group = 'utility';
ve.ui.MWHelpPopupTool.static.icon = 'help';
ve.ui.MWHelpPopupTool.static.title = OO.ui.deferMsg( 'visualeditor-help-tool' );
ve.ui.MWHelpPopupTool.static.autoAddToCatchall = false;
ve.ui.MWHelpPopupTool.static.autoAddToGroup = false;

/* Methods */

/**
 * Handle clicks on the feedback button.
 */
ve.ui.MWHelpPopupTool.prototype.onFeedbackClick = function () {
	var tool = this;
	this.popup.toggle( false );
	if ( !this.feedbackPromise ) {
		this.feedbackPromise = mw.loader.using( 'mediawiki.feedback' ).then( function () {
			var feedbackConfig, veConfig,
				mode = tool.toolbar.getSurface().getMode();

			// This can't be constructed until the editor has loaded as it uses special messages
			feedbackConfig = {
				bugsLink: new mw.Uri( 'https://phabricator.wikimedia.org/maniphest/task/edit/form/1/?projects=VisualEditor' ),
				bugsListLink: new mw.Uri( 'https://phabricator.wikimedia.org/maniphest/query/eSHgNozkIsuv/' ),
				showUseragentCheckbox: true,
				useragentCheckboxMandatory: true
			};

			// If so configured, tell mw.feedback that we're posting to a remote wiki and set the title
			veConfig = mw.config.get( 'wgVisualEditorConfig' );
			if ( veConfig.feedbackApiUrl ) {
				feedbackConfig.apiUrl = veConfig.feedbackApiUrl;
				feedbackConfig.title = new mw.Title(
					mode === 'source' ?
						veConfig.sourceFeedbackTitle : veConfig.feedbackTitle
				);
			} else {
				feedbackConfig.title = new mw.Title(
					mode === 'source' ?
						ve.msg( 'visualeditor-feedback-source-link' ) : ve.msg( 'visualeditor-feedback-link' )
				);
			}

			return new mw.Feedback( feedbackConfig );
		} );
	}
	this.feedbackPromise.done( function ( feedback ) {
		feedback.launch( {
			message: ve.msg( 'visualeditor-feedback-defaultmessage', location.toString() )
		} );
	} );
};

/**
 * Handle clicks on the keyboard shortcuts button.
 */
ve.ui.MWHelpPopupTool.prototype.onKeyboardShortcutsClick = function () {
	this.popup.toggle( false );
	this.toolbar.getSurface().executeCommand( 'commandHelp' );
};

/**
 * @inheritdoc
 */
ve.ui.MWHelpPopupTool.prototype.onSelect = function () {
	var $version;

	// Parent method
	ve.ui.MWHelpPopupTool.super.prototype.onSelect.apply( this, arguments );

	if ( !this.versionPromise && this.popup.isVisible() ) {
		$version = $( '<div>' ).addClass( 've-ui-mwHelpPopupTool-item oo-ui-pendingElement-pending' ).text( '\u00a0' );
		this.$items.append( $version );
		this.versionPromise = ve.init.target.getLocalApi().get( {
			action: 'query',
			meta: 'siteinfo',
			format: 'json',
			siprop: 'extensions'
		} ).then( function ( response ) {
			var extension = response.query.extensions.filter( function ( ext ) {
				return ext.name === 'VisualEditor';
			} )[ 0 ];

			if ( extension && extension[ 'vcs-version' ] ) {
				$version
					.removeClass( 'oo-ui-pendingElement-pending' )
					.empty()
					.append( $( '<a>' )
						.addClass( 've-ui-mwHelpPopupTool-version-link' )
						.attr( 'target', '_blank' )
						.attr( 'rel', 'noopener' )
						.attr( 'href', extension[ 'vcs-url' ] )
						.append( $( '<span>' )
							.addClass( 've-ui-mwHelpPopupTool-version-label' )
							.text( ve.msg( 'visualeditor-version-label' ) + ' ' + extension[ 'vcs-version' ].slice( 0, 7 ) )
						)
					)
					.append( ' ' )
					.append( $( '<span>' )
						.addClass( 've-ui-mwHelpPopupTool-version-date' )
						.text( extension[ 'vcs-date' ] )
					);
			} else {
				$version.remove();
			}
		}, function () {
			$version.remove();
		} );
	}
};

/* Registration */

ve.ui.toolFactory.register( ve.ui.MWHelpPopupTool );
