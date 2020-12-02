/*!
 * VisualEditor user interface MWWikitextConvertConfirmDialog class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for letting the user choose whether to convert probable-wikitext
 *
 * @class
 * @extends OO.ui.MessageDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWWikitextConvertConfirmDialog = function VeUiMWWikitextConvertConfirmDialog( config ) {
	// Parent constructor
	ve.ui.MWWikitextConvertConfirmDialog.super.call( this, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWWikitextConvertConfirmDialog, OO.ui.MessageDialog );

/* Methods */

ve.ui.MWWikitextConvertConfirmDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWWikitextConvertConfirmDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.deferred = data.deferred;
		}, this );
};

ve.ui.MWWikitextConvertConfirmDialog.prototype.getActionProcess = function ( action ) {
	return new OO.ui.Process( function () {
		this.deferred.resolve( action === 'plain' );
		this.close( { action: action } );
	}, this );
};

/* Static Properties */

ve.ui.MWWikitextConvertConfirmDialog.static.name = 'wikitextconvertconfirm';

ve.ui.MWWikitextConvertConfirmDialog.static.title =
	OO.ui.deferMsg( 'visualeditor-wikitextconvert-title' );

ve.ui.MWWikitextConvertConfirmDialog.static.message =
	OO.ui.deferMsg( 'visualeditor-wikitextconvert-message' );

ve.ui.MWWikitextConvertConfirmDialog.static.actions = [
	{ action: 'convert', label: OO.ui.deferMsg( 'visualeditor-wikitextconvert-convert' ) },
	{ action: 'plain', label: OO.ui.deferMsg( 'visualeditor-wikitextconvert-plain' ), flags: [ 'primary', 'progressive' ] }
];

ve.ui.MWWikitextConvertConfirmDialog.static.escapable = false;

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWWikitextConvertConfirmDialog );
