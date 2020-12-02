/*!
 * VisualEditor DataModel TextStyleAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel text style annotation.
 *
 * @class
 * @abstract
 * @extends ve.dm.Annotation
 * @constructor
 * @param {Object} element
 */
ve.dm.TextStyleAnnotation = function VeDmTextStyleAnnotation() {
	// Parent constructor
	ve.dm.TextStyleAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.TextStyleAnnotation, ve.dm.Annotation );

/* Static Properties */

ve.dm.TextStyleAnnotation.static.name = 'textStyle';

ve.dm.TextStyleAnnotation.static.matchTagNames = [];

ve.dm.TextStyleAnnotation.static.toDataElement = function ( domElements, converter ) {
	var nodeName = converter.isFromClipboard() ? this.matchTagNames[ 0 ] : domElements[ 0 ].nodeName.toLowerCase();
	return {
		type: this.name,
		attributes: {
			nodeName: nodeName
		}
	};
};

ve.dm.TextStyleAnnotation.static.toDomElements = function ( dataElement, doc ) {
	var nodeName = ve.getProp( dataElement, 'attributes', 'nodeName' );

	return [ doc.createElement( nodeName || this.matchTagNames[ 0 ] ) ];
};

/* Methods */

/**
 * @return {Object}
 */
ve.dm.TextStyleAnnotation.prototype.getComparableObject = function () {
	return { type: this.getType() };
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.TextStyleAnnotation );
