/*!
 * VisualEditor ContentEditable PreformattedNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable preformatted node.
 *
 * @class
 * @extends ve.ce.ContentBranchNode
 * @constructor
 * @param {ve.dm.PreformattedNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.PreformattedNode = function VeCePreformattedNode() {
	// Parent constructor
	ve.ce.PreformattedNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.PreformattedNode, ve.ce.ContentBranchNode );

/* Static Properties */

ve.ce.PreformattedNode.static.name = 'preformatted';

ve.ce.PreformattedNode.static.tagName = 'pre';

/* Registration */

ve.ce.nodeFactory.register( ve.ce.PreformattedNode );
