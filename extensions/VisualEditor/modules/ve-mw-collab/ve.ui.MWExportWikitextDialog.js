/*!
 * VisualEditor UserInterface MWExportWikitextDialog class.
 *
 * @copyright 2011-2017 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for exportWikitexting CollabTarget pages
 *
 * @class
 * @extends OO.ui.ProcessDialog
 *
 * @constructor
 * @param {Object} [config] Config options
 */
ve.ui.MWExportWikitextDialog = function VeUiMwExportWikitextDialog( config ) {
	// Parent constructor
	ve.ui.MWExportWikitextDialog.super.call( this, config );

	// Initialization
	this.$element.addClass( 've-ui-mwExportWikitextDialog' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWExportWikitextDialog, OO.ui.ProcessDialog );

/* Static Properties */

ve.ui.MWExportWikitextDialog.static.name = 'mwExportWikitext';

ve.ui.MWExportWikitextDialog.static.title = ve.msg( 'visualeditor-rebase-client-export' );

ve.ui.MWExportWikitextDialog.static.actions = [
	{
		label: OO.ui.deferMsg( 'visualeditor-dialog-action-done' ),
		flags: 'safe'
	}
];

ve.ui.MWExportWikitextDialog.static.size = 'larger';

/**
 * @inheritdoc
 */
ve.ui.MWExportWikitextDialog.prototype.initialize = function () {
	var panel,
		$content = $( '<div>' );

	// Parent method
	ve.ui.MWExportWikitextDialog.super.prototype.initialize.call( this );

	this.titleInput = new mw.widgets.TitleInputWidget( {
		value: ve.init.target.getImportTitle()
	}, { api: ve.init.target.getContentApi() } );
	this.titleButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'visualeditor-rebase-client-export' ),
		flags: [ 'primary', 'progressive' ]
	} );
	this.titleField = new OO.ui.ActionFieldLayout( this.titleInput, this.titleButton, {
		align: 'top',
		label: ve.msg( 'visualeditor-rebase-client-import-name' )
	} );

	this.titleButton.on( 'click', this.export.bind( this ) );

	this.wikitext = new OO.ui.MultilineTextInputWidget( {
		// The following classes are used here:
		// * mw-editfont-monospace
		// * mw-editfont-sans-serif
		// * mw-editfont-serif
		classes: [ 'mw-editfont-' + mw.user.options.get( 'editfont' ) ],
		autosize: true,
		readOnly: true,
		rows: 20
	} );
	this.wikitextField = new OO.ui.FieldLayout( this.wikitext, {
		align: 'top',
		label: ve.msg( 'visualeditor-savedialog-review-wikitext' )
	} );

	// Move to CSS
	this.titleField.$element.css( 'max-width', 'none' );
	this.titleInput.$element.css( 'max-width', 'none' );
	this.wikitext.$element.css( 'max-width', 'none' );

	$content.append(
		this.titleField.$element,
		this.wikitextField.$element
	);

	panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false,
		$content: $content
	} );
	this.$body.append( panel.$element );
};

/**
 * @inheritdoc
 */
ve.ui.MWExportWikitextDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWExportWikitextDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var dialog = this,
				surface = ve.init.target.getSurface();
			this.titleButton.setDisabled( true );
			this.wikitext.pushPending();
			ve.init.target.getWikitextFragment( surface.getModel().getDocument() ).then( function ( wikitext ) {
				dialog.wikitext.setValue( wikitext.trim() );
				dialog.wikitext.$input.scrollTop( 0 );
				dialog.wikitext.popPending();
				dialog.titleButton.setDisabled( false );
				dialog.updateSize();
			}, function () {
				// TODO: Display API errors
				dialog.wikitext.popPending();
			} );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWExportWikitextDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWExportWikitextDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			this.titleInput.focus();
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWExportWikitextDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWExportWikitextDialog.super.prototype.getTeardownProcess.call( this, data )
		.next( function () {
			this.wikitext.setValue( '' );
		}, this );
};

/**
 * Export the document to a specific title
 */
ve.ui.MWExportWikitextDialog.prototype.export = function () {
	var key, $form, params,
		wikitext = this.wikitext.getValue(),
		title = this.titleInput.getMWTitle(),
		submitUrl = ( new mw.Uri( title.getUrl() ) )
			.extend( {
				action: 'submit',
				veswitched: 1
			} );

	$form = $( '<form>' ).attr( { method: 'post', enctype: 'multipart/form-data' } ).addClass( 'oo-ui-element-hidden' );
	params = {
		format: 'text/x-wiki',
		model: 'wikitext',
		wpTextbox1: wikitext,
		wpEditToken: mw.user.tokens.get( 'csrfToken' ),
		// MediaWiki function-verification parameters, mostly relevant to the
		// classic editpage, but still required here:
		wpUnicodeCheck: 'ℳ𝒲♥𝓊𝓃𝒾𝒸ℴ𝒹ℯ',
		wpUltimateParam: true,
		wpDiff: true
	};
	if ( ve.init.target.getImportTitle().toString() === title.toString() ) {
		params = ve.extendObject( {
			oldid: ve.init.target.revid,
			basetimestamp: ve.init.target.baseTimeStamp,
			starttimestamp: ve.init.target.startTimeStamp
		}, params );
	}
	// Add params as hidden fields
	for ( key in params ) {
		$form.append( $( '<input>' ).attr( { type: 'hidden', name: key, value: params[ key ] } ) );
	}
	// Submit the form, mimicking a traditional edit
	// Firefox requires the form to be attached
	$form.attr( 'action', submitUrl ).appendTo( 'body' ).trigger( 'submit' );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWExportWikitextDialog );
