/*!
 * VisualEditor UserInterface ToolbarDialogWindowManager class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Window manager for toolbar dialogs.
 *
 * @class
 * @extends ve.ui.SurfaceWindowManager
 *
 * @constructor
 * @param {ve.ui.Surface} surface Surface this belongs to
 * @param {Object} [config] Configuration options
 * @cfg {ve.ui.Overlay} [overlay] Overlay to use for menus
 */
ve.ui.ToolbarDialogWindowManager = function VeUiToolbarDialogWindowManager( surface, config ) {
	// Parent constructor
	ve.ui.ToolbarDialogWindowManager.super.call( this, surface, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.ToolbarDialogWindowManager, ve.ui.SurfaceWindowManager );

/* Static Properties */

ve.ui.ToolbarDialogWindowManager.static.sizes = {
	full: {
		width: '100%',
		maxHeight: '100%'
	},
	small: {
		width: 150
	},
	medium: {
		width: 300
	},
	large: {
		width: 400
	}
};

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.ToolbarDialogWindowManager.prototype.getTeardownDelay = function () {
	return 250;
};
