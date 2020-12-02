/*!
 * VisualEditor DataModel AlignableNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * A mixin class for Alignable nodes.
 *
 * @class
 * @abstract
 * @extends ve.dm.ClassAttributeNode
 *
 * @constructor
 */
ve.dm.AlignableNode = function VeDmAlignableNode() {
	// Parent constructor
	ve.dm.AlignableNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.AlignableNode, ve.dm.ClassAttributeNode );

/* Static properties */

ve.dm.AlignableNode.static.isAlignable = true;

ve.dm.AlignableNode.static.classAttributes = {
	've-align-left': { align: 'left' },
	've-align-right': { align: 'right' },
	've-align-center': { align: 'center' }
};
