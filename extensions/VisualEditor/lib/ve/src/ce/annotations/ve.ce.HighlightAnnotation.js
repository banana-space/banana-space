/*!
 * VisualEditor ContentEditable HighlightAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable highlight annotation.
 *
 * @class
 * @extends ve.ce.TextStyleAnnotation
 * @constructor
 * @param {ve.dm.HighlightAnnotation} model Model to observe
 * @param {ve.ce.ContentBranchNode} [parentNode] Node rendering this annotation
 * @param {Object} [config] Configuration options
 */
ve.ce.HighlightAnnotation = function VeCeHighlightAnnotation() {
	// Parent constructor
	ve.ce.HighlightAnnotation.super.apply( this, arguments );

	// DOM changes
	this.$element.addClass( 've-ce-highlightAnnotation' );
};

/* Inheritance */

OO.inheritClass( ve.ce.HighlightAnnotation, ve.ce.TextStyleAnnotation );

/* Static Properties */

ve.ce.HighlightAnnotation.static.name = 'textStyle/highlight';

ve.ce.HighlightAnnotation.static.tagName = 'mark';

/* Registration */

ve.ce.annotationFactory.register( ve.ce.HighlightAnnotation );
