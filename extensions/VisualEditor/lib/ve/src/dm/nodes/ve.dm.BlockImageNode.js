/*!
 * VisualEditor DataModel BlockImageNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel block image node.
 *
 * @class
 * @extends ve.dm.BranchNode
 * @mixins ve.dm.ImageNode
 * @mixins ve.dm.AlignableNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.BlockImageNode = function VeDmBlockImageNode() {
	// Parent constructor
	ve.dm.BlockImageNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.ImageNode.call( this );
	ve.dm.AlignableNode.call( this );

	// <figure>
	//   <img/>
	//   [<figcaption/>]
	// </figure>
};

/* Inheritance */

OO.inheritClass( ve.dm.BlockImageNode, ve.dm.BranchNode );

OO.mixinClass( ve.dm.BlockImageNode, ve.dm.ImageNode );

// Mixin Alignable's parent class
OO.mixinClass( ve.dm.BlockImageNode, ve.dm.ClassAttributeNode );

OO.mixinClass( ve.dm.BlockImageNode, ve.dm.AlignableNode );

/* Static Properties */

ve.dm.BlockImageNode.static.name = 'blockImage';

ve.dm.BlockImageNode.static.preserveHtmlAttributes = function ( attribute ) {
	var attributes = [ 'class', 'src', 'width', 'height' ];
	return attributes.indexOf( attribute ) === -1;
};

ve.dm.BlockImageNode.static.handlesOwnChildren = true;

ve.dm.BlockImageNode.static.ignoreChildren = true;

ve.dm.BlockImageNode.static.childNodeTypes = [ 'imageCaption' ];

ve.dm.BlockImageNode.static.matchTagNames = [ 'figure' ];

ve.dm.BlockImageNode.static.matchFunction = function ( element ) {
	return element.children[ 0 ] && element.children[ 0 ].nodeName === 'IMG';
};

ve.dm.BlockImageNode.static.toDataElement = function ( domElements, converter ) {
	var dataElement, figure, classAttr, img, caption, attributes, width, height;

	figure = domElements[ 0 ];
	classAttr = figure.getAttribute( 'class' );
	img = figure.children[ 0 ];
	width = img.getAttribute( 'width' );
	height = img.getAttribute( 'height' );
	caption = figure.children[ 1 ];

	attributes = {
		src: img.getAttribute( 'src' ),
		width: width !== null && width !== '' ? +width : null,
		height: height !== null && height !== '' ? +height : null,
		alt: img.getAttribute( 'alt' )
	};

	this.setClassAttributes( attributes, classAttr );

	dataElement = {
		type: this.name,
		attributes: attributes
	};

	if ( !caption ) {
		return [
			dataElement,
			{ type: 'imageCaption' },
			{ type: 'imageCaption' },
			{ type: '/' + this.name }
		];
	} else {
		return [ dataElement ]
			.concat( converter.getDataFromDomClean( caption, { type: 'imageCaption' } ) )
			.concat( [ { type: '/' + this.name } ] );
	}
};

// TODO: Consider using jQuery instead of pure JS.
// TODO: At this moment node is not resizable but when it will be then adding defaultSize class
// should be more conditional.
ve.dm.BlockImageNode.static.toDomElements = function ( data, doc, converter ) {
	var dataElement = data[ 0 ],
		classAttr = this.getClassAttrFromAttributes( dataElement.attributes ),
		figure = doc.createElement( 'figure' ),
		img = doc.createElement( 'img' ),
		wrapper = doc.createElement( 'div' ),
		captionData = data.slice( 1, -1 );

	ve.setDomAttributes( img, dataElement.attributes, [ 'alt', 'src', 'width', 'height' ] );

	figure.appendChild( img );

	if ( classAttr ) {
		// eslint-disable-next-line mediawiki/class-doc
		figure.className = classAttr;
	}

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
 * @return {ve.dm.BlockImageCaptionNode|null} Caption node, if present
 */
ve.dm.BlockImageNode.prototype.getCaptionNode = function () {
	var node = this.children[ 0 ];
	return node instanceof ve.dm.BlockImageCaptionNode ? node : null;
};

/**
 * @inheritdoc
 */
ve.dm.BlockImageNode.prototype.suppressSlugType = function () {
	// TODO: Have alignment attribute changes trigger a parent branch node re-render
	return this.getAttribute( 'align' ) !== 'center' ? 'float' : null;
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.BlockImageNode );
