/*!
 * VisualEditor UserInterface CommandHelpDialog class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Dialog for listing all command keyboard shortcuts.
 *
 * @class
 * @extends OO.ui.ProcessDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.CommandHelpDialog = function VeUiCommandHelpDialog( config ) {
	// Parent constructor
	ve.ui.CommandHelpDialog.super.call( this, config );
};

/* Inheritance */

OO.inheritClass( ve.ui.CommandHelpDialog, OO.ui.ProcessDialog );

/* Static Properties */

ve.ui.CommandHelpDialog.static.name = 'commandHelp';

ve.ui.CommandHelpDialog.static.size = 'larger';

ve.ui.CommandHelpDialog.static.title =
	OO.ui.deferMsg( 'visualeditor-dialog-command-help-title' );

ve.ui.CommandHelpDialog.static.actions = [
	{
		label: OO.ui.deferMsg( 'visualeditor-dialog-action-done' ),
		flags: 'safe'
	}
];

ve.ui.CommandHelpDialog.static.commandGroups = {
	textStyle: {
		title: OO.ui.deferMsg( 'visualeditor-shortcuts-text-style' ),
		promote: [ 'bold', 'italic', 'link' ],
		demote: [ 'clear' ]
	},
	clipboard: {
		title: OO.ui.deferMsg( 'visualeditor-shortcuts-clipboard' )
	},
	formatting: {
		title: OO.ui.deferMsg( 'visualeditor-shortcuts-formatting' ),
		promote: [ 'paragraph', 'pre', 'blockquote' ]
	},
	history: {
		title: OO.ui.deferMsg( 'visualeditor-shortcuts-history' ),
		promote: [ 'undo', 'redo' ]
	},
	dialog: {
		title: OO.ui.deferMsg( 'visualeditor-shortcuts-dialog' )
	},
	other: {
		title: OO.ui.deferMsg( 'visualeditor-shortcuts-other' ),
		promote: [ 'findAndReplace', 'findNext', 'findPrevious' ],
		demote: [ 'commandHelp' ]
	},
	insert: {
		title: OO.ui.deferMsg( 'visualeditor-shortcuts-insert' )
	}
};

ve.ui.CommandHelpDialog.static.commandGroupsOrder = [
	'textStyle', 'clipboard',
	'formatting', 'history',
	'dialog', 'other', 'insert'
];

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.CommandHelpDialog.prototype.getBodyHeight = function () {
	return Math.round( this.contentLayout.$element[ 0 ].scrollHeight );
};

/**
 * @inheritdoc
 */
ve.ui.CommandHelpDialog.prototype.initialize = function () {
	var i, iLen, j, jLen, k, kLen, triggerList, commands, shortcut,
		$list, $shortcut, groupName, commandGroup, commandGroups, commandGroupsOrder, sequence, hasCommand, hasShortcut,
		surface = ve.init.target.getSurface(),
		sequenceRegistry = surface.sequenceRegistry,
		commandRegistry = surface.commandRegistry;

	// Parent method
	ve.ui.CommandHelpDialog.super.prototype.initialize.call( this );

	commandGroups = this.constructor.static.commandGroups;
	commandGroupsOrder = this.constructor.static.commandGroupsOrder;

	this.contentLayout = new OO.ui.PanelLayout( {
		scrollable: true,
		padded: true,
		expanded: false
	} );
	this.$container = $( '<div>' ).addClass( 've-ui-commandHelpDialog-container' );

	for ( i = 0, iLen = commandGroupsOrder.length; i < iLen; i++ ) {
		hasCommand = false;
		groupName = commandGroupsOrder[ i ];
		commandGroup = commandGroups[ groupName ];
		commands = this.constructor.static.sortedCommandsFromGroup( groupName, commandGroup.promote, commandGroup.demote );
		$list = $( '<dl>' ).addClass( 've-ui-commandHelpDialog-list' );
		for ( j = 0, jLen = commands.length; j < jLen; j++ ) {
			if ( commands[ j ].trigger ) {
				if ( !commands[ j ].ignoreCommand && !commandRegistry.lookup( commands[ j ].trigger ) ) {
					// Trigger is specified by unavailable command
					continue;
				}
				triggerList = ve.ui.triggerRegistry.lookup( commands[ j ].trigger );
			} else {
				triggerList = [];
				if ( commands[ j ].shortcuts ) {
					for ( k = 0, kLen = commands[ j ].shortcuts.length; k < kLen; k++ ) {
						shortcut = commands[ j ].shortcuts[ k ];
						triggerList.push(
							new ve.ui.Trigger( shortcut, true )
						);
					}
				}
			}

			hasShortcut = false;

			$shortcut = $( '<dt>' );
			for ( k = 0, kLen = triggerList.length; k < kLen; k++ ) {
				$shortcut.append( $( '<kbd>' ).append(
					triggerList[ k ].getMessage( true ).map( this.constructor.static.buildKeyNode )
				).find( 'kbd + kbd' ).before( '+' ).end() );
				hasShortcut = true;
			}
			if ( commands[ j ].sequences ) {
				for ( k = 0, kLen = commands[ j ].sequences.length; k < kLen; k++ ) {
					sequence = sequenceRegistry.lookup( commands[ j ].sequences[ k ] );
					if ( sequence ) {
						$shortcut.append( $( '<kbd>' ).addClass( 've-ui-commandHelpDialog-sequence' )
							.attr( 'data-label', ve.msg( 'visualeditor-shortcuts-sequence-notice' ) )
							.append(
								sequence.getMessage( true ).map( this.constructor.static.buildKeyNode )
							)
						);
						hasShortcut = true;
					}
				}
			}
			if ( hasShortcut ) {
				$list.append(
					$shortcut,
					$( '<dd>' ).text( OO.ui.resolveMsg( commands[ j ].label ) )
				);
				hasCommand = true;
			}
		}
		if ( hasCommand ) {
			this.$container.append(
				$( '<div>' )
					.addClass( 've-ui-commandHelpDialog-section' )
					.append(
						$( '<h3>' ).text( OO.ui.resolveMsg( commandGroup.title ) ),
						$list
					)
			);
		}
	}

	this.contentLayout.$element.append( this.$container );
	this.$body.append( this.contentLayout.$element );
};

/* Static methods */

/**
 * Wrap a key (as provided by a Trigger) in a node, for display
 *
 * @static
 * @param {string} key Key to display
 * @return {jQuery} A kbd wrapping the key text
 */
ve.ui.CommandHelpDialog.static.buildKeyNode = function ( key ) {
	var $key = $( '<kbd>' );
	if ( key === ' ' ) {
		// Might need to expand this if other keys show up, but currently things like
		// the tab-character only come from Triggers and are pre-localized there into
		// "tab" anyway.
		key = ( new ve.ui.Trigger( 'space' ) ).getMessage();
		$key.addClass( 've-ui-commandHelpDialog-specialKey' );
	}
	return $key.text( key );
};

/**
 * Extract a properly sorted list of commands from a command-group
 *
 * @static
 * @param {string} groupName The dialog-category in which to display this
 * @param {string[]} [promote] Commands which should be displayed first
 * @param {string[]} [demote] Commands which should be displayed last
 * @return {Object[]} List of commands in order
 */
ve.ui.CommandHelpDialog.static.sortedCommandsFromGroup = function ( groupName, promote, demote ) {
	var i,
		commands = ve.ui.commandHelpRegistry.lookupByGroup( groupName ),
		keys = Object.keys( commands ),
		used = {},
		auto = [],
		promoted = [],
		demoted = [];
	keys.sort();
	if ( promote ) {
		for ( i = 0; i < promote.length; i++ ) {
			if ( !commands[ promote[ i ] ] ) {
				continue;
			}
			promoted.push( commands[ promote[ i ] ] );
			used[ promote[ i ] ] = true;
		}
	}
	if ( demote ) {
		for ( i = 0; i < demote.length; i++ ) {
			if ( used[ demote[ i ] ] || !commands[ demote[ i ] ] ) {
				continue;
			}
			demoted.push( commands[ demote[ i ] ] );
			used[ demote[ i ] ] = true;
		}
	}
	for ( i = 0; i < keys.length; i++ ) {
		if ( used[ keys[ i ] ] ) {
			continue;
		}
		auto.push( commands[ keys[ i ] ] );
	}
	return promoted.concat( auto, demoted );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.CommandHelpDialog );
