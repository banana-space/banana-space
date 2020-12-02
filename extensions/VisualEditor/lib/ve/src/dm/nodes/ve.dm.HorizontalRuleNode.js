/*!
 * VisualEditor DataModel HorizontalRuleNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel horizontal rule node.
 *
 * @class
 * @extends ve.dm.LeafNode
 * @mixins ve.dm.FocusableNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 */
ve.dm.HorizontalRuleNode = function VeDmHorizontalRuleNode() {
	// Parent constructor
	ve.dm.HorizontalRuleNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.FocusableNode.call( this );
};

/* Inheritance */

OO.inheritClass( ve.dm.HorizontalRuleNode, ve.dm.LeafNode );

OO.mixinClass( ve.dm.HorizontalRuleNode, ve.dm.FocusableNode );

/* Static Properties */

ve.dm.HorizontalRuleNode.static.name = 'horizontalRule';

ve.dm.HorizontalRuleNode.static.matchTagNames = [ 'hr' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.HorizontalRuleNode );
