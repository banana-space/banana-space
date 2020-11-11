/*!
 * VisualEditor UserInterface MediaWiki UseExistingReferenceCommand class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Use existing reference command.
 *
 * @class
 * @extends ve.ui.Command
 *
 * @constructor
 */
ve.ui.MWUseExistingReferenceCommand = function VeUiMWUseExistingReferenceCommand() {
	// Parent constructor
	ve.ui.MWUseExistingReferenceCommand.super.call(
		this, 'reference/existing', 'window', 'open',
		{ args: [ 'reference', { useExisting: true } ], supportedSelections: [ 'linear' ] }
	);
};

/* Inheritance */

OO.inheritClass( ve.ui.MWUseExistingReferenceCommand, ve.ui.Command );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWUseExistingReferenceCommand.prototype.isExecutable = function ( fragment ) {
	var groupName, groups;

	// Parent method
	if ( !ve.ui.MWUseExistingReferenceCommand.super.prototype.isExecutable.apply( this, arguments ) ) {
		return false;
	}

	groups = fragment.getDocument().getInternalList().getNodeGroups();

	for ( groupName in groups ) {
		if ( groupName.lastIndexOf( 'mwReference/' ) === 0 && groups[ groupName ].indexOrder.length ) {
			return true;
		}
	}
	return false;
};

/* Registration */

ve.ui.commandRegistry.register( new ve.ui.MWUseExistingReferenceCommand() );
