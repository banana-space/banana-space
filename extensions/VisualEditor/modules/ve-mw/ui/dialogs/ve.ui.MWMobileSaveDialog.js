/*!
 * VisualEditor UserInterface MWMobileSaveDialog class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for saving MediaWiki pages in mobile.
 *
 * TODO: Currently this does no overriding so could be removed, but we may want
 * to customise the mobile save dialog in the near future.
 *
 * @class
 * @extends ve.ui.MWSaveDialog
 *
 * @constructor
 * @param {Object} [config] Config options
 */
ve.ui.MWMobileSaveDialog = function VeUiMwMobileSaveDialog() {
	// Parent constructor
	ve.ui.MWMobileSaveDialog.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwMobileSaveDialog' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWMobileSaveDialog, ve.ui.MWSaveDialog );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWMobileSaveDialog.prototype.initialize = function () {
	var mobile, skin, licenseMsg;

	// Parent method
	ve.ui.MWMobileSaveDialog.super.prototype.initialize.call( this );

	this.$reviewVisualDiff.addClass( 'content' );

	if ( mw.mobileFrontend ) {
		mobile = mw.mobileFrontend.require( 'mobile.startup' );
		skin = mobile.Skin.getSingleton();
		licenseMsg = skin.getLicenseMsg();
		if ( licenseMsg ) {
			this.$license.html( licenseMsg );
		}
	}
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWMobileSaveDialog );
