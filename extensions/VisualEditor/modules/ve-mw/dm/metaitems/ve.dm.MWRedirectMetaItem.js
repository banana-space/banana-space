/*!
 * VisualEditor DataModel MWRedirectMetaItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel redirect meta item.
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.MWRedirectMetaItem = function VeDmMWRedirectMetaItem() {
	// Parent constructor
	ve.dm.MWRedirectMetaItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWRedirectMetaItem, ve.dm.MetaItem );

/* Static Properties */

ve.dm.MWRedirectMetaItem.static.name = 'mwRedirect';

ve.dm.MWRedirectMetaItem.static.group = 'mwRedirect';

ve.dm.MWRedirectMetaItem.static.matchTagNames = [ 'link' ];

ve.dm.MWRedirectMetaItem.static.matchRdfaTypes = [ 'mw:PageProp/redirect' ];

ve.dm.MWRedirectMetaItem.static.toDataElement = function ( domElements, converter ) {
	// HACK piggy-back on MWInternalLinkAnnotation's ./ stripping logic
	var linkData = ve.dm.MWInternalLinkAnnotation.static.toDataElement( domElements, converter );
	linkData.type = this.name;
	return linkData;
};

ve.dm.MWRedirectMetaItem.static.toDomElements = function ( dataElement, doc ) {
	var meta = doc.createElement( 'link' );
	meta.setAttribute( 'rel', 'mw:PageProp/redirect' );
	// HACK piggy-back on MWInternalLinkAnnotation's logic
	meta.setAttribute( 'href', ve.dm.MWInternalLinkAnnotation.static.getHref( dataElement ) );
	return [ meta ];
};

ve.dm.MWRedirectMetaItem.static.describeChange = function ( key, change ) {
	if ( key === 'title' ) {
		return ve.htmlMsg( 'visualeditor-changedesc-mwredirect', this.wrapText( 'del', change.from ), this.wrapText( 'ins', change.to ) );
	}
	return null;
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWRedirectMetaItem );
