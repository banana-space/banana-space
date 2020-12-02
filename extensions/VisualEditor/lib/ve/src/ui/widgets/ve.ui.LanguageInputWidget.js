/*!
 * VisualEditor UserInterface LanguageInputWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Creates an ve.ui.LanguageInputWidget object.
 *
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @cfg {string} [dirInput='auto'] How to display the directionality input. Options are:
 *      - none: Directionality input is hidden.
 *      - no-auto: Directionality input is visible and options are LTR or RTL.
 *      - auto: Directionality input is visible and options include "auto" in
 *            addition to LTR and RTL.
 * @cfg {boolean} [readOnly=false] Prevent changes to the value of the input.
 * @cfg {boolean} [hideCodeInput] Prevent user from entering a language code as free text
 * @cfg {ve.ui.WindowManager} [dialogManager] Window manager to launch the language search dialog in
 * @cfg {string[]} [availableLanguages] Available language codes to show in search dialog
 */
ve.ui.LanguageInputWidget = function VeUiLanguageInputWidget( config ) {
	var dirItems, dirInput,
		$language = $( '<div>' ).addClass( 've-ui-languageInputWidget-languageInput' );

	// Configuration initialization
	config = config || {};
	dirInput = ( config.dirInput === undefined ) ? 'auto' : config.dirInput;

	// Parent constructor
	ve.ui.LanguageInputWidget.super.call( this, config );

	// Properties
	this.lang = null;
	this.dir = null;
	this.setReadOnly( !!config.readOnly );

	this.overlay = new ve.ui.Overlay( { classes: [ 've-ui-overlay-global' ] } );
	this.dialogs = config.dialogManager || new ve.ui.WindowManager( { factory: ve.ui.windowFactory } );
	this.availableLanguages = config.availableLanguages;

	this.findLanguageButton = new OO.ui.ButtonWidget( {
		classes: [ 've-ui-languageInputWidget-findLanguageButton' ],
		icon: 'ellipsis'
	} );
	this.selectedLanguageLabel = new OO.ui.LabelWidget( {
		classes: [ 've-ui-languageInputWidget-selectedLanguageLabel' ],
		label: ve.msg( 'visualeditor-languageinspector-widget-changelang' )
	} );
	this.languageCodeTextInput = new OO.ui.TextInputWidget( {
		classes: [ 've-ui-languageInputWidget-languageCodeTextInput' ]
	} );
	this.directionSelect = new OO.ui.ButtonSelectWidget( {
		classes: [ 've-ui-languageInputWidget-directionSelect' ]
	} );
	this.directionLabel = new OO.ui.LabelWidget( {
		classes: [ 've-ui-languageInputWidget-directionLabel' ],
		label: ve.msg( 'visualeditor-languageinspector-widget-label-direction' )
	} );

	$language.append(
		this.findLanguageButton.$element
	);
	if ( !config.hideCodeInput ) {
		$language.prepend( this.languageCodeTextInput.$element );
	}
	this.findLanguageButton.$element.before( this.selectedLanguageLabel.$element );

	// Events
	this.findLanguageButton.connect( this, { click: 'onFindLanguageButtonClick' } );
	this.languageCodeTextInput.connect( this, { change: 'onChange' } );
	this.directionSelect.connect( this, { select: 'onChange' } );

	// Initialization
	dirItems = [
		new OO.ui.ButtonOptionWidget( {
			data: 'rtl',
			icon: 'textDirRTL'
		} ),
		new OO.ui.ButtonOptionWidget( {
			data: 'ltr',
			icon: 'textDirLTR'
		} )
	];
	if ( dirInput === 'auto' ) {
		dirItems.splice(
			1, 0, new OO.ui.ButtonOptionWidget( {
				data: null,
				label: ve.msg( 'visualeditor-dialog-language-auto-direction' )
			} )
		);
	}
	this.directionSelect.addItems( dirItems );
	this.overlay.$element.append( this.dialogs.$element );
	$( document.body ).append( this.overlay.$element );

	this.$element
		.addClass( 've-ui-languageInputWidget' )
		.append( $language );

	if ( dirInput !== 'none' ) {
		this.$element.append( this.directionLabel.$element, this.directionSelect.$element );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.LanguageInputWidget, OO.ui.Widget );

/* Events */

/**
 * @event change
 * @param {string} lang Language code
 * @param {string} dir Directionality
 */

/* Methods */

/**
 * Handle find language button click events.
 */
ve.ui.LanguageInputWidget.prototype.onFindLanguageButtonClick = function () {
	var widget = this;

	this.dialogs.openWindow( 'languageSearch', {
		availableLanguages: this.availableLanguages,
		$returnFocusTo: null
	} ).closing.then( function ( data ) {
		data = data || {};
		if ( data.action === 'done' ) {
			widget.setLangAndDir( data.lang, data.dir );
		}
	} );
};

/**
 * Handle input widget change events.
 */
ve.ui.LanguageInputWidget.prototype.onChange = function () {
	var selectedItem;

	if ( this.updating ) {
		return;
	}

	selectedItem = this.directionSelect.findSelectedItem();
	this.setLangAndDir(
		this.languageCodeTextInput.getValue(),
		selectedItem ? selectedItem.getData() : null
	);
};

/**
 * Set language and directionality
 *
 * The inputs value will automatically be updated.
 *
 * @param {string} lang Language code
 * @param {string} dir Directionality
 * @fires change
 * @return {ve.ui.LanguageInputWidget}
 * @chainable
 */
ve.ui.LanguageInputWidget.prototype.setLangAndDir = function ( lang, dir ) {
	if ( lang === this.lang && dir === this.dir ) {
		// No change
		return this;
	}

	// Set state flag while programmatically changing input widget values
	this.updating = true;
	if ( lang || dir ) {
		lang = lang || '';
		this.languageCodeTextInput.setValue( lang );
		this.selectedLanguageLabel.setLabel(
			ve.init.platform.getLanguageName( lang.toLowerCase() ) ||
			ve.msg( 'visualeditor-languageinspector-widget-changelang' )
		);
		this.directionSelect.selectItemByData( dir );
	} else {
		this.languageCodeTextInput.setValue( '' );
		this.selectedLanguageLabel.setLabel(
			ve.msg( 'visualeditor-languageinspector-widget-changelang' )
		);
		this.directionSelect.selectItem( null );
	}
	// Set title as long language may be truncated
	this.selectedLanguageLabel.setTitle( this.selectedLanguageLabel.$label.text() );
	this.updating = false;

	this.lang = lang;
	this.dir = dir;
	this.emit( 'change', lang, dir );
	return this;
};

/**
 * Get the language
 *
 * @return {string} Language code
 */
ve.ui.LanguageInputWidget.prototype.getLang = function () {
	return this.lang;
};

/**
 * Get the directionality
 *
 * @return {string} Directionality (ltr/rtl)
 */
ve.ui.LanguageInputWidget.prototype.getDir = function () {
	return this.dir;
};

/**
 * Update the disabled state of the controls
 *
 * @chainable
 * @protected
 * @return {OO.ui.NumberInputWidget} The widget, for chaining
 */
ve.ui.LanguageInputWidget.prototype.updateControlsDisabled = function () {
	var disabled = this.isDisabled() || this.isReadOnly();
	if ( this.findLanguageButton ) {
		this.findLanguageButton.setDisabled( disabled );
	}
	if ( this.directionSelect ) {
		this.directionSelect.setDisabled( disabled );
	}
	return this;
};

/**
 * Disable or enable the inputs
 *
 * @param {boolean} disabled Set disabled or enabled
 * @return {ve.ui.LanguageInputWidget}
 * @chainable
 */
ve.ui.LanguageInputWidget.prototype.setDisabled = function ( disabled ) {
	// Parent method
	ve.ui.LanguageInputWidget.super.prototype.setDisabled.call( this, disabled );

	// The 'setDisabled' method runs in the constructor before the
	// inputs are initialized
	if ( this.languageCodeTextInput ) {
		this.languageCodeTextInput.setDisabled( disabled );
	}
	this.updateControlsDisabled();
	return this;
};

/**
 * Check if the widget is read-only
 *
 * @return {boolean}
 */
ve.ui.LanguageInputWidget.prototype.isReadOnly = function () {
	return this.readOnly;
};

/**
 * Set the read-only state of the widget
 *
 * @param {boolean} readOnly Make widget read-only
 * @return {ve.ui.LanguageInputWidget}
 * @chainable
 */
ve.ui.LanguageInputWidget.prototype.setReadOnly = function ( readOnly ) {
	this.readOnly = readOnly;
	if ( this.languageCodeTextInput ) {
		this.languageCodeTextInput.setReadOnly( readOnly );
	}
	this.updateControlsDisabled();
	return this;
};
