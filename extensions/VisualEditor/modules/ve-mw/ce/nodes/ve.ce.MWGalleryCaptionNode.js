/*!
 * VisualEditor ContentEditable GalleryCaptionNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable gallery caption node.
 *
 * @class
 * @extends ve.ce.BranchNode
 * @constructor
 * @param {ve.dm.MWGalleryCaptionNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWGalleryCaptionNode = function VeCeMWGalleryCaptionNode() {
	// Parent constructor
	ve.ce.MWGalleryCaptionNode.super.apply( this, arguments );

	// Build DOM
	this.$element.addClass( 'gallerycaption' );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWGalleryCaptionNode, ve.ce.BranchNode );

/* Static Properties */

ve.ce.MWGalleryCaptionNode.static.name = 'mwGalleryCaption';

ve.ce.MWGalleryCaptionNode.static.tagName = 'li';

/* Methods */

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWGalleryCaptionNode );
