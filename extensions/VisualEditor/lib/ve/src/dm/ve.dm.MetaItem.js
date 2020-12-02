/*!
 * VisualEditor DataModel MetaItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel meta item.
 * TODO: rename to MetaNode to reflect the fact it is now a node
 *
 * @class
 * @abstract
 * @extends ve.dm.LeafNode
 * @mixins OO.EventEmitter
 *
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.MetaItem = function VeDmMetaItem() {
	// Parent constructor
	ve.dm.MetaItem.super.apply( this, arguments );
	// Mixin
	OO.EventEmitter.call( this );
	// Properties
	this.list = null;
};

/* Inheritance */

OO.inheritClass( ve.dm.MetaItem, ve.dm.LeafNode );

OO.mixinClass( ve.dm.MetaItem, OO.EventEmitter );

/* Static members */

ve.dm.MetaItem.static.isContent = false;

ve.dm.MetaItem.static.isMetaData = true;

ve.dm.MetaItem.static.canSerializeAsContent = true;

/**
 * Symbolic name for the group this meta item type will be grouped in in ve.dm.MetaList.
 *
 * @static
 * @property {string}
 * @inheritable
 */
ve.dm.MetaItem.static.group = 'misc';

/**
 * If the metaitem can be removed by regular remove operations
 *
 * @static
 * @property {boolean}
 * @inheritable
 */
ve.dm.MetaItem.static.removable = false;

/* Methods */

/**
 * Remove this item from the document. Only works if the item is attached to a MetaList.
 *
 * @throws {Error} Cannot remove detached item
 */
ve.dm.MetaItem.prototype.remove = function () {
	if ( !this.list ) {
		throw new Error( 'Cannot remove detached item' );
	}
	this.list.removeMeta( this );
};

/**
 * Replace item with another in-place.
 *
 * Pass a plain object rather than a MetaItem into this function unless you know what you're doing.
 *
 * @param {Object|ve.dm.MetaItem} item Item to replace this item with
 */
ve.dm.MetaItem.prototype.replaceWith = function ( item ) {
	this.list.replaceMeta( this, item );
};

/**
 * Get the group this meta item belongs to.
 *
 * @see #static-group
 * @return {string} Group
 */
ve.dm.MetaItem.prototype.getGroup = function () {
	return this.constructor.static.group;
};

/**
 * Attach this item to a MetaList.
 *
 * @param {ve.dm.MetaList} list Parent list to attach to
 */
ve.dm.MetaItem.prototype.attachToMetaList = function ( list ) {
	this.list = list;
};

/**
 * Detach this item from its parent list.
 *
 * @param {ve.dm.MetaList} list List to detach from
 */
ve.dm.MetaItem.prototype.detachFromMetaList = function ( list ) {
	if ( this.list === list ) {
		this.list = null;
	}
};

/**
 * Check whether this item is attached to a MetaList.
 *
 * @return {boolean} Whether item is attached
 */
ve.dm.MetaItem.prototype.isAttached = function () {
	return this.list !== null;
};
