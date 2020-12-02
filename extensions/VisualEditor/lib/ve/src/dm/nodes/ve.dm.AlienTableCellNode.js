/*!
 * VisualEditor DataModel AlienTableCellNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel alien table cell node.
 *
 * @class
 * @extends ve.dm.AlienNode
 * @mixins ve.dm.TableCellableNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 */
ve.dm.AlienTableCellNode = function VeDmAlienTableCellNode() {
	// Parent constructor
	ve.dm.AlienTableCellNode.super.apply( this, arguments );

	// Mixin constructor
	ve.dm.TableCellableNode.call( this );
};

OO.inheritClass( ve.dm.AlienTableCellNode, ve.dm.AlienNode );

OO.mixinClass( ve.dm.AlienTableCellNode, ve.dm.TableCellableNode );

ve.dm.AlienTableCellNode.static.name = 'alienTableCell';

/* Registration */

ve.dm.modelRegistry.register( ve.dm.AlienTableCellNode );
