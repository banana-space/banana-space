/*!
 * VisualEditor DataModel BoldAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel bold annotation.
 *
 * Represents `<b>` and `<strong>` tags.
 *
 * @class
 * @extends ve.dm.TextStyleAnnotation
 * @constructor
 * @param {Object} element
 */
ve.dm.BoldAnnotation = function VeDmBoldAnnotation() {
	// Parent constructor
	ve.dm.BoldAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.BoldAnnotation, ve.dm.TextStyleAnnotation );

/* Static Properties */

ve.dm.BoldAnnotation.static.name = 'textStyle/bold';

ve.dm.BoldAnnotation.static.matchTagNames = [ 'b', 'strong' ];

ve.dm.BoldAnnotation.static.inferFromView = true;

/* Registration */

ve.dm.modelRegistry.register( ve.dm.BoldAnnotation );
