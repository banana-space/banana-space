/*!
 * VisualEditor ContentEditable ItalicAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable italic annotation.
 *
 * @class
 * @extends ve.ce.TextStyleAnnotation
 * @constructor
 * @param {ve.dm.ItalicAnnotation} model Model to observe
 * @param {ve.ce.ContentBranchNode} [parentNode] Node rendering this annotation
 * @param {Object} [config] Configuration options
 */
ve.ce.ItalicAnnotation = function VeCeItalicAnnotation() {
	// Parent constructor
	ve.ce.ItalicAnnotation.super.apply( this, arguments );

	// DOM changes
	this.$element.addClass( 've-ce-italicAnnotation' );
};

/* Inheritance */

OO.inheritClass( ve.ce.ItalicAnnotation, ve.ce.TextStyleAnnotation );

/* Static Properties */

ve.ce.ItalicAnnotation.static.name = 'textStyle/italic';

ve.ce.ItalicAnnotation.static.tagName = 'i';

/* Registration */

ve.ce.annotationFactory.register( ve.ce.ItalicAnnotation );
