/*!
 * VisualEditor ContentEditable BlockquoteNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable Blockquote node.
 *
 * @class
 * @extends ve.ce.BranchNode
 * @constructor
 * @param {ve.dm.BlockquoteNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.BlockquoteNode = function VeCeBlockquoteNode() {
	// Parent constructor
	ve.ce.BlockquoteNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.BlockquoteNode, ve.ce.BranchNode );

/* Static Properties */

ve.ce.BlockquoteNode.static.name = 'blockquote';

ve.ce.BlockquoteNode.static.tagName = 'blockquote';

ve.ce.BlockquoteNode.static.removeEmptyLastChildOnEnter = true;

/* Registration */

ve.ce.nodeFactory.register( ve.ce.BlockquoteNode );
