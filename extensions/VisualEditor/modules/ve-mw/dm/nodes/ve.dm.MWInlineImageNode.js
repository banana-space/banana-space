/*!
 * VisualEditor DataModel MWInlineImage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki image node.
 *
 * @class
 * @extends ve.dm.LeafNode
 * @mixins ve.dm.MWImageNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 */
ve.dm.MWInlineImageNode = function VeDmMWInlineImageNode() {
	// Parent constructor
	ve.dm.MWInlineImageNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.MWImageNode.call( this );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWInlineImageNode, ve.dm.LeafNode );

// Need to mixin base class as well (T92540)
OO.mixinClass( ve.dm.MWInlineImageNode, ve.dm.GeneratedContentNode );

OO.mixinClass( ve.dm.MWInlineImageNode, ve.dm.MWImageNode );

/* Static Properties */

ve.dm.MWInlineImageNode.static.isContent = true;

ve.dm.MWInlineImageNode.static.name = 'mwInlineImage';

ve.dm.MWInlineImageNode.static.preserveHtmlAttributes = function ( attribute ) {
	var attributes = [ 'typeof', 'class', 'src', 'resource', 'width', 'height', 'href', 'data-mw' ];
	return attributes.indexOf( attribute ) === -1;
};

// <span> is here for backwards compatibility with Parsoid content that may be
// stored in RESTBase.  This is now generated as <figure-inline>.  It should
// be safe to remove when verion 1.5 content is no longer acceptable.
ve.dm.MWInlineImageNode.static.matchTagNames = [ 'span', 'figure-inline' ];

ve.dm.MWInlineImageNode.static.disallowedAnnotationTypes = [ 'link' ];

ve.dm.MWInlineImageNode.static.toDataElement = function ( domElements, converter ) {
	var dataElement, attributes, types,
		figureInline = domElements[ 0 ],
		imgWrapper = figureInline.children[ 0 ], // <a> or <span>
		img = imgWrapper.children[ 0 ], // <img>, <video> or <audio>
		typeofAttrs = ( figureInline.getAttribute( 'typeof' ) || '' ).trim().split( /\s+/ ),
		mwDataJSON = figureInline.getAttribute( 'data-mw' ),
		mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {},
		classes = figureInline.getAttribute( 'class' ),
		recognizedClasses = [],
		errorIndex = typeofAttrs.indexOf( 'mw:Error' ),
		width = img.getAttribute( 'width' ),
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
		originalClasses: classes,
		width: width !== null && width !== '' ? +width : null,
		height: height !== null && height !== '' ? +height : null,
		alt: img.getAttribute( 'alt' ),
		mw: mwData,
		isError: errorIndex !== -1
	};

	// Extract individual classes
	classes = typeof classes === 'string' ? classes.trim().split( /\s+/ ) : [];

	// Deal with border flag
	if ( classes.indexOf( 'mw-image-border' ) !== -1 ) {
		attributes.borderImage = true;
		recognizedClasses.push( 'mw-image-border' );
	}

	// Vertical alignment
	attributes.valign = 'default';
	[ 'midde', 'baseline', 'sub', 'super', 'top', 'text-top', 'bottom', 'text-bottom' ].some( function ( valign ) {
		var className = 'mw-valign-' + valign;
		if ( classes.indexOf( className ) !== -1 ) {
			attributes.valign = valign;
			recognizedClasses.push( className );
			return true;
		}
		return false;
	} );

	// Border
	if ( classes.indexOf( 'mw-image-border' ) !== -1 ) {
		attributes.borderImage = true;
		recognizedClasses.push( 'mw-image-border' );
	}

	// Default-size
	if ( classes.indexOf( 'mw-default-size' ) !== -1 ) {
		attributes.defaultSize = true;
		recognizedClasses.push( 'mw-default-size' );
	}

	// Store unrecognized classes so we can restore them on the way out
	attributes.unrecognizedClasses = OO.simpleArrayDifference( classes, recognizedClasses );

	dataElement = { type: this.name, attributes: attributes };

	this.storeGeneratedContents( dataElement, dataElement.attributes.src, converter.getStore() );

	return dataElement;
};

ve.dm.MWInlineImageNode.static.toDomElements = function ( data, doc ) {
	var firstChild, srcAttr,
		mediaClass = data.attributes.mediaClass,
		figureInline = doc.createElement( 'figure-inline' ),
		img = doc.createElement( this.typesToTags[ mediaClass ] ),
		classes = [],
		originalClasses = data.attributes.originalClasses;

	ve.setDomAttributes( img, data.attributes, [ 'width', 'height', 'resource' ] );
	srcAttr = this.typesToSrcAttrs[ mediaClass ];
	if ( srcAttr ) {
		img.setAttribute( srcAttr, data.attributes.src );
	}

	if ( typeof data.attributes.alt === 'string' ) {
		img.setAttribute( 'alt', data.attributes.alt );
	}

	// RDFa type
	figureInline.setAttribute( 'typeof', this.getRdfa( mediaClass, data.attributes.type ) );
	if ( !ve.isEmptyObject( data.attributes.mw ) ) {
		figureInline.setAttribute( 'data-mw', JSON.stringify( data.attributes.mw ) );
	}

	if ( data.attributes.defaultSize ) {
		classes.push( 'mw-default-size' );
	}

	if ( data.attributes.borderImage ) {
		classes.push( 'mw-image-border' );
	}

	if ( data.attributes.valign && data.attributes.valign !== 'default' ) {
		classes.push( 'mw-valign-' + data.attributes.valign );
	}

	if ( data.attributes.unrecognizedClasses ) {
		classes = OO.simpleArrayUnion( classes, data.attributes.unrecognizedClasses );
	}

	if (
		originalClasses &&
		ve.compare( originalClasses.trim().split( /\s+/ ).sort(), classes.sort() )
	) {
		// eslint-disable-next-line mediawiki/class-doc
		figureInline.className = originalClasses;
	} else if ( classes.length > 0 ) {
		// eslint-disable-next-line mediawiki/class-doc
		figureInline.className = classes.join( ' ' );
	}

	if ( data.attributes.href ) {
		firstChild = doc.createElement( 'a' );
		firstChild.setAttribute( 'href', data.attributes.href );
	} else {
		firstChild = doc.createElement( 'span' );
	}

	figureInline.appendChild( firstChild );
	firstChild.appendChild( img );

	return [ figureInline ];
};

/* Registration */

ve.dm.modelRegistry.unregister( ve.dm.InlineImageNode );
ve.dm.modelRegistry.register( ve.dm.MWInlineImageNode );
