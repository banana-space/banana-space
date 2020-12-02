/*!
 * VisualEditor DataModel ListItemNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel list item node.
 *
 * @class
 * @extends ve.dm.BranchNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.ListItemNode = function VeDmListItemNode() {
	// Parent constructor
	ve.dm.ListItemNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.ListItemNode, ve.dm.BranchNode );

/* Static Properties */

ve.dm.ListItemNode.static.name = 'listItem';

ve.dm.ListItemNode.static.parentNodeTypes = [ 'list' ];

ve.dm.ListItemNode.static.matchTagNames = [ 'li' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.ListItemNode );
