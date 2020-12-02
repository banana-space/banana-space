/**
 * MediaWiki save tool
 *
 * @class
 * @abstract
 * @extends ve.ui.Tool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.MWSaveTool = function VeUiMWSaveTool() {
	// Parent constructor
	ve.ui.MWSaveTool.super.apply( this, arguments );

	// NOTE (phuedx, 2014-08-20): This class is used by the firsteditve guided
	// tour to attach a guider to the "Save page" button.
	this.$link.addClass( 've-ui-toolbar-saveButton' );

	if ( ve.msg( 'accesskey-save' ) !== '-' && ve.msg( 'accesskey-save' ) !== '' ) {
		// FlaggedRevs tries to use this - it's useless on VE pages because all that stuff gets hidden, but it will still conflict so get rid of it
		ve.init.target.$saveAccessKeyElements = $( '[accesskey="' + ve.msg( 'accesskey-save' ) + '"]' ).removeAttr( 'accesskey' );
		this.$link.attr( 'accesskey', ve.msg( 'accesskey-save' ) );
	}

	this.setTitle( ve.init.target.getSaveButtonLabel( true ) );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWSaveTool, ve.ui.Tool );

/* Static properties */

ve.ui.MWSaveTool.static.name = 'showSave';
ve.ui.MWSaveTool.static.flags = [ 'primary', 'progressive' ];
ve.ui.MWSaveTool.static.displayBothIconAndLabel = true;
ve.ui.MWSaveTool.static.group = 'save';
ve.ui.MWSaveTool.static.commandName = 'showSave';
ve.ui.MWSaveTool.static.autoAddToCatchall = false;
ve.ui.MWSaveTool.static.autoAddToGroup = false;

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWSaveTool.prototype.onUpdateState = function () {
	var wasSaveable = !this.isDisabled(),
		isSaveable = ve.init.target && ve.init.target.isSaveable();

	// This could be a ve.ui.WindowTool that becomes active
	// when the save dialog is open, but onUpdateState is
	// called without arguments in ArticleTarget for speed.

	if ( wasSaveable !== isSaveable ) {
		this.setDisabled( !isSaveable );
		mw.hook( 've.toolbarSaveButton.stateChanged' ).fire( !isSaveable );
	}
};

/* Registration */

ve.ui.toolFactory.register( ve.ui.MWSaveTool );
