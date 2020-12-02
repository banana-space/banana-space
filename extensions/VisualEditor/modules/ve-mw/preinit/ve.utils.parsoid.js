/*!
 * Parsoid utilities.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

mw.libs.ve = mw.libs.ve || {};

/**
 * Resolve a URL relative to a given base.
 *
 * Copied from ve.resolveUrl
 *
 * @param {string} url URL to resolve
 * @param {HTMLDocument} base Document whose base URL to use
 * @return {string} Resolved URL
 */
mw.libs.ve.resolveUrl = function ( url, base ) {
	var node = base.createElement( 'a' );
	node.setAttribute( 'href', url );
	// If doc.baseURI isn't set, node.href will be an empty string
	// This is crazy, returning the original URL is better
	return node.href || url;
};

/**
 * Decode a URI component into a mediawiki article title
 *
 * N.B. Illegal article titles can result from fairly reasonable input (e.g. "100%25beef");
 * see https://phabricator.wikimedia.org/T137847 .
 *
 * @param {string} s String to decode
 * @param {boolean} [preserveUnderscores] Don't convert underscores to spaces
 * @return {string} Decoded string, or original string if decodeURIComponent failed
 */
mw.libs.ve.decodeURIComponentIntoArticleTitle = function ( s, preserveUnderscores ) {
	try {
		s = decodeURIComponent( s );
	} catch ( e ) {
		return s;
	}
	if ( preserveUnderscores ) {
		return s;
	}
	return s.replace( /_/g, ' ' );
};

/**
 * Unwrap Parsoid sections
 *
 * @param {HTMLElement} element Parent element, e.g. document body
 * @param {string} [keepSection] Section to keep
 */
mw.libs.ve.unwrapParsoidSections = function ( element, keepSection ) {
	Array.prototype.forEach.call( element.querySelectorAll( 'section[data-mw-section-id]' ), function ( section ) {
		var parent = section.parentNode,
			sectionId = section.getAttribute( 'data-mw-section-id' );
		// Copy section ID to first child (should be a heading)
		// Pseudo-sections (with negative section IDs) may not have a heading
		if ( sectionId !== null && +sectionId > 0 ) {
			section.firstChild.setAttribute( 'data-mw-section-id', sectionId );
		}
		if ( keepSection !== undefined && sectionId === keepSection ) {
			return;
		}
		while ( section.firstChild ) {
			parent.insertBefore( section.firstChild, section );
		}
		parent.removeChild( section );
	} );
};

/**
 * Strip legacy (non-HTML5) IDs; typically found as section IDs inside
 * headings.
 *
 * @param {HTMLElement} element Parent element, e.g. document body
 */
mw.libs.ve.stripParsoidFallbackIds = function ( element ) {
	Array.prototype.forEach.call( element.querySelectorAll( 'span[typeof="mw:FallbackId"][id]:empty' ), function ( legacySpan ) {
		legacySpan.parentNode.removeChild( legacySpan );
	} );
};

mw.libs.ve.restbaseIdRegExp = /^mw[a-zA-Z0-9\-_]{2,6}$/;

mw.libs.ve.stripRestbaseIds = function ( doc ) {
	var restbaseIdRegExp = mw.libs.ve.restbaseIdRegExp;
	Array.prototype.forEach.call( doc.querySelectorAll( '[id^="mw"]' ), function ( element ) {
		if ( element.id.match( restbaseIdRegExp ) ) {
			element.removeAttribute( 'id' );
		}
	} );
};

/**
 * Fix fragment links which should be relative to the current document
 *
 * This prevents these links from trying to navigate to another page,
 * or open in a new window.
 *
 * Call this after ve.targetLinksToNewWindow, as it removes the target attribute.
 * Call this after LinkCache.styleParsoidElements, as it breaks that method by including the query string.
 *
 * @param {HTMLElement} container Parent element, e.g. document body
 * @param {mw.Title} docTitle Current title, only links to this title will be normalized
 * @param {string} [prefix] Prefix to add to fragment and target ID to avoid collisions
 */
mw.libs.ve.fixFragmentLinks = function ( container, docTitle, prefix ) {
	var docTitleText = docTitle.getPrefixedText();
	prefix = prefix || '';
	Array.prototype.forEach.call( container.querySelectorAll( 'a[href*="#"]' ), function ( el ) {
		var target, title,
			fragment = new mw.Uri( el.href ).fragment,
			targetData = mw.libs.ve.getTargetDataFromHref( el.href, el.ownerDocument );

		if ( targetData.isInternal ) {
			title = mw.Title.newFromText( targetData.title );
			if ( title && title.getPrefixedText() === docTitleText ) {

				if ( !fragment ) {
					// Special case for empty fragment, even if prefix set
					el.setAttribute( 'href', '#' );
				} else {
					if ( prefix ) {
						target = container.querySelector( '#' + $.escapeSelector( fragment ) );
						// There may be multiple links to a specific target, so check the target
						// hasn't already been fixed (in which case it would be null)
						if ( target ) {
							target.setAttribute( 'id', prefix + fragment );
						}
					}
					el.setAttribute( 'href', '#' + prefix + fragment );
				}
				el.removeAttribute( 'target' );

			}
		}
	} );
};

/**
 * Parse URL to get title it points to.
 *
 * @param {string} href
 * @param {HTMLDocument|string} doc Document whose base URL to use, or base URL as a string.
 * @return {Object} Information about the given href
 * @return {string} return.title
 *    The title of the internal link, else the original href if href is external
 * @return {string} return.rawTitle
 *    The title without URL decoding and underscore normalization applied
 * @return {boolean} return.isInternal
 *    True if the href pointed to the local wiki, false if href is external
 */
mw.libs.ve.getTargetDataFromHref = function ( href, doc ) {
	var relativeBase, relativeBaseRegex, relativeHref, isInternal, matches, data, uri;

	function regexEscape( str ) {
		return str.replace( /([.?*+^$[\]\\(){}|-])/g, '\\$1' );
	}

	// Protocol relative href
	relativeHref = href.replace( /^https?:/i, '' );
	// Paths without a host portion are assumed to be internal
	isInternal = !/^\/\//.test( relativeHref );

	// Check if this matches the server's article path
	// Protocol relative base
	relativeBase = mw.libs.ve.resolveUrl( mw.config.get( 'wgArticlePath' ), doc ).replace( /^https?:/i, '' );
	relativeBaseRegex = new RegExp( regexEscape( relativeBase ).replace( regexEscape( '$1' ), '(.*)' ) );
	matches = relativeHref.match( relativeBaseRegex );
	if ( matches && matches[ 1 ].split( '#' )[ 0 ].indexOf( '?' ) === -1 ) {
		// Take the relative path
		href = matches[ 1 ];
		isInternal = true;
	}

	// Check if this matches the server's script path (as used by red links)
	relativeBase = mw.libs.ve.resolveUrl( mw.config.get( 'wgScript' ), doc ).replace( /^https?:/i, '' );
	if ( relativeHref.indexOf( relativeBase ) === 0 ) {
		uri = new mw.Uri( relativeHref );
		if ( Object.keys( uri.query ).length === 1 && uri.query.title ) {
			href = uri.query.title;
			isInternal = true;
		} else if ( Object.keys( uri.query ).length === 3 && uri.query.title && uri.query.action === 'edit' && uri.query.redlink === '1' ) {
			href = uri.query.title;
			isInternal = true;
		} else {
			href = relativeHref;
			isInternal = false;
		}
	}

	// This href doesn't necessarily come from Parsoid (and it might not have the "./" prefix), but
	// this method will work fine.
	data = mw.libs.ve.parseParsoidResourceName( href );
	data.isInternal = isInternal;
	return data;
};

/**
 * Expand a string of the form jquery.foo,bar|jquery.ui.baz,quux to
 * an array of module names like [ 'jquery.foo', 'jquery.bar',
 * 'jquery.ui.baz', 'jquery.ui.quux' ]
 *
 * Implementation of ResourceLoaderContext::expandModuleNames
 * TODO: Consider upstreaming this to MW core.
 *
 * @param {string} moduleNames Packed module name list
 * @return {string[]} Array of module names
 */
mw.libs.ve.expandModuleNames = function ( moduleNames ) {
	var modules = [];

	moduleNames.split( '|' ).forEach( function ( group ) {
		var matches, prefix, suffixes;
		if ( group.indexOf( ',' ) === -1 ) {
			// This is not a set of modules in foo.bar,baz notation
			// but a single module
			modules.push( group );
		} else {
			// This is a set of modules in foo.bar,baz notation
			matches = group.match( /(.*)\.([^.]*)/ );
			if ( !matches ) {
				// Prefixless modules, i.e. without dots
				modules = modules.concat( group.split( ',' ) );
			} else {
				// We have a prefix and a bunch of suffixes
				prefix = matches[ 1 ];
				suffixes = matches[ 2 ].split( ',' ); // [ 'bar', 'baz' ]
				suffixes.forEach( function ( suffix ) {
					modules.push( prefix + '.' + suffix );
				} );
			}
		}
	} );
	return modules;
};

/**
 * Split Parsoid resource name into the href prefix and the page title.
 *
 * @param {string} resourceName Resource name, from a `href` or `resource` attribute
 * @return {Object} Object with the following properties:
 * @return {string} return.title Full page title in text form (with namespace, and spaces instead of underscores)
 * @return {string} return.rawTitle The title without URL decoding and underscore normalization applied
 */
mw.libs.ve.parseParsoidResourceName = function ( resourceName ) {
	// Resource names are always prefixed with './' to prevent the MediaWiki namespace from being
	// interpreted as a URL protocol, consider e.g. 'href="./File:Foo.png"'.
	// (We accept input without the prefix, so this can also take plain page titles.)
	var matches = resourceName.match( /^(\.\/|)(.*)$/ );
	return {
		// '%' and '?' are valid in page titles, but normally URI-encoded. This also changes underscores
		// to spaces.
		title: mw.libs.ve.decodeURIComponentIntoArticleTitle( matches[ 2 ] ),
		rawTitle: matches[ 2 ]
	};
};

/**
 * Extract the page title from a Parsoid resource name.
 *
 * @param {string} resourceName Resource name, from a `href` or `resource` attribute
 * @return {string} Full page title in text form (with namespace, and spaces instead of underscores)
 */
mw.libs.ve.normalizeParsoidResourceName = function ( resourceName ) {
	return mw.libs.ve.parseParsoidResourceName( resourceName ).title;
};
