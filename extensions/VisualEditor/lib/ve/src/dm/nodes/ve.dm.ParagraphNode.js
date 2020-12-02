/*!
 * VisualEditor DataModel ParagraphNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel paragraph node.
 *
 * @class
 * @extends ve.dm.ContentBranchNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.ParagraphNode = function VeDmParagraphNode() {
	// Parent constructor
	ve.dm.ParagraphNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.ParagraphNode, ve.dm.ContentBranchNode );

/* Static Properties */

ve.dm.ParagraphNode.static.name = 'paragraph';

ve.dm.ParagraphNode.static.matchTagNames = [ 'p' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.ParagraphNode );
