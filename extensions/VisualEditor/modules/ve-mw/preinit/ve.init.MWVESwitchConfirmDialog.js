/*!
 * VisualEditor user interface MWVESwitchConfirmDialog class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

mw.libs.ve = mw.libs.ve || {};
/**
 * Dialog for letting the user choose how to switch to wikitext mode.
 *
 * @class
 * @extends OO.ui.MessageDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
mw.libs.ve.SwitchConfirmDialog = function MWLibsVESwitchConfirmDialog( config ) {
	// Parent constructor
	mw.libs.ve.SwitchConfirmDialog.super.call( this, config );
};

/* Inheritance */

OO.inheritClass( mw.libs.ve.SwitchConfirmDialog, OO.ui.MessageDialog );

/* Static Properties */

mw.libs.ve.SwitchConfirmDialog.static.name = 'veswitchconfirm';

mw.libs.ve.SwitchConfirmDialog.static.title =
	mw.msg( 'visualeditor-mweditmodeve-title' );

mw.libs.ve.SwitchConfirmDialog.static.message =
	mw.msg( 'visualeditor-mweditmodeve-warning' );

mw.libs.ve.SwitchConfirmDialog.static.actions = [
	{
		action: 'cancel',
		label: mw.msg( 'visualeditor-mweditmodesource-warning-cancel' ),
		flags: [ 'safe', 'back' ]
	},
	{
		action: 'discard',
		label: mw.msg( 'visualeditor-mweditmodesource-warning-switch-discard' ),
		flags: 'destructive'
	}
];

/* Methods */

/**
 * @inheritdoc
 */
mw.libs.ve.SwitchConfirmDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'discard' ) {
		return new OO.ui.Process( function () {
			this.getActions()
				.setAbilities( { cancel: false } )
				.get( { actions: 'discard' } )[ 0 ].pushPending();
			this.close( { action: 'discard' } );
		}, this );
	} else if ( action === 'cancel' ) {
		return new OO.ui.Process( function () {
			this.close( { action: 'cancel' } );
		}, this );
	}

	// Parent method
	return mw.libs.ve.SwitchConfirmDialog.super.prototype.getActionProcess.call( this, action );
};
