/*!
 * VisualEditor DataModel DivNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel div node.
 *
 * @class
 * @extends ve.dm.BranchNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.DivNode = function VeDmDivNode() {
	// Parent constructor
	ve.dm.DivNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.DivNode, ve.dm.BranchNode );

/* Static Properties */

ve.dm.DivNode.static.name = 'div';

ve.dm.DivNode.static.matchTagNames = [ 'div' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.DivNode );
