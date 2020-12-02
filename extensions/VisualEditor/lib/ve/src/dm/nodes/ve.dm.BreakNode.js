/*!
 * VisualEditor DataModel BreakNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel break node.
 *
 * @class
 * @extends ve.dm.LeafNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 */
ve.dm.BreakNode = function VeDmBreakNode() {
	// Parent constructor
	ve.dm.BreakNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.BreakNode, ve.dm.LeafNode );

/* Static Properties */

ve.dm.BreakNode.static.name = 'break';

ve.dm.BreakNode.static.isContent = true;

ve.dm.BreakNode.static.matchTagNames = [ 'br' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.BreakNode );
