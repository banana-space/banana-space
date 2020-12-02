/*!
 * VisualEditor ContentEditable AlienBlockNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable alien block node.
 *
 * @class
 * @extends ve.ce.AlienNode
 *
 * @constructor
 * @param {ve.dm.AlienBlockNode} model
 * @param {Object} [config]
 */
ve.ce.AlienBlockNode = function VeCeAlienBlockNode() {
	// Parent constructor
	ve.ce.AlienBlockNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.AlienBlockNode, ve.ce.AlienNode );

/* Static Properties */

ve.ce.AlienBlockNode.static.name = 'alienBlock';

/* Registration */

ve.ce.nodeFactory.register( ve.ce.AlienBlockNode );
