/*!
 * VisualEditor DataModel MWBlockImageNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki image node.
 *
 * @class
 * @extends ve.dm.BranchNode
 * @mixins ve.dm.MWImageNode
 * @mixins ve.dm.ClassAttributeNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.MWBlockImageNode = function VeDmMWBlockImageNode() {
	// Parent constructor
	ve.dm.MWBlockImageNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.MWImageNode.call( this );
	ve.dm.ClassAttributeNode.call( this );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWBlockImageNode, ve.dm.BranchNode );

// Need to mixin base class as well (T92540)
OO.mixinClass( ve.dm.MWBlockImageNode, ve.dm.GeneratedContentNode );

OO.mixinClass( ve.dm.MWBlockImageNode, ve.dm.MWImageNode );

OO.mixinClass( ve.dm.MWBlockImageNode, ve.dm.ClassAttributeNode );

/* Static Properties */

ve.dm.MWBlockImageNode.static.name = 'mwBlockImage';

ve.dm.MWBlockImageNode.static.preserveHtmlAttributes = function ( attribute ) {
	var attributes = [ 'typeof', 'class', 'src', 'resource', 'width', 'height', 'href', 'rel', 'data-mw' ];
	return attributes.indexOf( attribute ) === -1;
};

ve.dm.MWBlockImageNode.static.handlesOwnChildren = true;

ve.dm.MWBlockImageNode.static.ignoreChildren = true;

ve.dm.MWBlockImageNode.static.childNodeTypes = [ 'mwImageCaption' ];

ve.dm.MWBlockImageNode.static.matchTagNames = [ 'figure' ];

ve.dm.MWBlockImageNode.static.disallowedAnnotationTypes = [ 'link' ];

ve.dm.MWBlockImageNode.static.classAttributes = {
	'mw-image-border': { borderImage: true },
	'mw-halign-left': { align: 'left' },
	'mw-halign-right': { align: 'right' },
	'mw-halign-center': { align: 'center' },
	'mw-halign-none': { align: 'none' },
	'mw-default-size': { defaultSize: true }
};

ve.dm.MWBlockImageNode.static.toDataElement = function ( domElements, converter ) {
	var dataElement, newDimensions, attributes,
		figure, imgWrapper, img, captionNode, caption,
		classAttr, typeofAttrs, errorIndex, width, height, types,
		mwDataJSON, mwData;

	figure = domElements[ 0 ];
	imgWrapper = figure.children[ 0 ]; // <a> or <span>
	img = imgWrapper.children[ 0 ]; // <img>, <video> or <audio>
	captionNode = figure.children[ 1 ]; // <figcaption> or undefined
	classAttr = figure.getAttribute( 'class' );
	typeofAttrs = figure.getAttribute( 'typeof' ).trim().split( /\s+/ );
	mwDataJSON = figure.getAttribute( 'data-mw' );
	mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	errorIndex = typeofAttrs.indexOf( 'mw:Error' );
	width = img.getAttribute( 'width' );
	height = img.getAttribute( 'height' );

	if ( errorIndex !== -1 ) {
		typeofAttrs.splice( errorIndex, 1 );
	}

	types = this.rdfaToTypes[ typeofAttrs[ 0 ] ];

	attributes = {
		mediaClass: types.mediaClass,
		type: types.frameType,
		src: img.getAttribute( 'src' ) || img.getAttribute( 'poster' ),
		href: imgWrapper.getAttribute( 'href' ),
		resource: img.getAttribute( 'resource' ),
		width: width !== null && width !== '' ? +width : null,
		height: height !== null && height !== '' ? +height : null,
		alt: img.getAttribute( 'alt' ),
		mw: mwData,
		isError: errorIndex !== -1
	};

	this.setClassAttributes( attributes, classAttr );

	attributes.align = attributes.align || 'default';

	// Default-size
	if ( attributes.defaultSize ) {
		// Force wiki-default size for thumb and frameless
		if (
			attributes.type === 'thumb' ||
			attributes.type === 'frameless'
		) {
			// We're going to change .width and .height, store the original
			// values so we can restore them later.
			// FIXME "just" don't modify .width and .height instead
			attributes.originalWidth = attributes.width;
			attributes.originalHeight = attributes.height;
			// Parsoid hands us images with default Wikipedia dimensions
			// rather than default MediaWiki configuration dimensions.
			// We must force local wiki default in edit mode for default
			// size images.
			newDimensions = this.scaleToThumbnailSize( attributes );
			if ( newDimensions ) {
				attributes.width = newDimensions.width;
				attributes.height = newDimensions.height;
			}
		}
	}

	if ( captionNode ) {
		caption = converter.getDataFromDomClean( captionNode, { type: 'mwImageCaption' } );
	} else {
		caption = [
			{ type: 'mwImageCaption' },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			{ type: '/paragraph' },
			{ type: '/mwImageCaption' }
		];
	}

	dataElement = { type: this.name, attributes: attributes };

	this.storeGeneratedContents( dataElement, dataElement.attributes.src, converter.getStore() );

	return [ dataElement ]
		.concat( caption )
		.concat( { type: '/' + this.name } );
};

// TODO: At this moment node is not resizable but when it will be then adding defaultSize class
// should be more conditional.
ve.dm.MWBlockImageNode.static.toDomElements = function ( data, doc, converter ) {
	var width, height, srcAttr,
		dataElement = data[ 0 ],
		mediaClass = dataElement.attributes.mediaClass,
		figure = doc.createElement( 'figure' ),
		imgWrapper = doc.createElement( dataElement.attributes.href ? 'a' : 'span' ),
		img = doc.createElement( this.typesToTags[ mediaClass ] ),
		wrapper = doc.createElement( 'div' ),
		classAttr = this.getClassAttrFromAttributes( dataElement.attributes ),
		captionData = data.slice( 1, -1 );

	// RDFa type
	figure.setAttribute( 'typeof', this.getRdfa( mediaClass, dataElement.attributes.type ) );
	if ( !ve.isEmptyObject( dataElement.attributes.mw ) ) {
		figure.setAttribute( 'data-mw', JSON.stringify( dataElement.attributes.mw ) );
	}

	if ( classAttr ) {
		// eslint-disable-next-line mediawiki/class-doc
		figure.className = classAttr;
	}

	if ( dataElement.attributes.href ) {
		imgWrapper.setAttribute( 'href', dataElement.attributes.href );
	}

	width = dataElement.attributes.width;
	height = dataElement.attributes.height;
	// If defaultSize is set, and was set on the way in, use the original width and height
	// we got on the way in.
	if ( dataElement.attributes.defaultSize ) {
		if ( dataElement.attributes.originalWidth !== undefined ) {
			width = dataElement.attributes.originalWidth;
		}
		if ( dataElement.attributes.originalHeight !== undefined ) {
			height = dataElement.attributes.originalHeight;
		}
	}

	srcAttr = this.typesToSrcAttrs[ mediaClass ];
	if ( srcAttr ) {
		img.setAttribute( srcAttr, dataElement.attributes.src );
	}
	img.setAttribute( 'width', width );
	img.setAttribute( 'height', height );
	img.setAttribute( 'resource', dataElement.attributes.resource );
	if ( typeof dataElement.attributes.alt === 'string' ) {
		img.setAttribute( 'alt', dataElement.attributes.alt );
	}
	figure.appendChild( imgWrapper );
	imgWrapper.appendChild( img );

	// If length of captionData is smaller or equal to 2 it means that there is no caption or that
	// it is empty - in both cases we are going to skip appending <figcaption>.
	if ( captionData.length > 2 ) {
		converter.getDomSubtreeFromData( data.slice( 1, -1 ), wrapper );
		while ( wrapper.firstChild ) {
			figure.appendChild( wrapper.firstChild );
		}
	}
	return [ figure ];
};

/* Methods */

/**
 * Get the caption node of the image.
 *
 * @return {ve.dm.MWImageCaptionNode|null} Caption node, if present
 */
ve.dm.MWBlockImageNode.prototype.getCaptionNode = function () {
	var node = this.children[ 0 ];
	return node instanceof ve.dm.MWImageCaptionNode ? node : null;
};

/**
 * @inheritdoc
 */
ve.dm.MWBlockImageNode.prototype.suppressSlugType = function () {
	// TODO: Have alignment attribute changes trigger a parent branch node re-render
	var align = this.getAttribute( 'align' );
	return align !== 'none' && align !== 'center' ? 'float' : null;
};

/* Registration */

ve.dm.modelRegistry.unregister( ve.dm.BlockImageNode );
ve.dm.modelRegistry.register( ve.dm.MWBlockImageNode );
