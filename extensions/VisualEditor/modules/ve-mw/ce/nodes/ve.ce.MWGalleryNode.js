/*!
 * VisualEditor ContentEditable MWGalleryNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki gallery node.
 *
 * @class
 * @extends ve.ce.BranchNode
 * @mixins ve.ce.FocusableNode
 *
 * @constructor
 * @param {ve.dm.MWGalleryNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWGalleryNode = function VeCeMWGalleryNode() {
	// Parent constructor
	ve.ce.MWGalleryNode.super.apply( this, arguments );

	// DOM hierarchy for MWGalleryNode:
	//   <ul> this.$element (gallery mw-gallery-{mode})
	//     <li> ve.ce.MWGalleryCaptionNode (gallerycaption)
	//     <li> ve.ce.MWGalleryImageNode (gallerybox)
	//     <li> ve.ce.MWGalleryImageNode (gallerybox)
	//     ⋮

	// Mixin constructors
	ve.ce.FocusableNode.call( this, this.$element );

	// Events
	this.model.connect( this, { update: 'updateInvisibleIcon' } );
	this.model.connect( this, { update: 'onUpdate' } );
	this.model.connect( this, { attributeChange: 'onAttributeChange' } );

	// Initialization
	this.$element.addClass( 'gallery' );
	this.onUpdate();
};

/* Inheritance */

OO.inheritClass( ve.ce.MWGalleryNode, ve.ce.BranchNode );

OO.mixinClass( ve.ce.MWGalleryNode, ve.ce.FocusableNode );

/* Static Properties */

ve.ce.MWGalleryNode.static.name = 'mwGallery';

ve.ce.MWGalleryNode.static.tagName = 'ul';

ve.ce.MWGalleryNode.static.iconWhenInvisible = 'imageGallery';

ve.ce.MWGalleryNode.static.primaryCommandName = 'gallery';

/* Methods */

/**
 * Handle model update events.
 */
ve.ce.MWGalleryNode.prototype.onUpdate = function () {
	var mwAttrs, defaults, mode, imageWidth, imagePadding;

	mwAttrs = this.model.getAttribute( 'mw' ).attrs;
	defaults = mw.config.get( 'wgVisualEditorConfig' ).galleryOptions;
	mode = mwAttrs.mode || defaults.mode;

	// `.attr( …, undefined )` does nothing - it's required to use `null` to remove an attribute.
	// (This also clears the 'max-width', set below, if it's not needed.)
	this.$element.attr( 'style', mwAttrs.style || null );

	if ( mwAttrs.perrow && ( mode === 'traditional' || mode === 'nolines' ) ) {
		// Magic 30 and 8 matches the code in ve.ce.MWGalleryImageNode
		imageWidth = parseInt( mwAttrs.widths || defaults.imageWidth );
		imagePadding = ( mode === 'traditional' ? 30 : 0 );
		this.$element.css( 'max-width', mwAttrs.perrow * ( imageWidth + imagePadding + 8 ) );
	}
};

/**
 * Handle attribute changes to keep the live HTML element updated.
 *
 * @param {string} key Attribute name
 * @param {Mixed} from Old value
 * @param {Mixed} to New value
 */
ve.ce.MWGalleryNode.prototype.onAttributeChange = function ( key, from, to ) {
	var defaults = mw.config.get( 'wgVisualEditorConfig' ).galleryOptions;

	if ( key !== 'mw' ) {
		return;
	}

	if ( from.attrs.class !== to.attrs.class ) {
		// We can't overwrite the whole 'class' HTML attribute, because it also contains a class
		// generated from the 'mode' MW attribute, and VE internal classes like 've-ce-focusableNode'
		// eslint-disable-next-line mediawiki/class-doc
		this.$element
			.removeClass( from.attrs.class )
			.addClass( to.attrs.class );
	}

	if ( from.attrs.mode !== to.attrs.mode ) {
		// The following classes are used here:
		// * mw-gallery-traditional
		// * mw-gallery-nolines
		// * mw-gallery-packed
		// * mw-gallery-packed-overlay
		// * mw-gallery-packed-hover
		// * mw-gallery-slideshow
		this.$element
			.removeClass( 'mw-gallery-' + ( from.attrs.mode || defaults.mode ) )
			.addClass( 'mw-gallery-' + ( to.attrs.mode || defaults.mode ) );
	}
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWGalleryNode );
