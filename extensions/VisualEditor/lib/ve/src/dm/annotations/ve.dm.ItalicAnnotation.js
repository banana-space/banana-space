/*!
 * VisualEditor DataModel ItalicAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel italic annotation.
 *
 * Represents `<i>` and `<em>` tags.
 *
 * @class
 * @extends ve.dm.TextStyleAnnotation
 * @constructor
 * @param {Object} element
 */
ve.dm.ItalicAnnotation = function VeDmItalicAnnotation() {
	// Parent constructor
	ve.dm.ItalicAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.ItalicAnnotation, ve.dm.TextStyleAnnotation );

/* Static Properties */

ve.dm.ItalicAnnotation.static.name = 'textStyle/italic';

ve.dm.ItalicAnnotation.static.matchTagNames = [ 'i', 'em' ];

ve.dm.ItalicAnnotation.static.inferFromView = true;

/* Registration */

ve.dm.modelRegistry.register( ve.dm.ItalicAnnotation );
