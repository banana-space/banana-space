/*!
 * VisualEditor UserInterface MWSaveDialog class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for saving MediaWiki pages.
 *
 * Note that most methods are not safe to call before the dialog has initialized, except where
 * noted otherwise.
 *
 * @class
 * @extends OO.ui.ProcessDialog
 *
 * @constructor
 * @param {Object} [config] Config options
 */
ve.ui.MWSaveDialog = function VeUiMwSaveDialog( config ) {
	// Parent constructor
	ve.ui.MWSaveDialog.super.call( this, config );

	// Properties
	this.editSummaryByteLimit = mw.config.get( 'wgCommentByteLimit' );
	this.editSummaryCodePointLimit = mw.config.get( 'wgCommentCodePointLimit' );
	this.restoring = false;
	this.messages = {};
	this.setupDeferred = ve.createDeferred();
	this.checkboxesByName = null;
	this.changedEditSummary = false;
	this.canReview = false;
	this.canPreview = false;
	this.hasDiff = false;
	this.diffElement = null;
	this.diffElementPromise = null;
	this.getDiffElementPromise = null;

	// Initialization
	this.$element.addClass( 've-ui-mwSaveDialog' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWSaveDialog, OO.ui.ProcessDialog );

/* Static Properties */

ve.ui.MWSaveDialog.static.name = 'mwSave';

ve.ui.MWSaveDialog.static.title =
	OO.ui.deferMsg( 'visualeditor-savedialog-title-save' );

ve.ui.MWSaveDialog.static.feedbackUrl = 'https://www.mediawiki.org/wiki/Talk:VisualEditor/Diffs';

ve.ui.MWSaveDialog.static.actions = [
	{
		action: 'save',
		// label will be set by config.saveButtonLabel
		flags: [ 'primary', 'progressive' ],
		modes: [ 'save', 'review', 'preview' ]
	},
	{
		label: OO.ui.deferMsg( 'visualeditor-savedialog-label-resume-editing' ),
		flags: [ 'safe', OO.ui.isMobile() ? 'back' : 'close' ],
		modes: [ 'save', 'conflict' ]
	},
	{
		action: 'review',
		label: OO.ui.deferMsg( 'visualeditor-savedialog-label-review' ),
		modes: [ 'save', 'preview' ]
	},
	{
		action: 'preview',
		label: OO.ui.deferMsg( 'showpreview' ),
		modes: [ 'save', 'review' ]
	},
	{
		action: 'approve',
		label: OO.ui.deferMsg( 'visualeditor-savedialog-label-review-good' ),
		flags: [ 'safe', 'back' ],
		modes: [ 'review', 'preview' ]
	},
	{
		action: 'resolve',
		label: OO.ui.deferMsg( 'visualeditor-savedialog-label-resolve-conflict' ),
		flags: [ 'primary', 'progressive' ],
		modes: 'conflict'
	},
	{
		action: 'report',
		label: OO.ui.deferMsg( 'visualeditor-savedialog-label-visual-diff-report' ),
		flags: [ 'progressive' ],
		modes: 'review',
		framed: false,
		icon: 'feedback',
		classes: [ 've-ui-mwSaveDialog-visualDiffFeedback' ],
		href: ve.ui.MWSaveDialog.static.feedbackUrl
	}
];

/* Events */

/**
 * @event save
 * @param {jQuery.Deferred} saveDeferred Deferred object to resolve/reject when the save
 *  succeeds/fails.
 * Emitted when the user clicks the save button
 */

/**
 * @event review
 * Emitted when the user clicks the review changes button
 */

/**
 * @event preview
 * Emitted when the user clicks the show preview button
 */

/**
 * @event resolve
 * Emitted when the user clicks the resolve conflict button
 */

/**
 * @event retry
 * Emitted when the user clicks the retry/continue save button after an error.
 */

/* Methods */

/**
 * Set review content and show review panel.
 *
 * @param {jQuery.Promise} wikitextDiffPromise Wikitext diff HTML promise
 * @param {jQuery.Promise} visualDiffGeneratorPromise Visual diff promise
 * @param {HTMLDocument} [baseDoc] Base document against which to normalise links when rendering visualDiff
 */
ve.ui.MWSaveDialog.prototype.setDiffAndReview = function ( wikitextDiffPromise, visualDiffGeneratorPromise, baseDoc ) {
	var dialog = this;

	this.clearDiff();

	function createDiffElement( visualDiff ) {
		var diffElement = new ve.ui.DiffElement( visualDiff );
		// The following classes are used here:
		// * mw-content-ltr
		// * mw-content-rtl
		diffElement.$document.addClass( 'mw-body-content mw-parser-output mw-content-' + visualDiff.newDoc.getDir() );
		ve.targetLinksToNewWindow( diffElement.$document[ 0 ] );
		// Run styles so links render with their appropriate classes
		ve.init.platform.linkCache.styleParsoidElements( diffElement.$document, baseDoc );
		mw.libs.ve.fixFragmentLinks( diffElement.$document[ 0 ], mw.Title.newFromText( ve.init.target.getPageName() ), 'mw-save-visualdiff-' );
		return diffElement;
	}

	// Visual diff
	this.$reviewVisualDiff.append( new OO.ui.ProgressBarWidget().$element );
	// Don't generate the DiffElement until the tab is switched to
	this.getDiffElementPromise = function () {
		return visualDiffGeneratorPromise.then( function ( visualDiffGenerator ) {
			return createDiffElement( visualDiffGenerator() );
		} );
	};

	this.baseDoc = baseDoc;

	// Wikitext diff
	this.$reviewWikitextDiff.append( new OO.ui.ProgressBarWidget().$element );
	wikitextDiffPromise.then( function ( wikitextDiff ) {
		if ( wikitextDiff ) {
			dialog.$reviewWikitextDiff.empty().append( wikitextDiff );
		} else {
			dialog.$reviewWikitextDiff.empty().append(
				$( '<div>' ).addClass( 've-ui-mwSaveDialog-no-changes' ).text( ve.msg( 'visualeditor-diff-no-changes' ) )
			);
		}
	}, function ( code, errorObject ) {
		var $errorMessage = ve.init.target.extractErrorMessages( errorObject );

		dialog.$reviewWikitextDiff.empty().append(
			new OO.ui.MessageWidget( {
				type: 'error',
				label: $errorMessage
			} ).$element
		);
	} ).always( function () {
		dialog.updateSize();
	} );

	this.hasDiff = true;
	this.popPending();
	this.swapPanel( 'review' );
};

/**
 * Set preview content and show preview panel.
 *
 * @param {HTMLDocument|string} docOrMsg Document to preview, or error message
 * @param {HTMLDocument} [baseDoc] Base document against which to normalise links, if document provided
 */
ve.ui.MWSaveDialog.prototype.showPreview = function ( docOrMsg, baseDoc ) {
	var body, $heading, redirectMeta, deferred,
		$redirect = $(),
		categories = [],
		modules = [],
		dialog = this;

	if ( docOrMsg instanceof HTMLDocument ) {
		// Extract required modules for stylesheet tags (avoids re-loading styles)
		Array.prototype.forEach.call( docOrMsg.head.querySelectorAll( 'link[rel~=stylesheet]' ), function ( link ) {
			var uri = new mw.Uri( link.href );
			if ( uri.query.modules ) {
				modules = modules.concat( mw.libs.ve.expandModuleNames( uri.query.modules ) );
			}
		} );
		// Remove skin-specific modules (T187075)
		modules = modules.filter( function ( module ) {
			return module.indexOf( 'skins.' ) !== 0;
		} );
		mw.loader.using( modules );
		body = docOrMsg.body;
		// Take a snapshot of all categories
		Array.prototype.forEach.call( body.querySelectorAll( 'link[rel~="mw:PageProp/Category"]' ), function ( element ) {
			categories.push( ve.dm.nodeFactory.createFromElement( ve.dm.MWCategoryMetaItem.static.toDataElement( [ element ] ) ) );
		} );
		// Import body to current document, then resolve attributes against original document (parseDocument called #fixBase)
		document.adoptNode( body );

		// TODO: This code is very similar to ve.ui.PreviewElement+ve.ui.MWPreviewElement
		ve.resolveAttributes( body, docOrMsg, ve.dm.Converter.static.computedAttributes );

		$heading = $( '<h1>' ).addClass( 'firstHeading' );

		// Document title will only be set if wikitext contains {{DISPLAYTITLE}}
		if ( docOrMsg.title ) {
			// HACK: Parse title as it can contain basic wikitext (T122976)
			ve.init.target.getContentApi().post( {
				action: 'parse',
				title: ve.init.target.getPageName(),
				prop: 'displaytitle',
				text: '{{DISPLAYTITLE:' + docOrMsg.title + '}}\n'
			} ).then( function ( response ) {
				if ( ve.getProp( response, 'parse', 'displaytitle' ) ) {
					$heading.html( response.parse.displaytitle );
				}
			} );
		}

		// Redirect
		redirectMeta = body.querySelector( 'link[rel="mw:PageProp/redirect"]' );
		if ( redirectMeta ) {
			$redirect = ve.init.mw.ArticleTarget.static.buildRedirectMsg(
				mw.libs.ve.getTargetDataFromHref(
					redirectMeta.getAttribute( 'href' ),
					document
				).title
			);
		}

		this.$previewViewer.empty().append(
			// TODO: This won't work with formatted titles (T122976)
			$heading.text( docOrMsg.title || mw.Title.newFromText( ve.init.target.getPageName() ).getPrefixedText() ),
			$redirect,
			// The following classes are used here:
			// * mw-content-ltr
			// * mw-content-rtl
			$( '<div>' ).addClass( 'mw-content-' + mw.config.get( 'wgVisualEditor' ).pageLanguageDir ).append(
				body.childNodes
			)
		);

		ve.targetLinksToNewWindow( this.$previewViewer[ 0 ] );
		// Add styles so links render with their appropriate classes
		ve.init.platform.linkCache.styleParsoidElements( this.$previewViewer, baseDoc );
		mw.libs.ve.fixFragmentLinks( this.$previewViewer[ 0 ], mw.Title.newFromText( ve.init.target.getPageName() ), 'mw-save-preview-' );

		if ( categories.length ) {
			// If there are categories, we need to render them. This involves
			// a delay, since they might be hidden categories.
			deferred = ve.init.target.renderCategories( categories ).done( function ( $categories ) {
				dialog.$previewViewer.append( $categories );

				ve.targetLinksToNewWindow( $categories[ 0 ] );
				// Add styles so links render with their appropriate classes
				ve.init.platform.linkCache.styleParsoidElements( $categories, baseDoc );
			} );
		} else {
			deferred = ve.createDeferred().resolve();
		}
		deferred.done( function () {
			// Run hooks so other things can alter the document
			mw.hook( 'wikipage.content' ).fire( dialog.$previewViewer );
		} );
	} else {
		this.$previewViewer.empty().append(
			$( '<em>' ).text( docOrMsg )
		);
	}

	this.popPending();
	this.swapPanel( 'preview' );
};

/**
 * @inheritdoc
 */
ve.ui.MWSaveDialog.prototype.pushPending = function () {
	this.getActions().setAbilities( { review: false, preview: false } );
	return ve.ui.MWSaveDialog.super.prototype.pushPending.call( this );
};

/**
 * @inheritdoc
 */
ve.ui.MWSaveDialog.prototype.popPending = function () {
	var ret = ve.ui.MWSaveDialog.super.prototype.popPending.call( this );
	if ( !this.isPending() ) {
		this.getActions().setAbilities( { review: true, preview: true } );
	}
	return ret;
};

/**
 * Clear the diff displayed in the review panel, if any.
 */
ve.ui.MWSaveDialog.prototype.clearDiff = function () {
	this.$reviewWikitextDiff.empty();
	this.$reviewVisualDiff.empty();
	this.$previewViewer.empty();
	this.hasDiff = false;
	this.diffElement = null;
	this.diffElementPromise = null;
	this.getDiffElementPromise = null;
};

/**
 * Swap state in the save dialog.
 *
 * @param {string} panel One of 'save', 'review' or 'conflict'
 * @param {boolean} [noFocus] Don't attempt to focus anything (e.g. while setting up)
 * @throws {Error} Unknown saveDialog panel
 */
ve.ui.MWSaveDialog.prototype.swapPanel = function ( panel, noFocus ) {
	var currentEditSummaryWikitext,
		mode = panel,
		size = 'medium',
		dialog = this,
		panelObj = dialog[ panel + 'Panel' ];

	if ( ( [ 'save', 'review', 'preview', 'conflict' ].indexOf( panel ) ) === -1 ) {
		throw new Error( 'Unknown saveDialog panel: ' + panel );
	}

	// Update the window title
	// The following messages are used here:
	// * visualeditor-savedialog-title-conflict
	// * visualeditor-savedialog-title-preview
	// * visualeditor-savedialog-title-review
	// * visualeditor-savedialog-title-save
	this.title.setLabel( ve.msg( 'visualeditor-savedialog-title-' + panel ) );

	// Reset save button if we disabled it for e.g. unrecoverable spam error
	this.actions.setAbilities( { save: true } );

	if ( !noFocus ) {
		// On panels without inputs, ensure the dialog is focused so events
		// are captured, e.g. 'Esc' to close
		this.$content[ 0 ].focus();
	}

	switch ( panel ) {
		case 'save':
			if ( !noFocus && this.panels.getCurrentItem() !== this.savePanel ) {
				// HACK: FF needs *another* defer
				setTimeout( function () {
					dialog.editSummaryInput.moveCursorToEnd();
				} );
			}
			break;
		case 'conflict':
			this.actions.setAbilities( { save: false } );
			break;
		case 'preview':
			size = 'full';
			this.previewPanel.$element[ 0 ].focus();
			this.previewPanel.$element.prepend( this.$previewEditSummaryContainer );
			break;
		case 'review':
			size = 'larger';
			this.reviewModeButtonSelect.$element.after( this.$previewEditSummaryContainer );
			setTimeout( function () {
				dialog.updateReviewMode();

				ve.track(
					'activity.' + dialog.constructor.static.name,
					{ action: 'review-initial-' + dialog.reviewModeButtonSelect.findSelectedItem().getData() }
				);
			} );
			break;
	}
	if ( panel === 'preview' || panel === 'review' ) {
		currentEditSummaryWikitext = this.editSummaryInput.getValue();
		if ( this.lastEditSummaryWikitext === undefined || this.lastEditSummaryWikitext !== currentEditSummaryWikitext ) {
			if ( this.editSummaryXhr ) {
				this.editSummaryXhr.abort();
			}
			this.lastEditSummaryWikitext = currentEditSummaryWikitext;
			this.$previewEditSummary.empty();

			if ( !currentEditSummaryWikitext || currentEditSummaryWikitext.trim() === '' ) {
				// Don't bother with an API request for an empty summary
				this.$previewEditSummary.text( ve.msg( 'visualeditor-savedialog-review-nosummary' ) );
			} else {
				this.$previewEditSummary.parent()
					.removeClass( 'oo-ui-element-hidden' )
					.addClass( 'mw-ajax-loader' );
				this.editSummaryXhr = ve.init.target.getContentApi().post( {
					action: 'parse',
					title: ve.init.target.getPageName(),
					prop: '',
					summary: currentEditSummaryWikitext
				} ).done( function ( result ) {
					if ( result.parse.parsedsummary === '' ) {
						dialog.$previewEditSummary.parent().addClass( 'oo-ui-element-hidden' );
					} else {
						// Intentionally treated as HTML
						dialog.$previewEditSummary.html( ve.msg( 'parentheses', result.parse.parsedsummary ) );
						ve.targetLinksToNewWindow( dialog.$previewEditSummary[ 0 ] );
					}
				} ).fail( function () {
					dialog.$previewEditSummary.parent().addClass( 'oo-ui-element-hidden' );
				} ).always( function () {
					dialog.$previewEditSummary.parent().removeClass( 'mw-ajax-loader' );
					dialog.updateSize();
				} );
			}
		}
	}

	// Show the target panel
	this.panels.setItem( panelObj );
	this.setSize( size );

	// Set mode after setting size so that the footer is measured correctly
	this.actions.setMode( mode );

	// Only show preview in source mode
	this.actions.forEach( { actions: 'preview' }, function ( action ) {
		action.toggle( dialog.canPreview );
	} );

	// Diff API doesn't support section=new
	this.actions.forEach( { actions: 'review' }, function ( action ) {
		action.toggle( dialog.canReview );
	} );

	// Support: iOS
	// HACK: iOS Safari sometimes makes the entire panel completely disappear (T221289).
	// Rebuilding it makes it reappear.
	OO.ui.Element.static.reconsiderScrollbars( panelObj.$element[ 0 ] );

	mw.hook( 've.saveDialog.stateChanged' ).fire();
};

/**
 * Show a message in the save dialog.
 *
 * @param {string} name Message's unique name
 * @param {string|jQuery|Array} message Message content (string of HTML, jQuery object or array of
 *  Node objects)
 * @param {Object} [options]
 * @param {boolean} [options.wrap="warning"] Whether to wrap the message in a paragraph and if
 *  so, how. One of "warning", "error" or false.
 */
ve.ui.MWSaveDialog.prototype.showMessage = function ( name, message, options ) {
	var $message;
	if ( !this.messages[ name ] ) {
		options = options || {};
		if ( options.wrap === undefined ) {
			options.wrap = 'warning';
		}
		$message = $( '<div>' ).addClass( 've-ui-mwSaveDialog-message' );
		if ( options.wrap !== false ) {
			$message.append( $( '<p>' ).append(
				// The following messages are used here:
				// * visualeditor-savedialog-label-error
				// * visualeditor-savedialog-label-warning
				$( '<strong>' ).text( mw.msg( 'visualeditor-savedialog-label-' + options.wrap ) ),
				document.createTextNode( mw.msg( 'colon-separator' ) ),
				message
			) );
		} else {
			$message.append( message );
		}
		this.$saveMessages.append( $message.css( 'display', 'none' ) );

		// FIXME: Use CSS transitions
		// eslint-disable-next-line no-jquery/no-slide
		$message.slideDown( {
			duration: 250,
			progress: this.updateSize.bind( this )
		} );

		this.swapPanel( 'save' );

		this.messages[ name ] = $message;
	}
};

/**
 * Remove a message from the save dialog.
 *
 * @param {string} name Message's unique name
 */
ve.ui.MWSaveDialog.prototype.clearMessage = function ( name ) {
	if ( this.messages[ name ] ) {
		this.messages[ name ].slideUp( {
			progress: this.updateSize.bind( this )
		} );
		delete this.messages[ name ];
	}
};

/**
 * Remove all messages from the save dialog.
 */
ve.ui.MWSaveDialog.prototype.clearAllMessages = function () {
	this.$saveMessages.empty();
	this.messages = {};
};

/**
 * Reset the fields of the save dialog.
 */
ve.ui.MWSaveDialog.prototype.reset = function () {
	// Reset summary input
	this.editSummaryInput.setValue( '' );
	// Uncheck minoredit
	if ( this.checkboxesByName.wpMinoredit ) {
		this.checkboxesByName.wpMinoredit.setSelected( false );
	}
	this.clearDiff();
};

/**
 * Initialize MediaWiki page specific checkboxes.
 *
 * This method is safe to call even when the dialog hasn't been initialized yet.
 *
 * @param {OO.ui.FieldLayout[]} checkboxFields Checkbox fields
 */
ve.ui.MWSaveDialog.prototype.setupCheckboxes = function ( checkboxFields ) {
	var dialog = this;
	this.setupDeferred.done( function () {
		checkboxFields.forEach( function ( field ) {
			dialog.$saveCheckboxes.append( field.$element );
		} );
		dialog.updateOptionsBar();
	} );
};

/**
 * Change the edit summary prefilled in the save dialog.
 *
 * This method is safe to call even when the dialog hasn't been initialized yet.
 *
 * @param {string} summary Edit summary to prefill
 */
ve.ui.MWSaveDialog.prototype.setEditSummary = function ( summary ) {
	var dialog = this;
	this.setupDeferred.done( function () {
		dialog.editSummaryInput.setValue( summary );
	} );
};

/**
 * @inheritdoc
 */
ve.ui.MWSaveDialog.prototype.initialize = function () {
	var dialog = this,
		mwString = require( 'mediawiki.String' );

	// Parent method
	ve.ui.MWSaveDialog.super.prototype.initialize.call( this );

	// Properties
	this.panels = new OO.ui.StackLayout( { scrollable: false } );
	this.savePanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true,
		classes: [ 've-ui-mwSaveDialog-savePanel' ]
	} );

	// Byte counter in edit summary
	this.editSummaryCountLabel = new OO.ui.LabelWidget( {
		classes: [ 've-ui-mwSaveDialog-editSummary-count' ],
		label: '',
		title: ve.msg( this.editSummaryCodePointLimit ?
			'visualeditor-editsummary-characters-remaining' : 'visualeditor-editsummary-bytes-remaining' )
	} );

	// Save panel
	this.$editSummaryLabel = $( '<div>' ).addClass( 've-ui-mwSaveDialog-summaryLabel' )
		.html( ve.init.platform.getParsedMessage( 'summary' ) );
	ve.targetLinksToNewWindow( this.$editSummaryLabel[ 0 ] );
	this.editSummaryInput = new ve.ui.MWEditSummaryWidget( {
		$overlay: this.$overlay,
		placeholder: ve.msg( 'visualeditor-editsummary' ),
		classes: [ 've-ui-mwSaveDialog-summary' ],
		inputFilter: function ( value ) {
			// Prevent the user from inputting newlines (this kicks in on paste, etc.)
			return value.replace( /\r?\n/g, ' ' );
		}
	} );
	// Prevent the user from inputting newlines from keyboard
	this.editSummaryInput.$input.on( 'keypress', function ( e ) {
		if ( e.which === OO.ui.Keys.ENTER ) {
			e.preventDefault();

			dialog.showMessage(
				'keyboard-shortcut-submit',
				$( '<p>' ).msg(
					'visualeditor-savedialog-keyboard-shortcut-submit',
					new ve.ui.Trigger( ve.ui.commandHelpRegistry.lookup( 'dialogConfirm' ).shortcuts[ 0 ] ).getMessage()
				),
				{ wrap: false }
			);
		}
	} );
	// Limit length, and display the remaining bytes/characters
	if ( this.editSummaryCodePointLimit ) {
		this.editSummaryInput.$input.codePointLimit( this.editSummaryCodePointLimit );
	} else {
		this.editSummaryInput.$input.byteLimit( this.editSummaryByteLimit );
	}
	this.editSummaryInput.on( 'change', function () {
		var remaining;
		if ( dialog.editSummaryCodePointLimit ) {
			remaining = dialog.editSummaryCodePointLimit - mwString.codePointLength( dialog.editSummaryInput.getValue() );
		} else {
			remaining = dialog.editSummaryByteLimit - mwString.byteLength( dialog.editSummaryInput.getValue() );
		}
		// TODO: This looks a bit weird, there is no unit in the UI, just
		// numbers. Users likely assume characters but then it seems to count
		// down quicker than expected if it's byteLimit. Facing users with the
		// word "byte" is bad? (T42035)
		dialog.changedEditSummary = true;
		if ( remaining > 99 ) {
			dialog.editSummaryCountLabel.setLabel( '' );
		} else {
			dialog.editSummaryCountLabel.setLabel( mw.language.convertNumber( remaining ) );
		}

		dialog.updateOptionsBar();
	} );

	this.$saveCheckboxes = $( '<div>' ).addClass( 've-ui-mwSaveDialog-checkboxes' );
	this.$saveOptions = $( '<div>' ).addClass( 've-ui-mwSaveDialog-options' ).append(
		this.$saveCheckboxes,
		this.editSummaryCountLabel.$element
	);
	this.$license = $( '<p>' ).addClass( 've-ui-mwSaveDialog-license' )
		.html( ve.init.platform.getParsedMessage( 'copyrightwarning' ) );
	this.$saveMessages = $( '<div>' ).addClass( 've-ui-mwSaveDialog-messages' );
	this.$saveFoot = $( '<div>' ).addClass( 've-ui-mwSaveDialog-foot' ).append( this.$license );
	ve.targetLinksToNewWindow( this.$saveFoot[ 0 ] );
	this.savePanel.$element.append(
		this.$editSummaryLabel,
		this.editSummaryInput.$element,
		this.$saveOptions,
		this.$saveMessages,
		this.$saveFoot
	);

	this.updateOptionsBar();

	// Review panel
	this.reviewPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );

	this.$reviewVisualDiff = $( '<div>' ).addClass( 've-ui-mwSaveDialog-viewer' );
	this.$reviewWikitextDiff = $( '<div>' ).addClass( 've-ui-mwSaveDialog-viewer' );

	this.reviewModeButtonSelect = new OO.ui.ButtonSelectWidget( {
		items: [
			new OO.ui.ButtonOptionWidget( { data: 'visual', icon: 'eye', label: ve.msg( 'visualeditor-savedialog-review-visual' ) } ),
			new OO.ui.ButtonOptionWidget( { data: 'source', icon: 'wikiText', label: ve.msg( 'visualeditor-savedialog-review-wikitext' ) } )
		],
		classes: [ 've-ui-mwSaveDialog-reviewMode' ]
	} );
	this.reviewModeButtonSelect.connect( this, {
		choose: 'onReviewChoose',
		select: 'updateReviewMode'
	} );

	this.$previewEditSummary = $( '<span>' ).addClass( 've-ui-mwSaveDialog-summaryPreview' ).addClass( 'comment' );
	this.$previewEditSummaryContainer = $( '<div>' )
		.addClass( 'mw-summary-preview' )
		.text( ve.msg( 'summary-preview' ) )
		.append( $( '<br>' ), this.$previewEditSummary );
	this.$reviewActions = $( '<div>' ).addClass( 've-ui-mwSaveDialog-actions' );
	this.reviewPanel.$element.append(
		this.reviewModeButtonSelect.$element,
		this.$reviewVisualDiff,
		this.$reviewWikitextDiff,
		this.$reviewActions
	);

	// Preview panel
	this.previewPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );
	this.$previewViewer = $( '<div>' ).addClass( 'mw-body-content mw-parser-output' );
	this.previewPanel.$element
		// Make focusable for keyboard accessible scrolling
		.prop( 'tabIndex', 0 )
		.append( this.$previewViewer );

	// Conflict panel
	this.conflictPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );
	this.$conflict = $( '<div>' ).addClass( 've-ui-mwSaveDialog-conflict' )
		.html( ve.init.platform.getParsedMessage( 'visualeditor-editconflict' ) );
	ve.targetLinksToNewWindow( this.$conflict[ 0 ] );
	this.conflictPanel.$element.append( this.$conflict );

	// Panel stack
	this.panels.addItems( [
		this.savePanel,
		this.reviewPanel,
		this.previewPanel,
		this.conflictPanel
	] );

	// Initialization
	this.$body.append( this.panels.$element );

	this.setupDeferred.resolve();
};

ve.ui.MWSaveDialog.prototype.updateOptionsBar = function () {
	var showOptions = !!this.editSummaryCountLabel.getLabel() || !this.$saveCheckboxes.is( ':empty' );
	if ( showOptions !== this.showOptions ) {
		this.savePanel.$element.toggleClass( 've-ui-mwSaveDialog-withOptions', showOptions );
		this.showOptions = showOptions;
		this.updateSize();
	}
};

/**
 * Update the current review mode
 *
 * @param  {OO.ui.ButtonOptionWidget} [button] The button clicked, or false if this is the initial setup
 */
ve.ui.MWSaveDialog.prototype.updateReviewMode = function () {
	var dialog = this,
		diffMode = this.reviewModeButtonSelect.findSelectedItem().getData(),
		surfaceMode = ve.init.target.getSurface().getMode(),
		isVisual = diffMode === 'visual';

	if ( !this.hasDiff ) {
		return;
	}

	// Config values used here:
	// * visualeditor-diffmode-visual
	// * visualeditor-diffmode-source
	ve.userConfig( 'visualeditor-diffmode-' + surfaceMode, diffMode );

	// Hack: cache report action so it is getable even when hidden (see T174497)
	if ( !this.report ) {
		this.report = this.getActions().get( { actions: 'report' } )[ 0 ];
	}

	this.$reviewVisualDiff.toggleClass( 'oo-ui-element-hidden', !isVisual );
	this.$reviewWikitextDiff.toggleClass( 'oo-ui-element-hidden', isVisual );
	if ( isVisual ) {
		this.report.toggle( true );
		if ( !this.diffElement ) {
			if ( !this.diffElementPromise ) {
				this.diffElementPromise = this.getDiffElementPromise().then( function ( diffElement ) {
					dialog.diffElement = diffElement;
					dialog.$reviewVisualDiff.empty().append( diffElement.$element );
					dialog.positionDiffElement();
				} );
			}
			return;
		}
		this.positionDiffElement();
	} else {
		this.report.toggle( false );
	}

	// Support: iOS
	// HACK: iOS Safari sometimes makes the entire panel completely disappear (T219680).
	// Rebuilding it makes it reappear.
	OO.ui.Element.static.reconsiderScrollbars( this.reviewPanel.$element[ 0 ] );

	this.updateSize();
};

/**
 * Update the current review mode
 *
 * @param {OO.ui.OptionWidget} item Item chosen
 */
ve.ui.MWSaveDialog.prototype.onReviewChoose = function ( item ) {
	ve.track( 'activity.' + this.constructor.static.name, { action: 'review-switch-' + item.getData() } );
};

/**
 * @inheritdoc
 */
ve.ui.MWSaveDialog.prototype.setDimensions = function () {
	// Parent method
	ve.ui.MWSaveDialog.parent.prototype.setDimensions.apply( this, arguments );

	if ( !this.positioning ) {
		this.positionDiffElement();
	}
};

/**
 * Re-position elements within the diff element
 *
 * Should be called whenever the diff element's container has changed width.
 */
ve.ui.MWSaveDialog.prototype.positionDiffElement = function () {
	var dialog = this;
	if ( this.panels.getCurrentItem() === this.reviewPanel ) {
		setTimeout( function () {
			dialog.withoutSizeTransitions( function () {
				// This is delayed, so check the visual diff is still visible
				if ( dialog.diffElement && dialog.isVisible() && dialog.reviewModeButtonSelect.findSelectedItem().getData() === 'visual' ) {
					dialog.diffElement.positionDescriptions();
					dialog.positioning = true;
					dialog.updateSize();
					dialog.positioning = false;
				}
			} );
		}, OO.ui.theme.getDialogTransitionDuration() );
	}
};

/**
 * @inheritdoc
 * @param {Object} [data]
 * @param {boolean} [data.canReview] User can review changes
 * @param {boolean} [data.canPreview] User can preview changes
 * @param {OO.ui.FieldLayout[]} [data.checkboxFields] Checkbox fields
 * @param {Object} [data.checkboxesByName] Checkbox widgets, indexed by name
 * @param {string} [data.sectionTitle] Section title, if in new section mode
 * @param {string} [data.editSummary] Edit summary
 * @param {string} [data.initialPanel='save'] Initial panel to show
 * @param {jQuery|string|OO.ui.HtmlSnippet|Function|null} [data.saveButtonLabel] Label for the save button
 */
ve.ui.MWSaveDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWSaveDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var name,
				surfaceMode = ve.init.target.getSurface().getMode();

			this.canReview = !!data.canReview;
			this.canPreview = !!data.canPreview;
			this.setupCheckboxes( data.checkboxFields || [] );
			this.checkboxesByName = data.checkboxesByName || {};

			function trackCheckbox( name ) {
				ve.track( 'activity.mwSave', { action: 'checkbox-' + name } );
			}
			for ( name in this.checkboxesByName ) {
				this.checkboxesByName[ name ].$element.off( '.mwSave' ).on( 'click.mwSave', trackCheckbox.bind( this, name ) );
			}

			if ( data.sectionTitle ) {
				this.setEditSummary( ve.msg( 'newsectionsummary', data.sectionTitle ) );
				this.editSummaryInput.setDisabled( true );
			} else {
				this.editSummaryInput.setDisabled( false );
				if ( !this.changedEditSummary ) {
					this.setEditSummary( data.editSummary );
				}
			}

			// Config values used here:
			// * visualeditor-diffmode-visual
			// * visualeditor-diffmode-source
			this.reviewModeButtonSelect.selectItemByData(
				ve.userConfig( 'visualeditor-diffmode-' + surfaceMode ) || surfaceMode
			);

			// Old messages should not persist
			this.clearAllMessages();
			// Don't focus during setup to prevent scroll jumping (T153010)
			this.swapPanel( data.initialPanel || 'save', true );
			// Update save button label
			this.actions.forEach( { actions: 'save' }, function ( action ) {
				action.setLabel( data.saveButtonLabel );
			} );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWSaveDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWSaveDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			// HACK: iOS Safari sometimes makes the entire panel completely disappear (T221289).
			// Rebuilding it makes it reappear.
			OO.ui.Element.static.reconsiderScrollbars( this.panels.getCurrentItem().$element[ 0 ] );

			// Support: Firefox
			// In Firefox, trying to focus a hidden input will throw an
			// exception. This would happen when opening the preview via
			// keyboard shortcut.
			if ( this.panels.getCurrentItem() === this.savePanel ) {
				// This includes a #focus call
				this.editSummaryInput.moveCursorToEnd();
			}
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWSaveDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWSaveDialog.super.prototype.getTeardownProcess.call( this, data )
		.next( function () {
			this.emit( 'close' );
			this.report = null;
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWSaveDialog.prototype.getActionProcess = function ( action ) {
	ve.track( 'activity.' + this.constructor.static.name, { action: 'dialog-' + ( action || 'abort' ) } );

	if ( action === 'save' ) {
		return new OO.ui.Process( function () {
			var saveDeferred = ve.createDeferred();
			this.clearMessage( 'keyboard-shortcut-submit' );
			this.emit( 'save', saveDeferred );
			return saveDeferred.promise();
		}, this );
	}
	if ( action === 'review' || action === 'preview' || action === 'resolve' ) {
		return new OO.ui.Process( function () {
			this.emit( action );
		}, this );
	}
	if ( action === 'approve' ) {
		return new OO.ui.Process( function () {
			this.swapPanel( 'save' );
		}, this );
	}
	if ( action === 'report' ) {
		return new OO.ui.Process( function () {
			window.open( this.constructor.static.feedbackUrl );
		}, this );
	}

	return ve.ui.MWSaveDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * @inheritdoc
 */
ve.ui.MWSaveDialog.prototype.getBodyHeight = function () {
	// Don't vary the height when the foot is made visible or not
	return this.panels.getCurrentItem().$element.outerHeight( true );
};

/**
 * Handle retry button click events.
 *
 * Hides errors and then tries again.
 */
ve.ui.MWSaveDialog.prototype.onRetryButtonClick = function () {
	this.emit( 'retry' );
	ve.ui.MWSaveDialog.super.prototype.onRetryButtonClick.apply( this, arguments );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWSaveDialog );
