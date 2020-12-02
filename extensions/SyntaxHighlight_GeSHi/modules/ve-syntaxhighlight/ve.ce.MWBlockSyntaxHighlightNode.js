/*!
 * VisualEditor ContentEditable MWBlockSyntaxHighlightNode class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki block syntax highlight node.
 *
 * @class
 *
 * @constructor
 */
ve.ce.MWBlockSyntaxHighlightNode = function VeCeMWBlockSyntaxHighlightNode() {
	// Parent method
	ve.ce.MWBlockExtensionNode.super.apply( this, arguments );

	// Mixin method
	ve.ce.MWSyntaxHighlightNode.call( this );
};

OO.inheritClass( ve.ce.MWBlockSyntaxHighlightNode, ve.ce.MWBlockExtensionNode );

OO.mixinClass( ve.ce.MWBlockSyntaxHighlightNode, ve.ce.MWSyntaxHighlightNode );

ve.ce.MWBlockSyntaxHighlightNode.static.name = 'mwBlockSyntaxHighlight';

ve.ce.MWBlockSyntaxHighlightNode.static.primaryCommandName = 'syntaxhighlightDialog';

ve.ce.MWBlockSyntaxHighlightNode.static.getDescription = function ( model ) {
	return ve.getProp( model.getAttribute( 'mw' ), 'attrs', 'lang' );
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWBlockSyntaxHighlightNode );
