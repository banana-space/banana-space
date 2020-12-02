/*!
 * VisualEditor DataModel AlienMetaItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel alien meta item.
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in linmod
 */
ve.dm.AlienMetaItem = function VeDmAlienMetaItem() {
	// Parent constructor
	ve.dm.AlienMetaItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.AlienMetaItem, ve.dm.MetaItem );

/* Static Properties */

ve.dm.AlienMetaItem.static.name = 'alienMeta';

ve.dm.AlienMetaItem.static.matchTagNames = [ 'meta', 'link' ];

ve.dm.AlienMetaItem.static.preserveHtmlAttributes = false;

ve.dm.AlienMetaItem.static.toDomElements = function ( dataElement, doc, converter ) {
	return ve.copyDomElements( converter.getStore().value( dataElement.originalDomElementsHash ) || [], doc );
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.AlienMetaItem );
