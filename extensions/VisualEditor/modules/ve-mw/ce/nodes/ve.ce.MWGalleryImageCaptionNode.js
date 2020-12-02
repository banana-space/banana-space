/*!
 * VisualEditor ContentEditable GalleryImageCaptionNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable gallery image caption node.
 *
 * @class
 * @extends ve.ce.BranchNode
 * @constructor
 * @param {ve.dm.MWGalleryImageCaptionNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWGalleryImageCaptionNode = function VeCeMWGalleryImageCaptionNode() {
	// Parent constructor
	ve.ce.MWGalleryImageCaptionNode.super.apply( this, arguments );

	this.$element.addClass( 'gallerytext' );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWGalleryImageCaptionNode, ve.ce.BranchNode );

/* Static Properties */

ve.ce.MWGalleryImageCaptionNode.static.name = 'mwGalleryImageCaption';

ve.ce.MWGalleryImageCaptionNode.static.tagName = 'div';

/* Methods */

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWGalleryImageCaptionNode );
