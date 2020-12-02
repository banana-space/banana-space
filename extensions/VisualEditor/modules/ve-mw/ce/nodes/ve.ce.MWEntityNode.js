/*!
 * VisualEditor ContentEditable MWEntityNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki entity node.
 *
 * @class
 * @extends ve.ce.LeafNode
 * @constructor
 * @param {ve.dm.MWEntityNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWEntityNode = function VeCeMWEntityNode() {
	// Parent constructor
	ve.ce.MWEntityNode.super.apply( this, arguments );

	// DOM changes
	this.$element.addClass( 've-ce-mwEntityNode' );
	// Need CE=false to prevent selection issues
	this.$element.prop( 'contentEditable', 'false' );

	// Events
	this.model.connect( this, { update: 'onUpdate' } );

	// Initialization
	this.onUpdate();
};

/* Inheritance */

OO.inheritClass( ve.ce.MWEntityNode, ve.ce.LeafNode );

/* Static Properties */

ve.ce.MWEntityNode.static.name = 'mwEntity';

/* Methods */

/**
 * Handle model update events.
 */
ve.ce.MWEntityNode.prototype.onUpdate = function () {
	var
		chr = this.model.getAttribute( 'character' ),
		whitespaceHtmlChars = ve.visibleWhitespaceCharacters,
		significantWhitespace = this.getModel().getParent().hasSignificantWhitespace();

	if ( !significantWhitespace && Object.prototype.hasOwnProperty.call( whitespaceHtmlChars, chr ) ) {
		chr = whitespaceHtmlChars[ chr ];
	}

	this.$element.text( chr );
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWEntityNode );
