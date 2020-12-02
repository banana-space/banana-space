/*!
 * VisualEditor UserInterface Command class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Command that executes an action.
 *
 * @class
 *
 * @constructor
 * @param {string} name Symbolic name for the command
 * @param {string} action Action to execute when command is triggered
 * @param {string} method Method to call on action when executing
 * @param {Object} [options] Command options
 * @param {string[]|null} [options.supportedSelections] List of supported selection types, or null for all
 * @param {Array} [options.args] Additional arguments to pass to the action when executing
 */
ve.ui.Command = function VeUiCommand( name, action, method, options ) {
	options = options || {};
	this.name = name;
	this.action = action;
	this.method = method;
	this.supportedSelections = options.supportedSelections || null;
	this.args = options.args || [];
};

/* Methods */

/**
 * Execute command on a surface.
 *
 * @param {ve.ui.Surface} surface Surface to execute command on
 * @param {Object} [args] Custom arguments to override defaults
 * @param {string} [source] Label for the source of the command, for tracking
 * @return {boolean} Command was executed
 */
ve.ui.Command.prototype.execute = function ( surface, args, source ) {
	var action = this.action;
	args = args || this.args;
	if ( this.isExecutable( surface.getModel().getFragment() ) ) {
		// We want to log things that are functionally a window being opened. We
		// rely on the convention of the method being "open", regardless of what the
		// action is. (Commonly window, but overridden in places.)
		if ( this.method === 'open' ) {
			if ( typeof args[ 0 ] === 'string' ) {
				action = args[ 0 ];
			}
			ve.track(
				'activity.' + action,
				{ action: 'window-open-from-' + ( source || 'command' ) }
			);
		}
		return surface.execute.apply( surface, [ this.action, this.method ].concat( args ) );
	} else {
		return false;
	}
};

/**
 * Check if this command is executable on a given surface fragment
 *
 * @param {ve.dm.SurfaceFragment} fragment Surface fragment
 * @return {boolean} The command can execute on this fragment
 */
ve.ui.Command.prototype.isExecutable = function ( fragment ) {
	return !this.supportedSelections ||
		this.supportedSelections.indexOf( fragment.getSelection().getName() ) !== -1;
};

/**
 * Get command action.
 *
 * @return {string} action Action to execute when command is triggered
 */
ve.ui.Command.prototype.getAction = function () {
	return this.action;
};

/**
 * Get command method.
 *
 * @return {string} method Method to call on action when executing
 */
ve.ui.Command.prototype.getMethod = function () {
	return this.method;
};

/**
 * Get command name.
 *
 * @return {string} name The symbolic name of the command.
 */
ve.ui.Command.prototype.getName = function () {
	return this.name;
};

/**
 * Get command arguments.
 *
 * @return {Array} args Additional arguments to pass to the action when executing
 */
ve.ui.Command.prototype.getArgs = function () {
	return this.args;
};
