/*!
 * VisualEditor DataModel MWSyntaxHighlightNode class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki syntax highlight node.
 *
 * @class
 * @abstract
 *
 * @constructor
 */
ve.dm.MWSyntaxHighlightNode = function VeDmMWSyntaxHighlightNode() {
};

/* Inheritance */

OO.initClass( ve.dm.MWSyntaxHighlightNode );

/* Static members */

ve.dm.MWSyntaxHighlightNode.static.name = 'mwSyntaxHighlight';

ve.dm.MWSyntaxHighlightNode.static.extensionName = 'syntaxhighlight';

ve.dm.MWSyntaxHighlightNode.static.getMatchRdfaTypes = function () {
	return [ 'mw:Extension/syntaxhighlight', 'mw:Extension/source' ];
};

/* Static methods */

/**
 * @inheritdoc
 */
ve.dm.MWSyntaxHighlightNode.static.toDataElement = function ( domElements, converter ) {
	// Parent method
	var isInline = this.isHybridInline( domElements, converter ),
		type = isInline ? 'mwInlineSyntaxHighlight' : 'mwBlockSyntaxHighlight',
		dataElement = ve.dm.MWExtensionNode.static.toDataElement.call( this, domElements, converter, type );

	return dataElement;
};

( function () {
	var supportedLanguages = [ undefined ],
		geshiToPygmentsMap,
		pygmentsToAceMap;

	/**
	 * Register supported Pygments languages.
	 *
	 * @param {Array} languages
	 */
	ve.dm.MWSyntaxHighlightNode.static.addPygmentsLanguages = function ( languages ) {
		ve.batchPush( supportedLanguages, languages );
	};

	/**
	 * Register map from Geshi to pygments lexer names.
	 *
	 * @param {Array} map
	 */
	ve.dm.MWSyntaxHighlightNode.static.addGeshiToPygmentsMap = function ( map ) {
		geshiToPygmentsMap = map;
		ve.batchPush( supportedLanguages, Object.keys( geshiToPygmentsMap ) );
	};

	/**
	 * Register a map from pygments to Ace lexer names.
	 *
	 * @param {Array} map
	 */
	ve.dm.MWSyntaxHighlightNode.static.addPygmentsToAceMap = function ( map ) {
		pygmentsToAceMap = map;
	};

	/**
	 * Converts a (valid) language as recognized by the SyntaxHighlight wikicode
	 * to a compatible Ace lexer name (to be used by CodeEditor)
	 *
	 * @param {string} language Language name
	 * @return {string} The name of the ace lexer
	 */
	ve.dm.MWSyntaxHighlightNode.static.convertLanguageToAce = function ( language ) {
		language = geshiToPygmentsMap[ language ] || language;
		return ( pygmentsToAceMap[ language ] || language ).toLowerCase();
	};

	/**
	 * Check if a language is supported
	 *
	 * @param {string} language Language name
	 * @return {boolean} The language is supported
	 */
	ve.dm.MWSyntaxHighlightNode.static.isLanguageSupported = function ( language ) {
		return supportedLanguages.indexOf( language || undefined ) !== -1;
	};

	/**
	 * Get an array of all languages (both Pygments and former GeSHi names)
	 *
	 * @return {Array} All currently supported languages
	 */
	ve.dm.MWSyntaxHighlightNode.static.getLanguages = function () {
		return supportedLanguages.slice();
	};
}() );

/* Methods */

/**
 * Check if the node's current language is supported
 *
 * @return {boolean} The language is supported
 */
ve.dm.MWSyntaxHighlightNode.prototype.isLanguageSupported = function () {
	return this.constructor.static.isLanguageSupported( this.getLanguage() );
};

ve.dm.MWSyntaxHighlightNode.prototype.getLanguage = function () {
	return this.getAttribute( 'mw' ).attrs.lang;
};

/* Concrete subclasses */

ve.dm.MWBlockSyntaxHighlightNode = function VeDmMWBlockSyntaxHighlightNode() {
	// Parent method
	ve.dm.MWBlockExtensionNode.super.apply( this, arguments );

	// Mixin method
	ve.dm.MWSyntaxHighlightNode.call( this );
};

OO.inheritClass( ve.dm.MWBlockSyntaxHighlightNode, ve.dm.MWBlockExtensionNode );

OO.mixinClass( ve.dm.MWBlockSyntaxHighlightNode, ve.dm.MWSyntaxHighlightNode );

ve.dm.MWBlockSyntaxHighlightNode.static.name = 'mwBlockSyntaxHighlight';

ve.dm.MWBlockSyntaxHighlightNode.static.tagName = 'div';

ve.dm.MWInlineSyntaxHighlightNode = function VeDmMWInlineSyntaxHighlightNode() {
	// Parent method
	ve.dm.MWInlineExtensionNode.super.apply( this, arguments );

	// Mixin method
	ve.dm.MWSyntaxHighlightNode.call( this );
};

OO.inheritClass( ve.dm.MWInlineSyntaxHighlightNode, ve.dm.MWInlineExtensionNode );

OO.mixinClass( ve.dm.MWInlineSyntaxHighlightNode, ve.dm.MWSyntaxHighlightNode );

ve.dm.MWInlineSyntaxHighlightNode.static.name = 'mwInlineSyntaxHighlight';

ve.dm.MWInlineSyntaxHighlightNode.static.tagName = 'code';

ve.dm.MWInlineSyntaxHighlightNode.static.isContent = true;

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWBlockSyntaxHighlightNode );
ve.dm.modelRegistry.register( ve.dm.MWInlineSyntaxHighlightNode );
