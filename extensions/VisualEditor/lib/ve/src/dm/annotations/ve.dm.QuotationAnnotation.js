/*!
 * VisualEditor DataModel QuotationAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel quotation annotation.
 *
 * Represents `<q>` tags.
 *
 * @class
 * @extends ve.dm.TextStyleAnnotation
 * @constructor
 * @param {Object} element
 */
ve.dm.QuotationAnnotation = function VeDmQuotationAnnotation() {
	// Parent constructor
	ve.dm.QuotationAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.QuotationAnnotation, ve.dm.TextStyleAnnotation );

/* Static Properties */

ve.dm.QuotationAnnotation.static.name = 'textStyle/quotation';

ve.dm.QuotationAnnotation.static.matchTagNames = [ 'q' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.QuotationAnnotation );
