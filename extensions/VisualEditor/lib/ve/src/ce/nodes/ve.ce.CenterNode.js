/*!
 * VisualEditor ContentEditable CenterNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable center node.
 *
 * @class
 * @extends ve.ce.BranchNode
 * @constructor
 * @param {ve.dm.CenterNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.CenterNode = function VeCeCenterNode() {
	// Parent constructor
	ve.ce.CenterNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.CenterNode, ve.ce.BranchNode );

/* Static Properties */

ve.ce.CenterNode.static.name = 'center';

ve.ce.CenterNode.static.tagName = 'center';

/* Registration */

ve.ce.nodeFactory.register( ve.ce.CenterNode );
