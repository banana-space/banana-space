/*!
 * VisualEditor LeafNode mixin.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Leaf node mixin.
 *
 * @class
 * @abstract
 * @constructor
 */
ve.LeafNode = function VeLeafNode() {
	//
};

/* Methods */

/**
 * Check if the node has children.
 *
 * @return {boolean} Whether the node has children
 */
ve.LeafNode.prototype.hasChildren = function () {
	return false;
};
