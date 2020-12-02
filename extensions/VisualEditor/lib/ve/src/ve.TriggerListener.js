/*!
 * VisualEditor UserInterface TriggerListener class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Trigger listener
 *
 * @class
 *
 * @constructor
 * @param {string[]} commands Commands to listen to triggers for
 * @param {ve.ui.CommandRegistry} commandRegistry Command registry to get commands from
 */
ve.TriggerListener = function VeTriggerListener( commands, commandRegistry ) {
	var i, j, command, triggers;

	// Properties
	this.commands = commands;
	this.commandsByTrigger = {};
	this.triggers = {};

	for ( i = this.commands.length - 1; i >= 0; i-- ) {
		command = this.commands[ i ];
		triggers = ve.ui.triggerRegistry.lookup( command );
		if ( triggers ) {
			for ( j = triggers.length - 1; j >= 0; j-- ) {
				this.commandsByTrigger[ triggers[ j ].toString() ] = commandRegistry.lookup( command );
			}
			this.triggers[ command ] = triggers;
		}
	}
};

/* Inheritance */

OO.initClass( ve.TriggerListener );

/* Methods */

/**
 * Get list of commands.
 *
 * @return {string[]} Commands
 */
ve.TriggerListener.prototype.getCommands = function () {
	return this.commands;
};

/**
 * Get command associated with trigger string.
 *
 * @param {string} trigger Trigger string
 * @return {ve.ui.Command|undefined} Command
 */
ve.TriggerListener.prototype.getCommandByTrigger = function ( trigger ) {
	return this.commandsByTrigger[ trigger ];
};

/**
 * Get triggers for a specified name.
 *
 * @param {string} name Trigger name
 * @return {ve.ui.Trigger[]|undefined} Triggers
 */
ve.TriggerListener.prototype.getTriggers = function ( name ) {
	return this.triggers[ name ];
};
