/*!
 * VisualEditor DataModel CenterNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel center node.
 *
 * @class
 * @extends ve.dm.BranchNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.CenterNode = function VeDmCenterNode() {
	// Parent constructor
	ve.dm.CenterNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.CenterNode, ve.dm.BranchNode );

/* Static Properties */

ve.dm.CenterNode.static.name = 'center';

ve.dm.CenterNode.static.matchTagNames = [ 'center' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.CenterNode );
