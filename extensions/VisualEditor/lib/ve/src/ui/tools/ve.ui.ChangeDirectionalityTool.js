/*!
 * VisualEditor UserInterface ChangeDirectionalityTool class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * UserInterface change view directionality tool.
 *
 * @class
 * @extends ve.ui.Tool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.ChangeDirectionalityTool = function VeUiChangeDirectionalityTool() {
	// Parent constructor
	ve.ui.ChangeDirectionalityTool.super.apply( this, arguments );

	this.modelDir = null;

	this.setDisabled( false );
};

/* Inheritance */

OO.inheritClass( ve.ui.ChangeDirectionalityTool, ve.ui.Tool );

/* Static Properties */

ve.ui.ChangeDirectionalityTool.static.name = 'changeDirectionality';

ve.ui.ChangeDirectionalityTool.static.icon = 'textDirRTL';

ve.ui.ChangeDirectionalityTool.static.title =
	OO.ui.deferMsg( 'visualeditor-changedir-rtl' );

ve.ui.ChangeDirectionalityTool.static.autoAddToCatchall = false;

ve.ui.ChangeDirectionalityTool.static.commandName = 'changeDirectionality';

ve.ui.ChangeDirectionalityTool.static.deactivateOnSelect = false;

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.ChangeDirectionalityTool.prototype.onUpdateState = function ( fragment ) {
	var viewDir = this.toolbar.getSurface().getView().getDocument().getDir(),
		modelDir = fragment.getDocument().getDir();

	// Parent method
	ve.ui.ChangeDirectionalityTool.super.prototype.onUpdateState.apply( this, arguments );

	if ( modelDir !== this.modelDir ) {
		// Icons used here textDirLTR, textDirRTL
		this.setIcon( 'textDir' + ( modelDir === 'ltr' ? 'RTL' : 'LTR' ) );
		// The following messages are used here:
		// * visualeditor-changedir-tool-ltr
		// * visualeditor-changedir-tool-rtl
		this.setTitle( ve.msg( 'visualeditor-changedir-tool-' + ( modelDir === 'ltr' ? 'rtl' : 'ltr' ) ) );
		this.modelDir = modelDir;
	}

	this.setActive( viewDir !== modelDir );
};

/* Registration */

ve.ui.toolFactory.register( ve.ui.ChangeDirectionalityTool );
