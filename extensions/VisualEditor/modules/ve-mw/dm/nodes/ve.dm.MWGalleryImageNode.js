/*!
 * VisualEditor DataModel MWGalleryImageNode class.
 *
 * @copyright 2016 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki gallery image node.
 *
 * @class
 * @extends ve.dm.BranchNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.MWGalleryImageNode = function VeDmMWGalleryImageNode() {
	// Parent constructor
	ve.dm.MWGalleryImageNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWGalleryImageNode, ve.dm.BranchNode );

/* Static members */

ve.dm.MWGalleryImageNode.static.name = 'mwGalleryImage';

ve.dm.MWGalleryImageNode.static.matchTagNames = [ 'li' ];

ve.dm.MWGalleryImageNode.static.childNodeTypes = [ 'mwGalleryImageCaption' ];

ve.dm.MWGalleryImageNode.static.matchFunction = function ( element ) {
	var parentTypeof = ( element.parentNode && element.parentNode.getAttribute( 'typeof' ) ) || '';
	return element.getAttribute( 'class' ) === 'gallerybox' &&
		parentTypeof.trim().split( /\s+/ ).indexOf( 'mw:Extension/gallery' ) !== -1;
};

ve.dm.MWGalleryImageNode.static.parentNodeTypes = [ 'mwGallery' ];

ve.dm.MWGalleryImageNode.static.toDataElement = function ( domElements, converter ) {
	var li, img, captionNode, caption, filename, dataElement;

	// TODO: Improve handling of missing files. See 'isError' in MWBlockImageNode#toDataElement
	li = domElements[ 0 ];
	img = li.querySelector( 'img,audio,video,span[resource]' );

	// Get caption (may be missing for mode="packed-hover" galleries)
	captionNode = li.querySelector( '.gallerytext' );
	if ( captionNode ) {
		captionNode = captionNode.cloneNode( true );
		// If showFilename is 'yes', the filename is also inside the caption, so throw this out
		filename = captionNode.querySelector( '.galleryfilename' );
		if ( filename ) {
			filename.remove();
		}
	}

	if ( captionNode ) {
		caption = converter.getDataFromDomClean( captionNode, { type: 'mwGalleryImageCaption' } );
	} else {
		caption = [
			{ type: 'mwGalleryImageCaption' },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			{ type: '/paragraph' },
			{ type: '/mwGalleryImageCaption' }
		];
	}

	dataElement = {
		type: this.name,
		attributes: {
			resource: mw.libs.ve.normalizeParsoidResourceName( img.getAttribute( 'resource' ) ),
			altText: img.getAttribute( 'alt' ),
			// 'src' for images, 'poster' for video/audio
			src: img.getAttribute( 'src' ) || img.getAttribute( 'poster' ),
			height: img.getAttribute( 'height' ),
			width: img.getAttribute( 'width' )
		}
	};

	return [ dataElement ]
		.concat( caption )
		.concat( { type: '/' + this.name } );
};

ve.dm.MWGalleryImageNode.static.toDomElements = function ( data, doc ) {
	// ImageNode:
	//   <li> li (gallerybox)
	//     <div> thumbDiv
	//       <figure-inline> innerDiv
	//         <a> a
	//           <img> img
	var model = data,
		li = doc.createElement( 'li' ),
		thumbDiv = doc.createElement( 'div' ),
		innerDiv = doc.createElement( 'figure-inline' ),
		a = doc.createElement( 'a' ),
		img = doc.createElement( 'img' ),
		alt = model.attributes.altText;

	li.classList.add( 'gallerybox' );
	thumbDiv.classList.add( 'thumb' );
	innerDiv.setAttribute( 'typeof', 'mw:Image' );

	// TODO: Support editing the link
	// a.setAttribute( 'href', model.attributes.src );

	img.setAttribute( 'resource', model.attributes.resource );
	img.setAttribute( 'src', model.attributes.src );
	if ( alt ) {
		img.setAttribute( 'alt', alt );
	}

	a.appendChild( img );
	innerDiv.appendChild( a );
	thumbDiv.appendChild( innerDiv );
	li.appendChild( thumbDiv );

	return [ li ];
};

ve.dm.MWGalleryImageNode.static.describeChange = function ( key ) {
	// These attributes are computed
	if ( key === 'src' || key === 'width' || key === 'height' ) {
		return null;
	}
	// Parent method
	return ve.dm.MWGalleryImageNode.super.static.describeChange.apply( this, arguments );
};

/* Methods */

/**
 * Get the image's caption node.
 *
 * @return {ve.dm.MWImageCaptionNode|null} Caption node, if present
 */
ve.dm.MWGalleryImageNode.prototype.getCaptionNode = function () {
	return this.children.length > 0 ? this.children[ 0 ] : null;
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWGalleryImageNode );
