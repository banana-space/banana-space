/*!
 * VisualEditor DataModel MWDefaultSortMetaItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel category default sort meta item.
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.MWDefaultSortMetaItem = function VeDmMWDefaultSortMetaItem() {
	// Parent constructor
	ve.dm.MWDefaultSortMetaItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWDefaultSortMetaItem, ve.dm.MetaItem );

/* Static Properties */

ve.dm.MWDefaultSortMetaItem.static.name = 'mwDefaultSort';

ve.dm.MWDefaultSortMetaItem.static.group = 'mwDefaultSort';

ve.dm.MWDefaultSortMetaItem.static.matchTagNames = [ 'meta' ];

ve.dm.MWDefaultSortMetaItem.static.matchRdfaTypes = [ 'mw:PageProp/categorydefaultsort' ];

ve.dm.MWDefaultSortMetaItem.static.toDataElement = function ( domElements ) {
	var content = domElements[ 0 ].getAttribute( 'content' );
	return {
		type: this.name,
		attributes: {
			content: content
		}
	};
};

ve.dm.MWDefaultSortMetaItem.static.toDomElements = function ( dataElement, doc ) {
	var meta = doc.createElement( 'meta' );
	meta.setAttribute( 'property', 'mw:PageProp/categorydefaultsort' );
	meta.setAttribute( 'content', dataElement.attributes.content );
	return [ meta ];
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWDefaultSortMetaItem );
