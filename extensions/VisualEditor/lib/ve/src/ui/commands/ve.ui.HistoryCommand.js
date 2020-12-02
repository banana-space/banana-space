/*!
 * VisualEditor UserInterface HistoryCommand class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * UserInterface history command.
 *
 * @class
 * @extends ve.ui.Command
 *
 * @constructor
 * @param {string} name
 * @param {string} [method]
 */
ve.ui.HistoryCommand = function VeUiHistoryCommand( name, method ) {
	method = method || name;

	// Parent constructor
	ve.ui.HistoryCommand.super.call( this, name, 'history', method );

	this.checkMethod = {
		undo: 'canUndo',
		redo: 'canRedo'
	}[ method ];
};

/* Inheritance */

OO.inheritClass( ve.ui.HistoryCommand, ve.ui.Command );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.HistoryCommand.prototype.isExecutable = function ( fragment ) {
	var surface = fragment.getSurface();

	// Parent method
	return ve.ui.HistoryCommand.super.prototype.isExecutable.apply( this, arguments ) &&
		surface[ this.checkMethod ]();
};

/* Registration */

ve.ui.commandRegistry.register( new ve.ui.HistoryCommand( 'undo' ) );

ve.ui.commandRegistry.register( new ve.ui.HistoryCommand( 'redo' ) );
