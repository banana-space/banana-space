/*!
 * VisualEditor DiffTreeNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 * @license The MIT License (MIT); see LICENSE.txt
 */

/* global treeDiffer */

/**
 * Tree node for conducting a tree diff.
 *
 * @class
 * @extends treeDiffer.TreeNode
 * @constructor
 * @param {Object} node Node of any type
 * @param {Object} config
 */
ve.DiffTreeNode = function ( node ) {
	// Parent constructor
	ve.DiffTreeNode.super.apply( this, arguments );

	this.doc = node.getDocument();
};

/* Inheritance */

OO.inheritClass( ve.DiffTreeNode, treeDiffer.TreeNode );

/* Methods */

/**
 * Determine whether two nodes are equal. Branch nodes are considered equal if
 * they have the same types and element.attributes. Content branch nodes are
 * only equal if they also have the same content.
 *
 * @param {ve.DiffTreeNode} otherNode Node to compare to this node
 * @return {boolean} The nodes are equal
 */
ve.DiffTreeNode.prototype.isEqual = function ( otherNode ) {
	if ( this.node.canContainContent() && otherNode.node.canContainContent() ) {
		return JSON.stringify( this.doc.getData( this.node.getOuterRange() ) ) ===
			JSON.stringify( otherNode.doc.getData( otherNode.node.getOuterRange() ) );
	} else {
		return ( this.node.getType() === otherNode.node.getType() &&
			ve.compare( this.node.getHashObject(), otherNode.node.getHashObject() ) );
	}
};

/**
 * Get the children of the original node
 *
 * @return {Array} Array of nodes the same type as the original node
 */
ve.DiffTreeNode.prototype.getOriginalNodeChildren = function () {
	if ( this.node.children && !this.node.canContainContent() ) {
		return this.node.children;
	}
	return [];
};
