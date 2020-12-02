/*!
 * VisualEditor DataModel MWInternalLinkAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki internal link annotation.
 *
 * Example HTML sources:
 *
 *     <a rel="mw:WikiLink">
 *
 * @class
 * @extends ve.dm.LinkAnnotation
 * @constructor
 * @param {Object} element
 */
ve.dm.MWInternalLinkAnnotation = function VeDmMWInternalLinkAnnotation() {
	// Parent constructor
	ve.dm.MWInternalLinkAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWInternalLinkAnnotation, ve.dm.LinkAnnotation );

/* Static Properties */

ve.dm.MWInternalLinkAnnotation.static.name = 'link/mwInternal';

ve.dm.MWInternalLinkAnnotation.static.matchRdfaTypes = [ 'mw:WikiLink', 'mw:MediaLink' ];

// mw:MediaLink to non-existent files come with typeof="mw:Error"
ve.dm.MWInternalLinkAnnotation.static.allowedRdfaTypes = [ 'mw:Error' ];

ve.dm.MWInternalLinkAnnotation.static.toDataElement = function ( domElements, converter ) {
	var targetData,
		resource = domElements[ 0 ].getAttribute( 'resource' );

	if ( resource ) {
		targetData = mw.libs.ve.parseParsoidResourceName( resource );
	} else {
		targetData = mw.libs.ve.getTargetDataFromHref(
			domElements[ 0 ].getAttribute( 'href' ),
			converter.getTargetHtmlDocument()
		);

		if ( !targetData.isInternal ) {
			return ve.dm.MWExternalLinkAnnotation.static.toDataElement( domElements, converter );
		}
	}

	return {
		type: this.name,
		attributes: {
			title: targetData.title,
			normalizedTitle: this.normalizeTitle( targetData.title ),
			lookupTitle: this.getLookupTitle( targetData.title ),
			origTitle: targetData.rawTitle
		}
	};
};

/**
 * Build element from a given mw.Title and raw title
 *
 * @param {mw.Title} title The title to link to.
 * @param {string} [rawTitle] String from which the title was created
 * @return {Object} The element.
 */
ve.dm.MWInternalLinkAnnotation.static.dataElementFromTitle = function ( title, rawTitle ) {
	var element,
		target = title.toText(),
		namespaceIds = mw.config.get( 'wgNamespaceIds' );

	if ( title.getNamespaceId() === namespaceIds.file || title.getNamespaceId() === namespaceIds.category ) {
		// File: or Category: link
		// We have to prepend a colon so this is interpreted as a link
		// rather than an image inclusion or categorization
		target = ':' + target;
	}
	if ( title.getFragment() ) {
		target += '#' + title.getFragment();
	}

	element = {
		type: this.name,
		attributes: {
			title: target,
			normalizedTitle: this.normalizeTitle( title ),
			lookupTitle: this.getLookupTitle( title )
		}
	};

	if ( rawTitle ) {
		element.attributes.origTitle = rawTitle;
	}

	return element;
};

/**
 * Build a ve.dm.MWInternalLinkAnnotation from a given mw.Title.
 *
 * @param {mw.Title} title The title to link to.
 * @param {string} [rawTitle] String from which the title was created
 * @return {ve.dm.MWInternalLinkAnnotation} The annotation.
 */
ve.dm.MWInternalLinkAnnotation.static.newFromTitle = function ( title, rawTitle ) {
	var element = this.dataElementFromTitle( title, rawTitle );

	return new ve.dm.MWInternalLinkAnnotation( element );
};

ve.dm.MWInternalLinkAnnotation.static.toDomElements = function () {
	var parentResult = ve.dm.LinkAnnotation.static.toDomElements.apply( this, arguments );
	parentResult[ 0 ].setAttribute( 'rel', 'mw:WikiLink' );
	return parentResult;
};

ve.dm.MWInternalLinkAnnotation.static.getHref = function ( dataElement ) {
	var encodedTitle,
		title = dataElement.attributes.title,
		origTitle = dataElement.attributes.origTitle;
	if ( origTitle !== undefined && mw.libs.ve.decodeURIComponentIntoArticleTitle( origTitle ) === title ) {
		// Restore href from origTitle
		encodedTitle = origTitle;
	} else {
		// Don't escape slashes in the title; they represent subpages.
		// Don't escape colons to work around a Parsoid bug with interwiki links (T95850)
		// TODO: Maybe this should be using mw.util.wikiUrlencode(), which also doesn't escape them?
		encodedTitle = title.split( /(\/|#|:)/ ).map( function ( part ) {
			if ( part === '/' || part === '#' || part === ':' ) {
				return part;
			} else {
				return encodeURIComponent( part );
			}
		} ).join( '' );
	}
	if ( encodedTitle.slice( 0, 1 ) === '#' ) {
		// Special case: For a newly created link to a #fragment with
		// no explicit title use the current title as prefix (T218581)
		// TODO: Pass a 'doc' param to getPageName
		encodedTitle = ve.init.target.getPageName() + encodedTitle;
	}
	return './' + encodedTitle;
};

/**
 * Normalize title for comparison purposes.
 * E.g. capitalisation and underscores.
 *
 * @param {string|mw.Title} original Original title
 * @return {string} Normalized title, or the original string if it is invalid
 */
ve.dm.MWInternalLinkAnnotation.static.normalizeTitle = function ( original ) {
	var title = original instanceof mw.Title ? original : mw.Title.newFromText( original );
	if ( !title ) {
		return original;
	}
	return title.getPrefixedText() + ( title.getFragment() !== null ? '#' + title.getFragment() : '' );
};

/**
 * Normalize title for lookup (search suggestion, existence) purposes.
 *
 * @param {string|mw.Title} original Original title
 * @return {string} Normalized title, or the original string if it is invalid
 */
ve.dm.MWInternalLinkAnnotation.static.getLookupTitle = function ( original ) {
	var title = original instanceof mw.Title ? original : mw.Title.newFromText( original );
	if ( !title ) {
		return original;
	}
	return title.getPrefixedText();
};

/**
 * Get the fragment for a title
 *
 * @static
 * @param {string|mw.Title} original Original title
 * @return {string|null} Fragment for the title, or null if it was invalid or missing
 */
ve.dm.MWInternalLinkAnnotation.static.getFragment = function ( original ) {
	var title = original instanceof mw.Title ? original : mw.Title.newFromText( original );
	if ( !title ) {
		return null;
	}
	return title.getFragment();
};

ve.dm.MWInternalLinkAnnotation.static.describeChange = function ( key, change ) {
	if ( key === 'title' ) {
		return ve.htmlMsg( 'visualeditor-changedesc-link-href', this.wrapText( 'del', change.from ), this.wrapText( 'ins', change.to ) );
	}
	return null;
};

/* Methods */

/**
 * @inheritdoc
 */
ve.dm.MWInternalLinkAnnotation.prototype.getComparableObject = function () {
	return {
		type: this.getType(),
		normalizedTitle: this.getAttribute( 'normalizedTitle' )
	};
};

/**
 * @inheritdoc
 */
ve.dm.MWInternalLinkAnnotation.prototype.getComparableHtmlAttributes = function () {
	// Assume that wikitext never adds meaningful html attributes for comparison purposes,
	// although ideally this should be decided by Parsoid (Bug T95028).
	return {};
};

/**
 * @inheritdoc
 */
ve.dm.MWInternalLinkAnnotation.prototype.getDisplayTitle = function () {
	return this.getAttribute( 'normalizedTitle' );
};

/**
 * Convenience wrapper for .getFragment() on the current element.
 *
 * @see #static-getFragment
 * @return {string} Fragment for the title, or an empty string if it was invalid
 */
ve.dm.MWInternalLinkAnnotation.prototype.getFragment = function () {
	return this.constructor.static.getFragment( this.getAttribute( 'normalizedTitle' ) );
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWInternalLinkAnnotation );
