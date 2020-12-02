/*!
 * VisualEditor ContentEditable BreakNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable break node.
 *
 * @class
 * @extends ve.ce.LeafNode
 * @constructor
 * @param {ve.dm.BreakNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.BreakNode = function VeCeBreakNode() {
	// Parent constructor
	ve.ce.BreakNode.super.apply( this, arguments );

	// DOM changes
	this.$element.addClass( 've-ce-breakNode' );
};

/* Inheritance */

OO.inheritClass( ve.ce.BreakNode, ve.ce.LeafNode );

/* Static Properties */

ve.ce.BreakNode.static.name = 'break';

ve.ce.BreakNode.static.tagName = 'br';

/* Registration */

ve.ce.nodeFactory.register( ve.ce.BreakNode );
