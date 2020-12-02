/*!
 * VisualEditor UserInterface MergeCellsCommand class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * UserInterface merge cells command.
 *
 * @class
 * @extends ve.ui.Command
 *
 * @constructor
 */
ve.ui.MergeCellsCommand = function VeUiMergeCellsCommand() {
	// Parent constructor
	ve.ui.MergeCellsCommand.super.call(
		this, 'mergeCells', 'table', 'mergeCells',
		{ supportedSelections: [ 'table' ] }
	);
};

/* Inheritance */

OO.inheritClass( ve.ui.MergeCellsCommand, ve.ui.Command );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MergeCellsCommand.prototype.isExecutable = function ( fragment ) {
	// Parent method
	return ve.ui.MergeCellsCommand.super.prototype.isExecutable.apply( this, arguments ) &&
		fragment.getSelection().isMergeable( fragment.getDocument() );
};

/* Registration */

ve.ui.commandRegistry.register( new ve.ui.MergeCellsCommand() );
