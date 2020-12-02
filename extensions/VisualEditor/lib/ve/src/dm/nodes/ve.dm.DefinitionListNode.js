/*!
 * VisualEditor DataModel DefinitionListNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel definition list node.
 *
 * @class
 * @extends ve.dm.BranchNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.DefinitionListNode = function VeDmDefinitionListNode() {
	// Parent constructor
	ve.dm.DefinitionListNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.DefinitionListNode, ve.dm.BranchNode );

/* Static Properties */

ve.dm.DefinitionListNode.static.name = 'definitionList';

ve.dm.DefinitionListNode.static.childNodeTypes = [ 'definitionListItem' ];

ve.dm.DefinitionListNode.static.matchTagNames = [ 'dl' ];

ve.dm.DefinitionListNode.static.isDiffedAsList = true;

/* Registration */

ve.dm.modelRegistry.register( ve.dm.DefinitionListNode );
