/*!
 * VisualEditor DataModel SpanAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel span annotation.
 *
 * Represents `<span>` tags.
 *
 * @class
 * @extends ve.dm.TextStyleAnnotation
 * @constructor
 * @param {Object} element
 */
ve.dm.SpanAnnotation = function VeDmSpanAnnotation() {
	// Parent constructor
	ve.dm.SpanAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.SpanAnnotation, ve.dm.TextStyleAnnotation );

/* Static Properties */

ve.dm.SpanAnnotation.static.name = 'textStyle/span';

ve.dm.SpanAnnotation.static.matchTagNames = [ 'span' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.SpanAnnotation );
