/*!
 * VisualEditor InternalListNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable internal list node.
 *
 * @class
 * @extends ve.ce.BranchNode
 * @constructor
 * @param {ve.dm.InternalListNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.InternalListNode = function VeCeInternalListNode() {
	// Parent constructor
	ve.ce.InternalListNode.super.apply( this, arguments );

	// An internal list has no rendering
	this.$element = $( [] );
};

/* Inheritance */

OO.inheritClass( ve.ce.InternalListNode, ve.ce.BranchNode );

/* Static Properties */

ve.ce.InternalListNode.static.name = 'internalList';

/* Methods */

/**
 * Deliberately empty: don't build an entire CE tree with DOM elements for things that won't render
 *
 * @inheritdoc
 */
ve.ce.InternalListNode.prototype.onSplice = function () {
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.InternalListNode );
