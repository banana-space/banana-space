/*!
 * VisualEditor ContentEditable DivNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable div node.
 *
 * @class
 * @extends ve.ce.BranchNode
 * @constructor
 * @param {ve.dm.DivNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.DivNode = function VeCeDivNode() {
	// Parent constructor
	ve.ce.DivNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.DivNode, ve.ce.BranchNode );

/* Static Properties */

ve.ce.DivNode.static.name = 'div';

/* Registration */

ve.ce.nodeFactory.register( ve.ce.DivNode );
