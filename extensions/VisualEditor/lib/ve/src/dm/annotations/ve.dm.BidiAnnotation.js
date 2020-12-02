/*!
 * VisualEditor DataModel BidiAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel bi-di annotation.
 *
 * Represents `<bdi>` tags.
 *
 * @class
 * @extends ve.dm.TextStyleAnnotation
 * @constructor
 * @param {Object} element
 */
ve.dm.BidiAnnotation = function VeDmBidiAnnotation() {
	// Parent constructor
	ve.dm.BidiAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.BidiAnnotation, ve.dm.TextStyleAnnotation );

/* Static Properties */

ve.dm.BidiAnnotation.static.name = 'textStyle/bidi';

ve.dm.BidiAnnotation.static.matchTagNames = [ 'bdi' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.BidiAnnotation );
