/*!
 * VisualEditor HTML sanitization utilities.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/* global DOMPurify */

/**
 * Parse some user HTML into a sanitized node list, making it safe to load on the page
 *
 * @param {string} html HTML
 * @param {boolean} [returnDocument] For internal use only (if true, return whole document)
 * @return {NodeList|HTMLDocument} Sanitized node list (or HTML document, for internal use only)
 */
ve.sanitizeHtml = function ( html, returnDocument ) {
	// TODO: Move MW-specific rules to ve-mw
	var options,
		addTags = [ 'figure-inline' ],
		addAttrs = [
			'srcset',
			// RDFa
			'about', 'rel', 'resource', 'property', 'content', 'datatype', 'typeof'
		];
	options = {
		ADD_TAGS: addTags,
		ADD_ATTR: addAttrs,
		ADD_URI_SAFE_ATTR: addAttrs,
		FORBID_TAGS: [ 'style' ]
	};
	if ( !returnDocument ) {
		options.FORCE_BODY = true;
		options.RETURN_DOM_FRAGMENT = true;
		return DOMPurify.sanitize( html, options ).childNodes;
	}
	options.RETURN_DOM = true;
	return DOMPurify.sanitize( html, options ).ownerDocument;
};

/**
 * Parse some user HTML into a sanitized HTML document, making it safe to load on the page
 *
 * @param {string} html HTML
 * @return {HTMLDocument} Sanitized HTML document
 */
ve.sanitizeHtmlToDocument = function ( html ) {
	return ve.sanitizeHtml( html, true );
};
