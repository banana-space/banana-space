/*!
 * VisualEditor ContentEditable MWInlineImageNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki image node.
 *
 * @class
 * @extends ve.ce.LeafNode
 * @mixins ve.ce.MWImageNode
 *
 * @constructor
 * @param {ve.dm.MWInlineImageNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWInlineImageNode = function VeCeMWInlineImageNode( model, config ) {
	var $image;

	if ( model.getAttribute( 'isError' ) ) {
		this.$element = $( '<a>' )
			.addClass( 'new' )
			.text( model.getFilename() );
		$image = $( [] );
	} else {
		if ( model.getAttribute( 'href' ) ) {
			this.$element = $( '<a>' ).addClass( 'image' );
			$image = $( '<img>' ).appendTo( this.$element );
		} else {
			this.$element = $image = $( '<img>' );
		}
	}

	// Parent constructor
	// this.$element has already been created and styled, so pass through as config.$element
	// The constructor will add more classes to this.$element, such as ve-ce-leafNode
	ve.ce.MWInlineImageNode.super.call( this, model, ve.extendObject( {}, config, { $element: this.$element } ) );

	// Mixin constructors
	ve.ce.MWImageNode.call( this, this.$element, $image );

	$image
		.attr( 'src', this.getResolvedAttribute( 'src' ) )
		.attr( 'width', this.model.getAttribute( 'width' ) )
		.attr( 'height', this.model.getAttribute( 'height' ) );

	if ( this.$element.css( 'direction' ) === 'rtl' ) {
		this.showHandles( [ 'sw' ] );
	} else {
		this.showHandles( [ 'se' ] );
	}

	this.updateClasses();

	// DOM changes
	this.$element.addClass( 've-ce-mwInlineImageNode' );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWInlineImageNode, ve.ce.LeafNode );

// Need to mixin base class as well (T92540)
OO.mixinClass( ve.ce.MWInlineImageNode, ve.ce.GeneratedContentNode );

OO.mixinClass( ve.ce.MWInlineImageNode, ve.ce.MWImageNode );

/* Static Properties */

ve.ce.MWInlineImageNode.static.name = 'mwInlineImage';

/* Methods */

/**
 * Update CSS classes based on current attributes
 */
ve.ce.MWInlineImageNode.prototype.updateClasses = function () {
	var valign = this.model.getAttribute( 'valign' );

	// Border
	this.$element.toggleClass( 'mw-image-border', !!this.model.getAttribute( 'borderImage' ) );

	// default size
	this.$element.toggleClass( 'mw-default-size', !!this.model.getAttribute( 'defaultSize' ) );

	// valign
	if ( valign !== 'default' ) {
		this.$image.css( 'vertical-align', valign );
	}
};

/**
 * @inheritdoc
 */
ve.ce.MWInlineImageNode.prototype.onAttributeChange = function ( key, from, to ) {
	// Mixin method
	ve.ce.MWImageNode.prototype.onAttributeChange.apply( this, arguments );

	if ( key === 'height' || key === 'width' ) {
		to = parseInt( to, 10 );
	}

	if ( from !== to ) {
		switch ( key ) {
			// TODO: 'align', 'src', 'valign', 'border'
			case 'width':
				this.$image.css( 'width', to );
				break;
			case 'height':
				this.$image.css( 'height', to );
				break;
			case 'mediaType':
				this.updateMediaType();
				break;
		}
		this.updateClasses();
	}
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWInlineImageNode );
