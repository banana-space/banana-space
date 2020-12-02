/*!
 * VisualEditor DataModel AlienBlockNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel alien block node.
 *
 * @class
 * @extends ve.dm.AlienNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 */
ve.dm.AlienBlockNode = function VeDmAlienBlockNode() {
	// Parent constructor
	ve.dm.AlienBlockNode.super.apply( this, arguments );
};

OO.inheritClass( ve.dm.AlienBlockNode, ve.dm.AlienNode );

ve.dm.AlienBlockNode.static.name = 'alienBlock';

/* Registration */

ve.dm.modelRegistry.register( ve.dm.AlienBlockNode );
