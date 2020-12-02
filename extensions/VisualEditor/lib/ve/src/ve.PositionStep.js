/*!
 * VisualEditor representation of a node traversal step
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * @class
 *
 * @constructor
 * @param {Node} node The element or text node being crossed
 * @param {string} type The type of step: "enter", "leave", "cross" or (for text nodes) "internal"
 * @param {number} offset For "internal" text node steps only: the character offset being crossed
 */
ve.PositionStep = function VePositionStep( node, type, offset ) {
	this.node = node;
	this.type = type;
	this.offset = offset;
};
