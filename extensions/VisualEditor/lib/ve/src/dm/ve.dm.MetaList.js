/*!
 * VisualEditor DataModel MetaList class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel meta item.
 *
 * @class
 * @mixins OO.EventEmitter
 *
 * @constructor
 * @param {ve.dm.Surface} surface Surface model
 */
ve.dm.MetaList = function VeDmMetaList( surface ) {
	var items,
		metaList = this;

	// Mixin constructors
	OO.EventEmitter.call( this );

	this.surface = surface;
	this.document = surface.getDocument();

	// Sorted array of attached ve.dm.MetaItem nodes in document order
	this.items = items = [];

	this.document.connect( this, {
		nodeAttached: 'onNodeAttached',
		nodeDetached: 'onNodeDetached'
	} );

	// Add any ve.dm.MetaItem nodes already present in the document
	this.document.documentNode.traverse( function ( node ) {
		if ( node instanceof ve.dm.MetaItem ) {
			items.push( node );
			node.attachToMetaList( metaList );
		}
	} );
};

/* Inheritance */

OO.mixinClass( ve.dm.MetaList, OO.EventEmitter );

/* Events */

/**
 * @event insert
 * @param {ve.dm.MetaItem} item Item that was inserted
 */

/**
 * @event remove
 * @param {ve.dm.MetaItem} item Item that was removed
 */

/* Methods */

/**
 * If a ve.dm.MetaItem was attached, insert it into items in document order
 *
 * @param {ve.dm.Node} node The node that was attached
 */
ve.dm.MetaList.prototype.onNodeAttached = function ( node ) {
	var i,
		offsetPath = node.getOffsetPath();
	if ( node instanceof ve.dm.MetaItem ) {
		i = OO.binarySearch( this.items, function searchFunc( other ) {
			return ve.compareTuples( offsetPath, other.getOffsetPath() );
		}, true );
		this.items.splice( i, 0, node );
		node.attachToMetaList( this );
		this.emit( 'insert', node );
	}
};

/**
 * If a ve.dm.MetaItem was detached, remove it from items
 *
 * @param {ve.dm.Node} node The node that was detached
 */
ve.dm.MetaList.prototype.onNodeDetached = function ( node ) {
	var i;
	if ( node instanceof ve.dm.MetaItem ) {
		i = this.items.indexOf( node );
		if ( i !== -1 ) {
			node.detachFromMetaList( this );
			this.items.splice( i, 1 );
			this.emit( 'remove', node );
		}
	}
};

ve.dm.MetaList.prototype.indexOf = function ( item, group ) {
	var items = group ? this.getItemsInGroup( group ) : this.items;
	return items.indexOf( item );
};

/**
 * Get all items in a group.
 *
 * This function returns a shallow copy, so the array isn't returned by reference but the items
 * themselves are.
 *
 * @param {string} group Group
 * @return {ve.dm.MetaItem[]} Array of items in the group (shallow copy)
 */
ve.dm.MetaList.prototype.getItemsInGroup = function ( group ) {
	return this.items.filter( function ( item ) {
		return item.getGroup() === group;
	} );
};

/**
 * Get all items in the list.
 *
 * This function returns a shallow copy, so the array isn't returned by reference but the items
 * themselves are.
 *
 * @return {ve.dm.MetaItem[]} Array of items in the list
 */
ve.dm.MetaList.prototype.getAllItems = function () {
	return this.items.slice( 0 );
};

/**
 * Insert new metadata into the document. This builds and processes a transaction that inserts
 * metadata into the document.
 *
 * Pass a plain object rather than a MetaItem into this function unless you know what you're doing.
 *
 * @param {Object|ve.dm.MetaItem} meta Metadata element (or MetaItem) to insert
 * @param {number} offset Document offset to insert at; must be a valid offset for metadata;
 * defaults to document end
 */
ve.dm.MetaList.prototype.insertMeta = function ( meta, offset ) {
	var closeMeta, tx;
	if ( arguments[ 2 ] !== undefined ) {
		throw new Error( 'Old "index" argument is no longer supported' );
	}
	if ( meta instanceof ve.dm.MetaItem ) {
		meta = meta.getElement();
	}
	closeMeta = { type: '/' + meta.type };
	if ( offset === undefined ) {
		offset = this.document.getDocumentRange().end;
	}
	tx = ve.dm.TransactionBuilder.static.newFromInsertion( this.document, offset, [ meta, closeMeta ] );
	this.surface.change( tx );
};

/**
 * Remove a meta item from the document. This builds and processes a transaction that removes the
 * associated metadata from the document.
 *
 * @param {ve.dm.MetaItem} item Item to remove
 */
ve.dm.MetaList.prototype.removeMeta = function ( item ) {
	var tx;
	tx = ve.dm.TransactionBuilder.static.newFromRemoval(
		this.document,
		item.getOuterRange(),
		true
	);
	this.surface.change( tx );
};

/**
 * Replace a MetaItem with another in-place.
 *
 * Pass a plain object rather than a MetaItem into this function unless you know what you're doing.
 *
 * @param {ve.dm.MetaItem} oldItem Old item to replace
 * @param {Object|ve.dm.MetaItem} meta Metadata element (or MetaItem) to insert
 */
ve.dm.MetaList.prototype.replaceMeta = function ( oldItem, meta ) {
	var closeMeta, tx;
	if ( meta instanceof ve.dm.MetaItem ) {
		meta = meta.getElement();
	}
	closeMeta = { type: '/' + meta.type };
	tx = ve.dm.TransactionBuilder.static.newFromReplacement(
		this.document,
		oldItem.getOuterRange(),
		[ meta, closeMeta ],
		true
	);
	this.surface.change( tx );
};
