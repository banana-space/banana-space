/*!
 * VisualEditor UserInterface ClearAnnotationCommand class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * UserInterface clear all annotations command.
 *
 * @class
 * @extends ve.ui.Command
 *
 * @constructor
 */
ve.ui.ClearAnnotationCommand = function VeUiClearAnnotationCommand() {
	// Parent constructor
	ve.ui.ClearAnnotationCommand.super.call(
		this, 'clear', 'annotation', 'clearAll',
		{ supportedSelections: [ 'linear', 'table' ] }
	);
};

/* Inheritance */

OO.inheritClass( ve.ui.ClearAnnotationCommand, ve.ui.Command );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.ClearAnnotationCommand.prototype.isExecutable = function ( fragment ) {
	// Parent method
	return ve.ui.ClearAnnotationCommand.super.prototype.isExecutable.apply( this, arguments ) &&
		fragment.hasAnnotations();
};

/* Registration */

ve.ui.commandRegistry.register( new ve.ui.ClearAnnotationCommand() );
