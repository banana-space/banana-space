/*!
 * VisualEditor ContentEditable MWBlockImageNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki image node.
 *
 * @class
 * @extends ve.ce.BranchNode
 * @mixins ve.ce.MWImageNode
 *
 * @constructor
 * @param {ve.dm.MWBlockImageNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWBlockImageNode = function VeCeMWBlockImageNode() {
	var type, isError, $image, $focusable;

	// Parent constructor
	ve.ce.MWBlockImageNode.super.apply( this, arguments );

	type = this.model.getAttribute( 'type' );
	isError = this.model.getAttribute( 'isError' );

	// DOM Hierarchy for MWBlockImageNode:
	//   <figure> this.$element (ve-ce-mwBlockImageNode-{type})
	//     <a> this.$a
	//       <img> this.$image
	//     <figcaption> ve.ce.MWImageCaptionNode

	// Build DOM:
	if ( isError ) {
		$image = $( [] );
		this.$a = $( '<a>' )
			.addClass( 'new' )
			.text( this.model.getFilename() );
		$focusable = this.$a;
	} else {
		$image = $( '<img>' )
			.attr( 'src', this.getResolvedAttribute( 'src' ) );
		this.$a = $( '<a>' )
			.addClass( 'image' )
			.attr( 'href', this.getResolvedAttribute( 'href' ) )
			.append( $image );
		$focusable = $image;
	}

	this.$element
		.prepend( this.$a )
		// The following classes are used here:
		// * ve-ce-mwBlockImageNode-type-thumb
		// * ve-ce-mwBlockImageNode-type-frame
		// * ve-ce-mwBlockImageNode-type-frameless
		// * ve-ce-mwBlockImageNode-type-none
		.addClass( 've-ce-mwBlockImageNode ve-ce-mwBlockImageNode-type-' + type )
		// 'typeof' should appear with the proper Parsoid-generated
		// type. The model deals with converting it
		.attr( 'typeof', this.model.getRdfa() );

	// Mixin constructors
	ve.ce.MWImageNode.call( this, $focusable, $image );

	// Initialization
	this.updateSize();
};

/* Inheritance */

OO.inheritClass( ve.ce.MWBlockImageNode, ve.ce.BranchNode );

// Need to mixin base class as well (T92540)
OO.mixinClass( ve.ce.MWBlockImageNode, ve.ce.GeneratedContentNode );

OO.mixinClass( ve.ce.MWBlockImageNode, ve.ce.MWImageNode );

/* Static Properties */

ve.ce.MWBlockImageNode.static.name = 'mwBlockImage';

ve.ce.MWBlockImageNode.static.tagName = 'figure';

ve.ce.MWBlockImageNode.static.renderHtmlAttributes = false;

ve.ce.MWBlockImageNode.static.transition = false;

ve.ce.MWBlockImageNode.static.cssClasses = {
	default: {
		left: 'mw-halign-left',
		right: 'mw-halign-right',
		center: 'mw-halign-center',
		none: 'mw-halign-none'
	},
	none: {
		left: 'mw-halign-left',
		right: 'mw-halign-right',
		center: 'mw-halign-center',
		none: 'mw-halign-none'
	}
};

/* Methods */

/**
 * Update CSS classes based on alignment and type
 *
 * @param {string} [oldAlign] The old alignment, for removing classes
 */
ve.ce.MWBlockImageNode.prototype.updateClasses = function ( oldAlign ) {
	var alignClass,
		align = this.model.getAttribute( 'align' ),
		type = this.model.getAttribute( 'type' );

	if ( oldAlign && oldAlign !== align ) {
		// Remove previous alignment
		// See static.cssClasses
		// eslint-disable-next-line mediawiki/class-doc
		this.$element
			.removeClass( this.getCssClass( 'none', oldAlign ) )
			.removeClass( this.getCssClass( 'default', oldAlign ) );
	}

	if ( type !== 'none' && type !== 'frameless' ) {
		alignClass = this.getCssClass( 'default', align );
		this.$image.addClass( 've-ce-mwBlockImageNode-thumbimage' );
	} else {
		alignClass = this.getCssClass( 'none', align );
		this.$image.removeClass( 've-ce-mwBlockImageNode-thumbimage' );
	}
	// eslint-disable-next-line mediawiki/class-doc
	this.$element.addClass( alignClass );

	// Border
	this.$element.toggleClass( 'mw-image-border', !!this.model.getAttribute( 'borderImage' ) );

	switch ( alignClass ) {
		case 'mw-halign-right':
			this.showHandles( [ 'sw' ] );
			break;
		case 'mw-halign-left':
			this.showHandles( [ 'se' ] );
			break;
		case 'mw-halign-center':
			this.showHandles( [ 'sw', 'se' ] );
			break;
		default:
			this.showHandles();
			break;
	}
};

/**
 * Redraw the image and its wrappers at the specified dimensions
 *
 * The current dimensions from the model are used if none are specified.
 *
 * @param {Object} [dimensions] Dimension object containing width & height
 */
ve.ce.MWBlockImageNode.prototype.updateSize = function ( dimensions ) {
	var isError = this.model.getAttribute( 'isError' ),
		type = this.model.getAttribute( 'type' ),
		borderImage = this.model.getAttribute( 'borderImage' ),
		hasBorderOrFrame = ( type !== 'none' && type !== 'frameless' ) || borderImage;

	if ( isError ) {
		this.$element.css( { width: '', height: '' } );
		return;
	}

	if ( !dimensions ) {
		dimensions = {
			width: this.model.getAttribute( 'width' ),
			height: this.model.getAttribute( 'height' )
		};
	}

	this.$image.css( dimensions );

	// Make sure $element is sharing the dimensions, otherwise 'middle' and 'none'
	// positions don't work properly
	this.$element.css( {
		width: dimensions.width + ( hasBorderOrFrame ? 2 : 0 ),
		height: hasBorderOrFrame ? 'auto' : dimensions.height
	} );
	this.$element.toggleClass( 'mw-default-size', !!this.model.getAttribute( 'defaultSize' ) );
};

/**
 * Get the right CSS class to use for alignment
 *
 * @param {string} type 'none' or 'default'
 * @param {string} alignment 'left', 'right', 'center', 'none' or 'default'
 * @return {string} CSS class
 */
ve.ce.MWBlockImageNode.prototype.getCssClass = function ( type, alignment ) {
	// TODO use this.model.getAttribute( 'type' ) etc., see T54065
	// Default is different between RTL and LTR wikis:
	if ( type === 'default' && alignment === 'default' ) {
		if ( this.getModel().getDocument().getDir() === 'rtl' ) {
			return 'mw-halign-left';
		} else {
			return 'mw-halign-right';
		}
	} else {
		return this.constructor.static.cssClasses[ type ][ alignment ];
	}
};

/**
 * Override the default onSetup to add direction-dependent
 * classes to the image thumbnail.
 */
ve.ce.MWBlockImageNode.prototype.onSetup = function () {
	// Parent method
	ve.ce.MWBlockImageNode.super.prototype.onSetup.call( this );

	this.updateClasses();
};

/**
 * @inheritdoc
 */
ve.ce.MWBlockImageNode.prototype.onAttributeChange = function ( key, from, to ) {
	// Mixin method
	ve.ce.MWImageNode.prototype.onAttributeChange.apply( this, arguments );

	if ( key === 'height' || key === 'width' ) {
		to = parseInt( to, 10 );
	}

	if ( from !== to ) {
		switch ( key ) {
			case 'align':
				this.updateClasses( from );
				break;
			case 'src':
				this.$image.attr( 'src', this.getResolvedAttribute( 'src' ) );
				break;
			case 'width':
				this.updateSize( {
					width: to,
					height: this.model.getAttribute( 'height' )
				} );
				break;
			case 'height':
				this.updateSize( {
					width: this.model.getAttribute( 'width' ),
					height: to
				} );
				break;
			case 'type':
				// See constructor for types used
				// eslint-disable-next-line mediawiki/class-doc
				this.$element
					.removeClass( 've-ce-mwBlockImageNode-type-' + from )
					.addClass( 've-ce-mwBlockImageNode-type-' + to )
					.attr( 'typeof', this.model.getRdfa() );

				this.updateClasses();
				this.updateSize();
				break;
			case 'borderImage':
				this.updateClasses();
				this.updateSize();
				break;
			// Other image attributes if they exist
			case 'alt':
				if ( !to ) {
					this.$image.removeAttr( key );
				} else {
					this.$image.attr( key, to );
				}
				break;
			case 'mediaType':
				this.updateMediaType();
				break;
			case 'defaultSize':
				this.$element.toggleClass( 'mw-default-size', to );
				break;
		}
	}
};

/**
 * @param {Object} dimensions New dimensions
 */
ve.ce.MWBlockImageNode.prototype.onResizableResizing = function ( dimensions ) {
	if ( !this.outline ) {
		ve.ce.ResizableNode.prototype.onResizableResizing.call( this, dimensions );

		this.updateSize( dimensions );
	}
};

ve.ce.MWBlockImageNode.prototype.getDomPosition = function () {
	// We need to override this because this.$element can have children other than renderings of child
	// CE nodes (specifically, the image itself, this.$a), which throws the calculations out of whack.
	// Luckily, MWBlockImageNode is very simple and can contain at most one other node: its caption,
	// which is always inserted at the end.
	var domNode = this.$element.last()[ 0 ];
	return {
		node: domNode,
		offset: domNode.childNodes.length
	};
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWBlockImageNode );
