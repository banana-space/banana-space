/*!
 * VisualEditor DataModel MWExternalLinkAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki external link annotation.
 *
 * Example HTML sources:
 *
 *     <a rel="mw:ExtLink" class="external free" href="http://example.com">http://example.com</a>
 *     <a rel="mw:ExtLink" class="external text" href="http://example.com">Link content</a>
 *     <a rel="mw:ExtLink" class="external autonumber" href="http://example.com"></a>
 *     <a rel="mw:WikiLink/Interwiki" href="http://en.wikipedia.org/wiki/Foo">en:Foo</a>
 *
 * Each example is semantically slightly different, but they don't need special treatment (yet).
 *
 * @class
 * @extends ve.dm.LinkAnnotation
 * @constructor
 * @param {Object} element
 */
ve.dm.MWExternalLinkAnnotation = function VeDmMWExternalLinkAnnotation() {
	// Parent constructor
	ve.dm.MWExternalLinkAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWExternalLinkAnnotation, ve.dm.LinkAnnotation );

/* Static Properties */

ve.dm.MWExternalLinkAnnotation.static.name = 'link/mwExternal';

ve.dm.MWExternalLinkAnnotation.static.toDataElement = function ( domElements, converter ) {
	var dataElement, annotation,
		domElement = domElements[ 0 ],
		type = domElement.getAttribute( 'rel' ) || domElement.getAttribute( 'typeof' ) || domElement.getAttribute( 'property' ) || '',
		types = type.trim().split( /\s+/ );

	// If the link doesn't have a known RDFa type, auto-convert it to the correct type (internal/external/span)
	if ( types.indexOf( 'mw:ExtLink' ) === -1 && types.indexOf( 'mw:WikiLink/Interwiki' ) === -1 ) {
		if ( domElement.hasAttribute( 'href' ) ) {
			annotation = ve.ui.MWLinkAction.static.getLinkAnnotation( domElement.getAttribute( 'href' ), converter.getHtmlDocument() );
			return annotation.element;
		} else {
			// Convert href-less links to a plain span, which will get stripped by sanitization
			return ve.dm.SpanAnnotation.static.toDataElement.apply( ve.dm.SpanAnnotation.static, arguments );
		}
	}

	// Parent method
	dataElement = ve.dm.MWExternalLinkAnnotation.super.static.toDataElement.apply( this, arguments );

	dataElement.attributes.rel = type;
	return dataElement;
};

ve.dm.MWExternalLinkAnnotation.static.toDomElements = function ( dataElement, doc, converter ) {
	// Parent method
	var domElements = ve.dm.MWExternalLinkAnnotation.super.static.toDomElements.apply( this, arguments );

	if ( converter.isForPreview() ) {
		// Ensure there is an 'external' class when rendering, as this may have been created locally.
		domElements[ 0 ].setAttribute( 'class', 'external' );
	}

	domElements[ 0 ].setAttribute( 'rel', dataElement.attributes.rel || 'mw:ExtLink' );
	return domElements;
};

ve.dm.MWExternalLinkAnnotation.static.describeChange = function ( key, change ) {
	if ( key === 'href' ) {
		return ve.htmlMsg( 'visualeditor-changedesc-link-href', this.wrapText( 'del', change.from ), this.wrapText( 'ins', change.to ) );
	}
	return null;
};

/* Methods */

/**
 * @return {Object}
 */
ve.dm.MWExternalLinkAnnotation.prototype.getComparableObject = function () {
	return {
		type: this.getType(),
		href: this.getAttribute( 'href' ),
		rel: this.getAttribute( 'rel' ) || 'mw:ExtLink'
	};
};

/**
 * @inheritdoc
 */
ve.dm.MWExternalLinkAnnotation.prototype.getComparableHtmlAttributes = function () {
	// Assume that wikitext never adds meaningful html attributes for comparison purposes,
	// although ideally this should be decided by Parsoid (Bug T95028).
	return {};
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWExternalLinkAnnotation );
