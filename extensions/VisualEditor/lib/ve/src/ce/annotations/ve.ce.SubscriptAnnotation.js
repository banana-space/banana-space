/*!
 * VisualEditor ContentEditable SubscriptAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable subscript annotation.
 *
 * @class
 * @extends ve.ce.TextStyleAnnotation
 * @constructor
 * @param {ve.dm.SubscriptAnnotation} model Model to observe
 * @param {ve.ce.ContentBranchNode} [parentNode] Node rendering this annotation
 * @param {Object} [config] Configuration options
 */
ve.ce.SubscriptAnnotation = function VeCeSubscriptAnnotation() {
	// Parent constructor
	ve.ce.SubscriptAnnotation.super.apply( this, arguments );

	// DOM changes
	this.$element.addClass( 've-ce-subscriptAnnotation' );
};

/* Inheritance */

OO.inheritClass( ve.ce.SubscriptAnnotation, ve.ce.TextStyleAnnotation );

/* Static Properties */

ve.ce.SubscriptAnnotation.static.name = 'textStyle/subscript';

ve.ce.SubscriptAnnotation.static.tagName = 'sub';

/* Registration */

ve.ce.annotationFactory.register( ve.ce.SubscriptAnnotation );
