/*!
 * VisualEditor UserInterface MWSyntaxHighlightWindow class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki syntax highlight window.
 *
 * @class
 * @abstract
 *
 * @constructor
 */
ve.ui.MWSyntaxHighlightWindow = function VeUiMWSyntaxHighlightWindow() {
};

/* Inheritance */

OO.initClass( ve.ui.MWSyntaxHighlightWindow );

/* Static properties */

ve.ui.MWSyntaxHighlightWindow.static.icon = 'alienextension';

ve.ui.MWSyntaxHighlightWindow.static.title = OO.ui.deferMsg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-title' );

ve.ui.MWSyntaxHighlightWindow.static.dir = 'ltr';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWSyntaxHighlightWindow.prototype.initialize = function () {
	var noneMsg = ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-none' );

	this.language = new OO.ui.ComboBoxInputWidget( {
		$overlay: this.$overlay,
		menu: {
			filterFromInput: true,
			items: $.map( ve.dm.MWSyntaxHighlightNode.static.getLanguages(), function ( lang ) {
				return new OO.ui.MenuOptionWidget( { data: lang, label: lang || noneMsg } );
			} )
		},
		validate: function ( input ) {
			return ve.dm.MWSyntaxHighlightNode.static.isLanguageSupported( input );
		}
	} );

	this.showLinesCheckbox = new OO.ui.CheckboxInputWidget();

	this.startLineNumber = new OO.ui.NumberInputWidget( {
		min: 0,
		isInteger: true
	} );

	// Events
	this.language.connect( this, { change: 'onLanguageInputChange' } );
	this.showLinesCheckbox.connect( this, { change: 'onShowLinesCheckboxChange' } );
	this.startLineNumber.connect( this, { change: 'onStartLineNumberChange' } );

	this.languageField = new OO.ui.FieldLayout( this.language, {
		classes: [ 've-ui-mwSyntaxHighlightWindow-languageField' ],
		align: 'top',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-language' )
	} );
	this.codeField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-code' )
	} );
	this.showLinesField = new OO.ui.FieldLayout( this.showLinesCheckbox, {
		align: 'inline',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-showlines' )
	} );
	this.startLineField = new OO.ui.FieldLayout( this.startLineNumber, {
		classes: [ 've-ui-mwSyntaxHighlightWindow-startLineField' ],
		align: 'left',
		label: ve.msg( 'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-startingline' )
	} );
};

/**
 * Handle input change events
 *
 * @param {string} value New value
 */
ve.ui.MWSyntaxHighlightWindow.prototype.onLanguageInputChange = function () {
	var validity, inspector = this;
	validity = this.language.getValidity();
	validity.always( function () {
		inspector.getActions().setAbilities( { done: validity.state() === 'resolved' } );
	} );
};

/**
 * Handle change events from the show lines chechbox
 *
 * @param {boolean} value Widget value
 */
ve.ui.MWSyntaxHighlightWindow.prototype.onShowLinesCheckboxChange = function () {
	var showLines = this.showLinesCheckbox.isSelected();
	this.input.toggleLineNumbers( showLines );
	this.startLineNumber.setDisabled( !showLines );
};

/**
 * Handle change events from the start line input
 *
 * @param {string} value Widget value
 */
ve.ui.MWSyntaxHighlightWindow.prototype.onStartLineNumberChange = function ( value ) {
	var input = this.input;

	input.loadingPromise.done( function () {
		input.editor.setOption( 'firstLineNumber', value !== '' ? +value : 1 );
	} );
};

/**
 * @inheritdoc OO.ui.Window
 */
ve.ui.MWSyntaxHighlightWindow.prototype.getReadyProcess = function ( data, process ) {
	return process.next( function () {
		this.language.getMenu().toggle( false );
		if ( !this.language.getValue() ) {
			this.language.focus();
		} else {
			this.input.focus();
		}
	}, this );
};

/**
 * @inheritdoc OO.ui.Window
 */
ve.ui.MWSyntaxHighlightWindow.prototype.getSetupProcess = function ( data, process ) {
	return process.next( function () {
		var attrs = this.selectedNode ? this.selectedNode.getAttribute( 'mw' ).attrs : {},
			language = attrs.lang || '',
			showLines = attrs.line !== undefined,
			startLine = attrs.start;

		this.language.setValue( language );

		this.showLinesCheckbox.setSelected( showLines );
		this.startLineNumber.setValue( startLine );
	}, this );
};

/**
 * @inheritdoc OO.ui.Window
 */
ve.ui.MWSyntaxHighlightWindow.prototype.getTeardownProcess = function ( data, process ) {
	return process;
};

/**
 * @inheritdoc ve.ui.MWExtensionWindow
 */
ve.ui.MWSyntaxHighlightWindow.prototype.updateMwData = function ( mwData ) {
	var language = this.language.getValue(),
		showLines = this.showLinesCheckbox.isSelected(),
		startLine = this.startLineNumber.getValue();

	mwData.attrs.lang = language || undefined;
	mwData.attrs.line = showLines ? '1' : undefined;
	mwData.attrs.start = startLine !== '' ? startLine : undefined;
};
