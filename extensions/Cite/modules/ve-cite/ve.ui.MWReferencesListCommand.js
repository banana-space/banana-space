/*!
 * VisualEditor UserInterface MediaWiki ReferencesListCommand class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * References list command.
 *
 * If a references list node is selected, opens the dialog to edit it.
 * Otherwise inserts the references list for the default group.
 *
 * @class
 * @extends ve.ui.Command
 *
 * @constructor
 */
ve.ui.MWReferencesListCommand = function VeUiMWReferencesListCommand() {
	// Parent constructor
	ve.ui.MWReferencesListCommand.super.call(
		this, 'referencesList', null, null,
		{ supportedSelections: [ 'linear' ] }
	);
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferencesListCommand, ve.ui.Command );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWReferencesListCommand.prototype.execute = function ( surface ) {
	var fragment = surface.getModel().getFragment(),
		selectedNode = fragment.getSelectedNode(),
		isReflistNodeSelected = selectedNode && selectedNode instanceof ve.dm.MWReferencesListNode;

	if ( isReflistNodeSelected ) {
		return surface.execute( 'window', 'open', 'referencesList' );
	} else {
		fragment.collapseToEnd().insertContent( [
			{
				type: 'mwReferencesList',
				attributes: {
					listGroup: 'mwReference/',
					refGroup: '',
					isResponsive: mw.config.get( 'wgCiteResponsiveReferences' )
				}
			},
			{ type: '/mwReferencesList' }
		] );
		return true;
	}
};

/* Registration */

ve.ui.commandRegistry.register( new ve.ui.MWReferencesListCommand() );
