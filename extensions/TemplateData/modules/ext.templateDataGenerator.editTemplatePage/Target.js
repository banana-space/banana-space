/**
 * Template data edit ui target
 *
 * @class
 * @extends OO.ui.Element
 * @mixin OO.EventEmitter
 *
 * @constructor
 * @param {jQuery} $textarea Editor textarea
 * @param {Object} config Configuration options
 */
mw.TemplateData.Target = function mwTemplateDataTarget( $textarea, config ) {
	var $helpLink, relatedPage,
		target = this;

	// Parent constructor
	mw.TemplateData.Target.super.call( this, config );

	// Mixin constructor
	OO.EventEmitter.call( this );

	this.pageName = config.pageName;
	this.parentPage = config.parentPage;
	this.isPageSubLevel = !!config.isPageSubLevel;
	this.isDocPage = !!config.isDocPage;
	this.docSubpage = config.docSubpage;
	this.$textarea = $textarea;

	this.editOpenDialogButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'templatedata-editbutton' )
	} );

	this.editNoticeLabel = new OO.ui.LabelWidget( {
		classes: [ 'tdg-editscreen-error-msg' ]
	} )
		.toggle( false );

	$helpLink = $( '<a>' )
		.attr( {
			href: mw.msg( 'templatedata-helplink-target' ),
			target: '_blank'
		} )
		.addClass( 'tdg-editscreen-main-helplink' )
		.text( mw.msg( 'templatedata-helplink' ) );

	this.windowManager = new OO.ui.WindowManager();
	// Ensure OOUI's window manager is on top of ours, because we use OO.ui.confirm() elsewhere.
	// This is a bit silly...
	OO.ui.getWindowManager().$element.before( this.windowManager.$element );

	// Dialog
	this.tdgDialog = new mw.TemplateData.Dialog( config );
	this.windowManager.addWindows( [ this.tdgDialog ] );

	this.sourceHandler = new mw.TemplateData.SourceHandler( {
		fullPageName: this.pageName,
		parentPage: this.parentPage,
		isPageSubLevel: this.isPageSubLevel
	} );

	// Check if there's already a templatedata in a related page
	relatedPage = this.isDocPage ? this.parentPage : this.pageName + '/' + this.docSubpage;
	this.sourceHandler.getApi( relatedPage )
		.then( function ( result ) {
			var msg, matches, content,
				response = result.query.pages[ result.query.pageids[ 0 ] ];
			// HACK: When checking whether a related page (parent for /doc page or
			// vice versa) already has a templatedata string, we shouldn't
			// ask for the 'templatedata' action but rather the actual content
			// of the related page, otherwise we get embedded templatedata and
			// wrong information is presented.
			if ( response.missing === undefined ) {
				content = response.revisions[ 0 ][ '*' ];
				matches = content.match( /<templatedata>/i );
				// There's a templatedata string
				if ( matches ) {
					// HACK: Setting a link in the messages doesn't work. The bug report offers
					// a somewhat hacky work around that includes setting a separate message
					// to be parsed.
					// https://phabricator.wikimedia.org/T49395#490610
					msg = mw.message( 'templatedata-exists-on-related-page', relatedPage ).plain();
					mw.messages.set( { 'templatedata-string-exists-hack-message': msg } );
					msg = mw.message( 'templatedata-string-exists-hack-message' ).parse();

					target.setNoticeMessage( msg, 'warning', true );
				}
			}
		} );

	// Events
	this.editOpenDialogButton.connect( this, { click: 'onEditOpenDialogButton' } );
	this.tdgDialog.connect( this, { apply: 'onDialogApply' } );

	this.$element
		.addClass( 'tdg-editscreen-main' )
		.append(
			this.editOpenDialogButton.$element,
			$helpLink,
			this.editNoticeLabel.$element
		);
};

/* Inheritance */

OO.inheritClass( mw.TemplateData.Target, OO.ui.Element );

OO.mixinClass( mw.TemplateData.Target, OO.EventEmitter );

/* Methods */

/**
 * Destroy the target
 */
mw.TemplateData.Target.prototype.destroy = function () {
	this.windowManager.destroy();
	this.$element.remove();
};

/**
 * Display error message in the edit window
 *
 * @method setNoticeMessage
 * @param {string} msg Message to display
 * @param {string} [type='error'] Message type 'notice' or 'warning' or 'error'
 * @param {boolean} [parseHTML] The message should be parsed
 */
mw.TemplateData.Target.prototype.setNoticeMessage = function ( msg, type, parseHTML ) {
	var $msg;
	type = type || 'error';
	this.editNoticeLabel.$element
		.toggleClass( 'errorbox', type === 'error' )
		.toggleClass( 'warningbox', type === 'warning' );

	if ( parseHTML ) {
		// OOUI's label elements do not parse strings and display them
		// as-is. If the message contains html that should be parsed,
		// we have to transform it into a jQuery object
		$msg = $( '<span>' ).append( $.parseHTML( msg ) );
		this.editNoticeLabel.setLabel( $msg );
	} else {
		this.editNoticeLabel.setLabel( msg );
	}
	this.editNoticeLabel.toggle( true );
};

/**
 * Reset the error message in the edit window
 *
 * @method resetNoticeMessage
 */
mw.TemplateData.Target.prototype.resetNoticeMessage = function () {
	this.editNoticeLabel.setLabel( '' );
	this.editNoticeLabel.toggle( false );
};

/**
 * Open the templatedata edit dialog
 *
 * @method openEditDialog
 * @param {mw.TemplateData.Model} dataModel The data model
 * associated with this edit dialog.
 */
mw.TemplateData.Target.prototype.openEditDialog = function ( dataModel ) {
	// Open the edit dialog
	this.windowManager.openWindow( 'TemplateDataDialog', {
		model: dataModel
	} );
};

/**
 * Respond to edit dialog button click.
 *
 * @method onEditOpenDialogButton
 */
mw.TemplateData.Target.prototype.onEditOpenDialogButton = function () {
	var target = this;

	// Reset notice message
	this.resetNoticeMessage();

	this.originalWikitext = this.$textarea.textSelection( 'getContents' );

	// Build the model
	this.sourceHandler.buildModel( this.originalWikitext )
		.then(
			// Success
			function ( model ) {
				target.openEditDialog( model );
			},
			// Failure
			function () {
				// Open a message dialog
				OO.ui.getWindowManager().openWindow( 'message', {
					title: mw.msg( 'templatedata-modal-title' ),
					message: mw.msg( 'templatedata-errormsg-jsonbadformat' ),
					verbose: true,
					actions: [
						{
							action: 'accept',
							label: mw.msg( 'templatedata-modal-json-error-replace' ),
							flags: [ 'primary', 'destructive' ]
						},
						{
							action: 'reject',
							label: OO.ui.deferMsg( 'ooui-dialog-message-reject' ),
							flags: 'safe'
						}
					]
				} ).closed.then( function ( data ) {
					var model;
					if ( data && data.action === 'accept' ) {
						// Open the dialog with an empty model
						model = mw.TemplateData.Model.static.newFromObject(
							{ params: {} },
							target.sourceHandler.getTemplateSourceCodeParams()
						);
						target.openEditDialog( model );
					}
				} );
			}
		);
};

/**
 * Replace the old templatedata string with the new one, or
 * insert the new one into the page if an old one doesn't exist
 *
 * @method replaceTemplateData
 * @param {Object} newTemplateData New templatedata
 */
mw.TemplateData.Target.prototype.replaceTemplateData = function ( newTemplateData ) {
	var matches, templateDataOutput,
		templateDataJSON = JSON.stringify( newTemplateData, null, '\t' ),
		templatedataPattern = /(<templatedata>\s*)([\s\S]*?)\s*<\/templatedata>/i;

	if ( ( matches = this.originalWikitext.match( templatedataPattern ) ) ) {
		// Move cursor to select withing existing <templatedata> and whitespace
		this.$textarea.textSelection( 'setSelection', {
			start: matches.index + matches[ 1 ].length,
			end: matches.index + matches[ 1 ].length + matches[ 2 ].length
		} );
		templateDataOutput = templateDataJSON;
	} else {
		this.$textarea.textSelection( 'setSelection', { start: this.originalWikitext.length } );

		templateDataOutput = '<templatedata>\n' + templateDataJSON + '\n</templatedata>';

		if ( !this.isPageSubLevel ) {
			if ( ( matches = this.originalWikitext.match( /<\/noinclude>\s*$/ ) ) ) {
				// Move cursor inside </noinclude>
				this.$textarea.textSelection( 'setSelection', { start: matches.index } );
			} else {
				// Wrap in new <noinclude>s
				templateDataOutput = '<noinclude>\n' + templateDataOutput + '\n</noinclude>\n';
			}
		}

		if ( this.originalWikitext.substr( -1 ) !== '\n' ) {
			templateDataOutput = '\n' + templateDataOutput;
		}
	}
	this.$textarea.textSelection( 'replaceSelection', templateDataOutput );
};

/**
 * Respond to edit dialog apply event
 *
 * @method onDialogApply
 * @param {Object} templateData New templatedata
 */
mw.TemplateData.Target.prototype.onDialogApply = function ( templateData ) {
	var target = this;

	if (
		Object.keys( templateData ).length > 1 ||
		Object.keys( templateData.params ).length > 0
	) {
		this.replaceTemplateData( templateData );
	} else {
		this.windowManager.closeWindow( this.windowManager.getCurrentWindow() );
		OO.ui.getWindowManager().openWindow( 'message', {
			title: mw.msg( 'templatedata-modal-title' ),
			message: mw.msg( 'templatedata-errormsg-insertblank' ),
			actions: [
				{
					label: mw.msg( 'templatedata-modal-button-cancel' ),
					flags: [ 'primary', 'safe' ]
				},
				{
					action: 'apply',
					label: mw.msg( 'templatedata-modal-button-apply' )
				}
			]
		} ).closed.then( function ( data ) {
			if ( data && data.action === 'apply' ) {
				target.replaceTemplateData( templateData );
			}
		} );
	}
};
