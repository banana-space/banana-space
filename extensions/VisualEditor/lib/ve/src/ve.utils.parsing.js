/*!
 * VisualEditor parsing utilities, used when converting HTMLDocuments and strings.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * @class ve
 */

/**
 * Check whether a given DOM element has a block element type.
 *
 * @param {HTMLElement|string} element Element or element name
 * @return {boolean} Element is a block element
 */
ve.isBlockElement = function ( element ) {
	var elementName = typeof element === 'string' ? element : element.nodeName;
	return ve.elementTypes.block.indexOf( elementName.toLowerCase() ) !== -1;
};

/**
 * Check whether a given DOM element is a void element (can't have children).
 *
 * @param {HTMLElement|string} element Element or element name
 * @return {boolean} Element is a void element
 */
ve.isVoidElement = function ( element ) {
	var elementName = typeof element === 'string' ? element : element.nodeName;
	return ve.elementTypes.void.indexOf( elementName.toLowerCase() ) !== -1;
};

ve.elementTypes = {
	block: [
		'div', 'p',
		// Tables
		'table', 'tbody', 'thead', 'tfoot', 'caption', 'th', 'tr', 'td',
		// Lists
		'ul', 'ol', 'li', 'dl', 'dt', 'dd',
		// HTML5 heading content
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hgroup',
		// HTML5 sectioning content
		'article', 'aside', 'body', 'nav', 'section', 'footer', 'header', 'figure',
		'figcaption', 'fieldset', 'details', 'blockquote',
		// Other
		'hr', 'button', 'canvas', 'center', 'col', 'colgroup', 'embed',
		'map', 'object', 'pre', 'progress', 'video'
	],
	void: [
		// https://html.spec.whatwg.org/#void-elements
		'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
		'link', 'meta', 'param', 'source', 'track', 'wbr'
	]
};

/**
 * Create an HTMLDocument from an HTML string.
 *
 * The html parameter is supposed to be a full HTML document with a doctype and an `<html>` tag.
 * If you pass a document fragment, it may or may not work, this is at the mercy of the browser.
 *
 * To create an empty document, pass the empty string.
 *
 * If your input is both valid HTML and valid XML, and you need to work around style
 * normalization bugs in Internet Explorer, use #parseXhtml and #serializeXhtml.
 *
 * @param {string} html HTML string
 * @return {HTMLDocument} Document constructed from the HTML string
 */
ve.createDocumentFromHtml = function ( html ) {
	var newDocument;

	newDocument = ve.createDocumentFromHtmlUsingDomParser( html );
	if ( newDocument ) {
		return newDocument;
	}

	newDocument = ve.createDocumentFromHtmlUsingIframe( html );
	if ( newDocument ) {
		return newDocument;
	}

	return ve.createDocumentFromHtmlUsingInnerHtml( html );
};

/**
 * Private method for creating an HTMLDocument using the DOMParser
 *
 * @private
 * @param {string} html HTML string
 * @return {HTMLDocument|undefined} Document constructed from the HTML string or undefined if it failed
 */
ve.createDocumentFromHtmlUsingDomParser = function ( html ) {
	var newDocument;

	// Support: IE
	// IE doesn't like empty strings
	html = html || '<body></body>';

	try {
		newDocument = new DOMParser().parseFromString( html, 'text/html' );
		if ( newDocument ) {
			return newDocument;
		}
	} catch ( e ) { }
};

/**
 * Private fallback for browsers which don't support DOMParser
 *
 * @private
 * @param {string} html HTML string
 * @return {HTMLDocument|undefined} Document constructed from the HTML string or undefined if it failed
 */
ve.createDocumentFromHtmlUsingIframe = function ( html ) {
	var newDocument, iframe;

	// Here's what this fallback code should look like:
	//
	//     var newDocument = document.implementation.createHtmlDocument( '' );
	//     newDocument.open();
	//     newDocument.write( html );
	//     newDocument.close();
	//     return newDocument;
	//
	// Sadly, it's impossible:
	// * On Firefox 20, calling open()/write() doesn't actually do anything, including writing.
	//   This is reported as Firefox bug 867102.
	// * On Opera 12, calling open()/write() behaves as if called on window.document, replacing the
	//   entire contents of the page with new HTML. This is reported as Opera bug DSK-384486.
	//
	// Funnily, in all of those browsers it's apparently perfectly legal and possible to access the
	// newly created document's DOM itself, including modifying documentElement's innerHTML, which
	// would achieve our goal. But that requires some nasty magic to strip off the <html></html> tag
	// itself, so we're not doing that. (We can't use .outerHTML, either, as the spec disallows
	// assigning to it for the root element.)
	//
	// There is one more way - create an <iframe>, append it to current document, and access its
	// contentDocument. The only browser having issues with that is Opera (sometimes the accessible
	// value is not actually a Document, but something which behaves just like an empty regular
	// object…), so we're detecting that and using the innerHTML hack described above.

	// Support: Firefox 20
	// Support: Opera 12

	html = html || '<body></body>';

	// Create an invisible iframe
	iframe = document.createElement( 'iframe' );
	iframe.setAttribute( 'frameborder', '0' );
	iframe.setAttribute( 'width', '0' );
	iframe.setAttribute( 'height', '0' );
	// Attach it to the document. We have to do this to get a new document out of it
	document.documentElement.appendChild( iframe );
	// Write the HTML to it
	newDocument = ( iframe.contentWindow && iframe.contentWindow.document ) || iframe.contentDocument;
	newDocument.open();
	newDocument.write( html ); // Party like it's 1995!
	newDocument.close();
	// Detach the iframe
	iframe.parentNode.removeChild( iframe );

	if ( !newDocument.documentElement || newDocument.documentElement.cloneNode( false ) === undefined ) {
		// Surprise! The document is not a document! Only happens on Opera.
		// (Or its nodes are not actually nodes, while the document
		// *is* a document. This only happens when debugging with Dragonfly.)
		return;
	}

	return newDocument;
};

/**
 * Private fallback for browsers which don't support iframe technique
 *
 * @private
 * @param {string} html HTML string
 * @return {HTMLDocument} Document constructed from the HTML string
 */
ve.createDocumentFromHtmlUsingInnerHtml = function ( html ) {
	var i, htmlAttributes, wrapper, attributes,
		newDocument = document.implementation.createHTMLDocument( '' );

	html = html || '<body></body>';

	// Carefully unwrap the HTML out of the root node (and doctype, if any).
	newDocument.documentElement.innerHTML = html
		.replace( /^\s*(?:<!doctype[^>]*>)?\s*<html[^>]*>/i, '' )
		.replace( /<\/html>\s*$/i, '' );

	// Preserve <html> attributes, if any
	htmlAttributes = html.match( /<html([^>]*>)/i );
	if ( htmlAttributes && htmlAttributes[ 1 ] ) {
		wrapper = document.createElement( 'div' );
		wrapper.innerHTML = '<div ' + htmlAttributes[ 1 ] + '></div>';
		attributes = wrapper.firstChild.attributes;
		for ( i = 0; i < attributes.length; i++ ) {
			newDocument.documentElement.setAttribute(
				attributes[ i ].name,
				attributes[ i ].value
			);
		}
	}

	return newDocument;
};

/**
 * Take a target document with a possibly relative base URL, and modify it to be absolute.
 * The base URL of the target document is resolved using the base URL of the source document.
 *
 * Note that the fallbackBase parameter will be used if there is no <base> tag, even if
 * the document does have a valid base URL: this is to work around Firefox's behavior of having
 * documents created by DOMParser inherit the base URL of the main document.
 *
 * @param {HTMLDocument} targetDoc Document whose base URL should be resolved
 * @param {HTMLDocument} sourceDoc Document whose base URL should be used for resolution
 * @param {string} [fallbackBase] Base URL to use if resolving the base URL fails or there is no <base> tag
 */
ve.fixBase = function ( targetDoc, sourceDoc, fallbackBase ) {
	var baseNode = targetDoc.getElementsByTagName( 'base' )[ 0 ];
	if ( baseNode ) {
		// Support: Safari
		// In Safari a base node with an invalid href (e.g. protocol-relative)
		// in a document which has been dynamically created results in
		// 'about:blank' rather than '' or null. The base's href will also be '',
		// but that works out just setting the base to fallbackBase, so it's okay.
		if ( !targetDoc.baseURI || targetDoc.baseURI === 'about:blank' ) {
			// <base> tag present but not valid, try resolving its URL
			baseNode.setAttribute( 'href', ve.resolveUrl( baseNode.getAttribute( 'href' ), sourceDoc ) );
			if ( !targetDoc.baseURI && fallbackBase ) {
				// That didn't work, use the fallback
				baseNode.setAttribute( 'href', fallbackBase );
			}
		}
		// Support: Chrome
		// Chrome just entirely ignores <base> tags with a protocol-relative href attribute.
		// Code below is *not a no-op*; reading the href property and setting it back
		// will expand the href *attribute* to use an absolute URL if it was relative.
		// eslint-disable-next-line no-self-assign
		baseNode.href = baseNode.href;
	} else if ( fallbackBase ) {
		// Support: Firefox
		// No <base> tag, add one
		baseNode = targetDoc.createElement( 'base' );
		baseNode.setAttribute( 'href', fallbackBase );
		targetDoc.head.appendChild( baseNode );
	}
};

/**
 * Get the actual inner HTML of a DOM node.
 *
 * In most browsers, .innerHTML is broken and eats newlines in `<pre>` elements, see
 * https://bugzilla.mozilla.org/show_bug.cgi?id=838954 . This function detects this behavior
 * and works around it, to the extent possible. `<pre>\nFoo</pre>` will become `<pre>Foo</pre>`
 * if the browser is broken, but newlines are preserved in all other cases.
 *
 * @param {HTMLElement} element HTML element to get inner HTML of
 * @return {string} Inner HTML
 */
ve.properInnerHtml = function ( element ) {
	return ve.fixupPreBug( element ).innerHTML;
};

/**
 * Get the actual outer HTML of a DOM node.
 *
 * @see ve#properInnerHtml
 * @param {HTMLElement} element HTML element to get outer HTML of
 * @return {string} Outer HTML
 */
ve.properOuterHtml = function ( element ) {
	return ve.fixupPreBug( element ).outerHTML;
};

/**
 * Helper function for #properInnerHtml, #properOuterHtml and #serializeXhtml.
 *
 * Detect whether the browser has broken `<pre>` serialization, and if so return a clone
 * of the node with extra newlines added to make it serialize properly. If the browser is not
 * broken, just return the original node.
 *
 * @param {HTMLElement} element HTML element to fix up
 * @return {HTMLElement} Either element, or a fixed-up clone of it
 */
ve.fixupPreBug = function ( element ) {
	var div, $element;
	if ( ve.isPreInnerHtmlBroken === undefined ) {
		// Test whether newlines in `<pre>` are serialized back correctly
		div = document.createElement( 'div' );
		div.innerHTML = '<pre>\n\n</pre>';
		ve.isPreInnerHtmlBroken = div.innerHTML === '<pre>\n</pre>';
	}

	if ( !ve.isPreInnerHtmlBroken ) {
		return element;
	}

	// Workaround for T44469: if a `<pre>` starts with a newline, that means .innerHTML will
	// screw up and stringify it with one fewer newline. Work around this by adding a newline.
	// If we don't see a leading newline, we still don't know if the original HTML was
	// `<pre>Foo</pre>` or `<pre>\nFoo</pre>`, but that's a syntactic difference, not a
	// semantic one, and handling that is the integration target's job.
	$element = $( element ).clone();
	$element.find( 'pre, textarea, listing' ).each( function () {
		var matches;
		if ( this.firstChild && this.firstChild.nodeType === Node.TEXT_NODE ) {
			matches = this.firstChild.data.match( /^(\r\n|\r|\n)/ );
			if ( matches && matches[ 1 ] ) {
				// Prepend a newline exactly like the one we saw
				this.firstChild.insertData( 0, matches[ 1 ] );
			}
		}
	} );
	return $element.get( 0 );
};

/**
 * Helper function for #transformStyleAttributes.
 *
 * Normalize an attribute value. In compliant browsers, this should be
 * a no-op, but in IE style attributes are normalized on all elements,
 * color and bgcolor attributes are normalized on some elements (like `<tr>`),
 * and width and height attributes are normalized on some elements( like `<table>`).
 *
 * @param {string} name Attribute name
 * @param {string} value Attribute value
 * @param {string} [nodeName='div'] Element name
 * @return {string} Normalized attribute value
 */
ve.normalizeAttributeValue = function ( name, value, nodeName ) {
	var node = document.createElement( nodeName || 'div' );
	node.setAttribute( name, value );
	// Support: IE
	// IE normalizes invalid CSS to empty string, then if you normalize
	// an empty string again it becomes null. Return an empty string
	// instead of null to make this function idempotent.
	return node.getAttribute( name ) || '';
};

/**
 * Helper function for #parseXhtml and #serializeXhtml.
 *
 * Map attributes that are broken in IE to attributes prefixed with data-ve-
 * or vice versa. rowspan/colspan are also broken in Firefox 38 and below, but
 * we don't consider that serious enough to run this function for Firefox.
 *
 * @param {string} html HTML string. Must also be valid XML. Must only have
 *   one root node (e.g. be a complete document).
 * @param {boolean} unmask Map the masked attributes back to their originals
 * @return {string} HTML string modified to mask/unmask broken attributes
 */
ve.transformStyleAttributes = function ( html, unmask ) {
	var xmlDoc, fromAttr, toAttr, i, len,
		maskAttrs = [
			// Support: IE
			'style', // IE normalizes 'color:#ffd' to 'color: rgb(255, 255, 221);'
			'bgcolor', // IE normalizes '#FFDEAD' to '#ffdead'
			'color', // IE normalizes 'Red' to 'red'
			'width', // IE normalizes '240px' to '240'
			'height', // Same as width
			'rowspan', // IE (and FF 38 and below) normalizes rowspan="02" to rowspan="2"
			'colspan' // Same as rowspan
		];

	// Parse the HTML into an XML DOM
	xmlDoc = new DOMParser().parseFromString( html, 'text/xml' );

	// Go through and mask/unmask each attribute on all elements that have it
	for ( i = 0, len = maskAttrs.length; i < len; i++ ) {
		fromAttr = unmask ? 'data-ve-' + maskAttrs[ i ] : maskAttrs[ i ];
		toAttr = unmask ? maskAttrs[ i ] : 'data-ve-' + maskAttrs[ i ];
		// eslint-disable-next-line no-loop-func
		$( xmlDoc ).find( '[' + fromAttr + ']' ).each( function () {
			var toAttrValue, fromAttrNormalized,
				fromAttrValue = this.getAttribute( fromAttr );

			if ( unmask ) {
				this.removeAttribute( fromAttr );

				// If the data-ve- version doesn't normalize to the same value,
				// the attribute must have changed, so don't overwrite it
				fromAttrNormalized = ve.normalizeAttributeValue( toAttr, fromAttrValue, this.nodeName );
				// toAttr can't not be set, but IE returns null if the value was ''
				toAttrValue = this.getAttribute( toAttr ) || '';
				if ( toAttrValue !== fromAttrNormalized ) {
					return;
				}
			}

			this.setAttribute( toAttr, fromAttrValue );
		} );
	}

	// Inject empty text nodes into empty non-void tags to prevent things like <a></a> from
	// being serialized as <a /> and wreaking havoc
	$( xmlDoc ).find( ':empty:not(' + ve.elementTypes.void.join( ',' ) + ')' ).each( function () {
		this.appendChild( xmlDoc.createTextNode( '' ) );
	} );

	// Serialize back to a string
	return new XMLSerializer().serializeToString( xmlDoc );
};

/**
 * Parse an HTML string into an HTML DOM, while masking attributes affected by
 * normalization bugs if a broken browser is detected.
 * Since this process uses an XML parser, the input must be valid XML as well as HTML.
 *
 * @param {string} html HTML string. Must also be valid XML. Must only have
 *   one root node (e.g. be a complete document).
 * @return {HTMLDocument} HTML DOM
 */
ve.parseXhtml = function ( html ) {
	// Support: IE
	// Feature-detect style attribute breakage in IE
	if ( ve.isStyleAttributeBroken === undefined ) {
		ve.isStyleAttributeBroken = ve.normalizeAttributeValue( 'style', 'color:#ffd' ) !== 'color:#ffd';
	}
	if ( ve.isStyleAttributeBroken ) {
		html = ve.transformStyleAttributes( html, false );
	}
	return ve.createDocumentFromHtml( html );
};

/**
 * Serialize an HTML DOM created with #parseXhtml back to an HTML string, unmasking any
 * attributes that were masked.
 *
 * @param {HTMLDocument} doc HTML DOM
 * @return {string} Serialized HTML string
 */
ve.serializeXhtml = function ( doc ) {
	return ve.serializeXhtmlElement( doc.documentElement );
};

/**
 * Serialize an HTML element created with #parseXhtml back to an HTML string, unmasking any
 * attributes that were masked.
 *
 * @param {HTMLElement} element HTML element
 * @return {string} Serialized HTML string
 */
ve.serializeXhtmlElement = function ( element ) {
	var xml;
	// Support: IE
	// Feature-detect style attribute breakage in IE
	if ( ve.isStyleAttributeBroken === undefined ) {
		// Note this doesn't catch the rowspan/colspan normalization in Firefox 38 and below
		// We don't think FF<38 is sufficiently broken to justify going through all this XML parsing stuff
		ve.isStyleAttributeBroken = ve.normalizeAttributeValue( 'style', 'color:#ffd' ) !== 'color:#ffd';
	}
	if ( !ve.isStyleAttributeBroken ) {
		// Support: Firefox
		// Use outerHTML if possible because in Firefox, XMLSerializer URL-encodes
		// hrefs but outerHTML doesn't
		return ve.properOuterHtml( element );
	}

	xml = new XMLSerializer().serializeToString( ve.fixupPreBug( element ) );
	// FIXME T126035: This strips out xmlns as a quick hack
	xml = xml.replace( '<html xmlns="http://www.w3.org/1999/xhtml"', '<html' );
	return ve.transformStyleAttributes( xml, true );
};

/**
 * Resolve a URL relative to a given base.
 *
 * @param {string} url URL to resolve
 * @param {HTMLDocument} base Document whose base URL to use
 * @return {string} Resolved URL
 */
ve.resolveUrl = function ( url, base ) {
	var node = base.createElement( 'a' );
	node.setAttribute( 'href', url );
	// If doc.baseURI isn't set, node.href will be an empty string
	// This is crazy, returning the original URL is better
	return node.href || url;
};
