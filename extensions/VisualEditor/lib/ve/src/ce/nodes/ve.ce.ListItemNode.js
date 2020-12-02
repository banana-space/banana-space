/*!
 * VisualEditor ContentEditable ListItemNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable list item node.
 *
 * @class
 * @extends ve.ce.BranchNode
 * @mixins ve.ce.ContentEditableNode
 * @constructor
 * @param {ve.dm.ListItemNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.ListItemNode = function VeCeListItemNode() {
	// Parent constructor
	ve.ce.ListItemNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.ListItemNode, ve.ce.BranchNode );
OO.mixinClass( ve.ce.ListItemNode, ve.ce.ContentEditableNode );

/* Static Properties */

ve.ce.ListItemNode.static.name = 'listItem';

ve.ce.ListItemNode.static.tagName = 'li';

ve.ce.ListItemNode.static.splitOnEnter = true;

/* Registration */

ve.ce.nodeFactory.register( ve.ce.ListItemNode );
