/*!
 * VisualEditor UserInterface CompletionAction class.
 *
 * @copyright 2011-2019 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Completion action.
 *
 * This is a very specialized action, which mostly exists to be a data-provider to a
 * completion widget. Completion-types *must* override getSuggestions.
 *
 * @class
 * @abstract
 * @extends ve.ui.Action
 *
 * @constructor
 * @param {ve.ui.Surface} surface Surface to act on
 */
ve.ui.CompletionAction = function VeUiCompletionAction() {
	// Parent constructor
	ve.ui.CompletionAction.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.CompletionAction, ve.ui.Action );

/* Static Properties */

/**
 * Length to which to limit the list of returned completions
 *
 * @static
 * @property {number}
 */
ve.ui.CompletionAction.static.defaultLimit = 8;

/**
 * Length of the trigger sequence for the action
 *
 * This many characters will be stripped from the start of the current input by
 * CompletionWidget.
 *
 * @static
 * @property {number}
 */
ve.ui.CompletionAction.static.triggerLength = 1;

/**
 * Whether the current input should be included as a completion automatically
 *
 * @static
 * @property {boolean}
 */
ve.ui.CompletionAction.static.alwaysIncludeInput = true;

ve.ui.CompletionAction.static.methods = [ 'open' ];

/* Methods */

/**
 * Show the completions
 *
 * @return {boolean} Action was executed
 */
ve.ui.CompletionAction.prototype.open = function () {
	this.surface.completion.setup( this );

	return true;
};

/**
 * Retrieve suggested completions for the given input
 *
 * @abstract
 * @param {string} input
 * @param {number} [limit=20] restrict number of results to this
 * @return {jQuery.Promise} promise that will return results
 */
ve.ui.CompletionAction.prototype.getSuggestions = null;

/**
 * Perform the insetion for the chosen suggestion
 *
 * @param {Object} data Whatever data was attached to the menu option widget
 * @param {ve.Range} range The range the widget is considering
 * @return {ve.dm.SurfaceFragment} The fragment containing the inserted content
 */
ve.ui.CompletionAction.prototype.insertCompletion = function ( data, range ) {
	return this.surface.getModel().getLinearFragment( range )
		.insertContent( data, true );
};

/**
 * Should the widget abandon trying to find matches given the current state?
 *
 * @param {string} input
 * @param {number} matches Number of matches before the input occurred
 * @return {boolean} Whether to abandon
 */
ve.ui.CompletionAction.prototype.shouldAbandon = function ( input, matches ) {
	// abandon after adding whitespace if there are no active potential matches
	return matches === ( this.constructor.static.alwaysIncludeInput ? 1 : 0 ) &&
		!!( input && input.match( /\s$/ ) );
};

// helpers

/**
 * Make a menu item for a given suggestion
 *
 * @protected
 * @param  {string} suggestion
 * @return {OO.ui.MenuOptionWidget}
 */
ve.ui.CompletionAction.prototype.getMenuItemForSuggestion = function ( suggestion ) {
	return new OO.ui.MenuOptionWidget( { data: suggestion, label: suggestion } );
};

/**
 * Filter a suggestion list based on the current input
 *
 * This is for an implementor who has fetched/gathered a list of potential
 * suggestions and needs to trim them down to a viable set to display as
 * completion options for a given input.
 *
 * It restricts the selection to only suggestions that start with the input,
 * shortens the list to the configured defaultLimit, and adds the current
 * input to the list if alwaysIncludeInput and there wasn't an exact match.
 *
 * @protected
 * @param  {string[]} suggestions List of strings of valid completions
 * @param  {string} input Input to filter the suggestions to
 * @return {string[]}
 */
ve.ui.CompletionAction.prototype.filterSuggestionsForInput = function ( suggestions, input ) {
	var exact = false,
		inputTrimmed = input.trim(),
		inputTrimmedLower = inputTrimmed.toLowerCase().trim();
	suggestions = suggestions.filter( function ( word ) {
		var wordLower = word.toLowerCase();
		exact = exact || wordLower === inputTrimmedLower;
		return wordLower.slice( 0, inputTrimmedLower.length ) === inputTrimmedLower;
	} );
	if ( this.constructor.static.defaultLimit < suggestions.length ) {
		suggestions.length = this.constructor.static.defaultLimit;
	}
	if ( !exact && this.constructor.static.alwaysIncludeInput && inputTrimmed.length ) {
		suggestions.push( inputTrimmed );
	}
	return suggestions;
};
