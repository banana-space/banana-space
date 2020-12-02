/*!
 * VisualEditor DataModel RealCommentNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * @class
 * @extends ve.dm.CommentNode
 *
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.RealCommentNode = function VeDmRealCommentNode() {
	ve.dm.RealCommentNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.RealCommentNode, ve.dm.CommentNode );

/* Static Properties */

ve.dm.RealCommentNode.static.name = 'comment';

ve.dm.RealCommentNode.static.matchTagNames = [ '#comment' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.RealCommentNode );
