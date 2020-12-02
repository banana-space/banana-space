/*!
 * VisualEditor ContentEditable CommentAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable comment annotation.
 *
 * @class
 * @extends ve.ce.Annotation
 * @constructor
 * @param {ve.dm.CommentAnnotation} model Model to observe
 * @param {ve.ce.ContentBranchNode} [parentNode] Node rendering this annotation
 * @param {Object} [config] Configuration options
 */
ve.ce.CommentAnnotation = function VeCeCommentAnnotation() {
	// Parent constructor
	ve.ce.CommentAnnotation.super.apply( this, arguments );

	// DOM changes
	this.$element.addClass( 've-ce-commentAnnotation' );
};

/* Inheritance */

OO.inheritClass( ve.ce.CommentAnnotation, ve.ce.Annotation );

/* Static Properties */

ve.ce.CommentAnnotation.static.name = 'commentAnnotation';

ve.ce.CommentAnnotation.static.tagName = 'span';

ve.ce.CommentAnnotation.static.canBeActive = true;

/* Static Methods */

/**
 * @inheritdoc
 */
ve.ce.CommentAnnotation.static.getDescription = function ( model ) {
	var comments = model.getAttribute( 'comments' );

	return comments[ 0 ].author + ': ' + comments[ 0 ].text;
};

/* Registration */

ve.ce.annotationFactory.register( ve.ce.CommentAnnotation );
