/*!
 * VisualEditor DataModel MWCategoryMetaItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel category meta item.
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.MWCategoryMetaItem = function VeDmMWCategoryMetaItem() {
	// Parent constructor
	ve.dm.MWCategoryMetaItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWCategoryMetaItem, ve.dm.MetaItem );

/* Static Properties */

ve.dm.MWCategoryMetaItem.static.name = 'mwCategory';

ve.dm.MWCategoryMetaItem.static.group = 'mwCategory';

ve.dm.MWCategoryMetaItem.static.matchTagNames = [ 'link' ];

ve.dm.MWCategoryMetaItem.static.matchRdfaTypes = [ 'mw:PageProp/Category' ];

ve.dm.MWCategoryMetaItem.static.toDataElement = function ( domElements ) {
	var href = domElements[ 0 ].getAttribute( 'href' ),
		data = mw.libs.ve.parseParsoidResourceName( href ),
		rawTitleAndFragment = data.rawTitle.match( /^(.*?)(?:#(.*))?$/ ),
		titleAndFragment = data.title.match( /^(.*?)(?:#(.*))?$/ );
	return {
		type: this.name,
		attributes: {
			category: titleAndFragment[ 1 ],
			origCategory: rawTitleAndFragment[ 1 ],
			sortkey: titleAndFragment[ 2 ] || '',
			origSortkey: rawTitleAndFragment[ 2 ] || ''
		}
	};
};

ve.dm.MWCategoryMetaItem.static.toDomElements = function ( dataElement, doc ) {
	var href, encodedCategory,
		domElement = doc.createElement( 'link' ),
		category = dataElement.attributes.category || '',
		sortkey = dataElement.attributes.sortkey || '',
		origCategory = dataElement.attributes.origCategory || '',
		origSortkey = dataElement.attributes.origSortkey || '',
		normalizedOrigCategory = mw.libs.ve.decodeURIComponentIntoArticleTitle( origCategory ),
		normalizedOrigSortkey = mw.libs.ve.decodeURIComponentIntoArticleTitle( origSortkey );
	if ( normalizedOrigSortkey === sortkey ) {
		sortkey = origSortkey;
	} else {
		sortkey = encodeURIComponent( sortkey );
	}
	if ( normalizedOrigCategory === category ) {
		encodedCategory = origCategory;
	} else {
		encodedCategory = encodeURIComponent( category );
	}
	domElement.setAttribute( 'rel', 'mw:PageProp/Category' );
	href = './' + encodedCategory;
	if ( sortkey !== '' ) {
		href += '#' + sortkey;
	}
	domElement.setAttribute( 'href', href );
	return [ domElement ];
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWCategoryMetaItem );
