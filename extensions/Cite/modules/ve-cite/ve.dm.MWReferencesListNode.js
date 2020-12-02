/*!
 * VisualEditor DataModel MWReferencesListNode class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * DataModel MediaWiki references list node.
 *
 * @class
 * @extends ve.dm.BranchNode
 * @mixins ve.dm.FocusableNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.MWReferencesListNode = function VeDmMWReferencesListNode() {
	// Parent constructor
	ve.dm.MWReferencesListNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.FocusableNode.call( this );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWReferencesListNode, ve.dm.BranchNode );

OO.mixinClass( ve.dm.MWReferencesListNode, ve.dm.FocusableNode );

/* Methods */

ve.dm.MWReferencesListNode.prototype.isEditable = function () {
	return !this.getAttribute( 'templateGenerated' );
};

/* Static members */

ve.dm.MWReferencesListNode.static.name = 'mwReferencesList';

ve.dm.MWReferencesListNode.static.handlesOwnChildren = true;

ve.dm.MWReferencesListNode.static.ignoreChildren = true;

ve.dm.MWReferencesListNode.static.matchTagNames = null;

ve.dm.MWReferencesListNode.static.matchRdfaTypes = [ 'mw:Extension/references', 'mw:Transclusion' ];

ve.dm.MWReferencesListNode.static.matchFunction = function ( domElement ) {
	function isRefList( el ) {
		return el && el.nodeType === Node.ELEMENT_NODE && ( el.getAttribute( 'typeof' ) || '' ).indexOf( 'mw:Extension/references' ) !== -1;
	}
	// If the template generated only a reference list, treat it as a ref list (T52769)
	return isRefList( domElement ) ||
		// A div-wrapped reference list
		( domElement.children.length === 1 && isRefList( domElement.children[ 0 ] ) );
};

ve.dm.MWReferencesListNode.static.preserveHtmlAttributes = false;

ve.dm.MWReferencesListNode.static.toDataElement = function ( domElements, converter ) {
	var referencesListData, contentsDiv, contentsData, refListNode,
		mwDataJSON, mwData, refGroup, responsiveAttr, listGroup,
		type = domElements[ 0 ].getAttribute( 'typeof' ) || '',
		templateGenerated = type.indexOf( 'mw:Transclusion' ) !== -1,
		isResponsiveDefault = mw.config.get( 'wgCiteResponsiveReferences' );

	// We may have matched a mw:Transclusion wrapping a reference list, so pull out the refListNode
	if ( type.indexOf( 'mw:Extension/references' ) !== -1 ) {
		refListNode = domElements[ 0 ];
	} else {
		refListNode = domElements[ 0 ].querySelectorAll( '[typeof*="mw:Extension/references"]' )[ 0 ];
	}

	mwDataJSON = refListNode.getAttribute( 'data-mw' );
	mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	refGroup = ve.getProp( mwData, 'attrs', 'group' ) || '';
	responsiveAttr = ve.getProp( mwData, 'attrs', 'responsive' );
	listGroup = 'mwReference/' + refGroup;

	referencesListData = {
		type: this.name,
		attributes: {
			mw: mwData,
			originalMw: mwDataJSON,
			refGroup: refGroup,
			listGroup: listGroup,
			isResponsive: responsiveAttr !== undefined ? responsiveAttr !== '0' : isResponsiveDefault,
			templateGenerated: templateGenerated
		}
	};
	if ( mwData.body && mwData.body.html && !templateGenerated ) {
		// Process the nodes in .body.html as if they were this node's children
		// Don't process template-generated reflists, that mangles the content (T209493)
		contentsDiv = domElements[ 0 ].ownerDocument.createElement( 'div' );
		contentsDiv.innerHTML = mwData.body.html;
		contentsData = converter.getDataFromDomClean( contentsDiv );
		referencesListData = [ referencesListData ]
			.concat( contentsData )
			.concat( [ { type: '/' + this.name } ] );
	}
	return referencesListData;
};

ve.dm.MWReferencesListNode.static.toDomElements = function ( data, doc, converter ) {
	var el, els, mwData, contentsHtml, originalHtml, nextIndex, nextElement, modelNode, viewNode,
		isResponsiveDefault = mw.config.get( 'wgCiteResponsiveReferences' ),
		isForParser = converter.isForParser(),
		wrapper = doc.createElement( 'div' ),
		originalHtmlWrapper = doc.createElement( 'div' ),
		dataElement = data[ 0 ],
		attrs = dataElement.attributes,
		originalMw = attrs.originalMw,
		originalMwData = originalMw && JSON.parse( originalMw ),
		originalResponsiveAttr = ve.getProp( originalMwData, 'attrs', 'responsive' ),
		contentsData = data.slice( 1, -1 );

	// If we are sending a template generated ref back to Parsoid, output it as a template.
	// This works because the dataElement already has mw, originalMw and originalDomIndex properties.
	if ( attrs.templateGenerated && isForParser ) {
		return ve.dm.MWTransclusionNode.static.toDomElements.call( this, dataElement, doc, converter );
	}

	if ( !isForParser ) {
		// Output needs to be read so re-render
		modelNode = ve.dm.nodeFactory.createFromElement( dataElement );
		modelNode = new ve.dm.MWReferencesListNode( dataElement );
		// Build from original doc's internal list to get all refs (T186407)
		modelNode.setDocument( converter.originalDocInternalList.getDocument() );
		viewNode = ve.ce.nodeFactory.createFromModel( modelNode );
		viewNode.modified = true;
		viewNode.update();
		els = [ doc.createElement( 'div' ) ];
		els[ 0 ].appendChild( viewNode.$reflist[ 0 ] );
		// Destroy the view node so it doesn't try to update the DOM node later (e.g. updateDebounced)
		viewNode.destroy();
	} else if ( dataElement.originalDomElementsHash !== undefined ) {
		// If there's more than 1 element, preserve entire array, not just first element
		els = ve.copyDomElements( converter.getStore().value( dataElement.originalDomElementsHash ), doc );
	} else {
		els = [ doc.createElement( 'div' ) ];
	}

	mwData = attrs.mw ? ve.copy( attrs.mw ) : {};

	mwData.name = 'references';

	if ( attrs.refGroup ) {
		ve.setProp( mwData, 'attrs', 'group', attrs.refGroup );
	} else if ( mwData.attrs ) {
		delete mwData.attrs.refGroup;
	}

	if ( !(
		// The original "responsive" attribute hasn't had its meaning changed
		originalResponsiveAttr !== undefined && ( originalResponsiveAttr !== '0' ) === attrs.isResponsive
	) ) {
		if ( attrs.isResponsive !== isResponsiveDefault ) {
			ve.setProp( mwData, 'attrs', 'responsive', attrs.isResponsive ? '' : '0' );
		} else if ( mwData.attrs ) {
			delete mwData.attrs.responsive;
		}
	}

	if ( mwData.autoGenerated ) {
		// This was an autogenerated reflist. We need to check whether changes
		// have been made which make that no longer true. The reflist dialog
		// handles unsetting this if changes to the properties have been made.
		// Here we want to work out if it has been moved away from the end of
		// the document.
		// TODO: it would be better to do this without needing to fish through
		// the converter's linear data. Use the DM tree instead?
		nextIndex = converter.documentData.indexOf( data[ data.length - 1 ] ) + 1;
		while ( ( nextElement = converter.documentData[ nextIndex ] ) ) {
			if ( nextElement.type[ 0 ] !== '/' ) {
				break;
			}
			nextIndex++;
		}
		if ( nextElement && nextElement.type !== 'internalList' ) {
			delete mwData.autoGenerated;
		}
	}

	el = els[ 0 ];
	el.setAttribute( 'typeof', 'mw:Extension/references' );

	if ( contentsData.length > 2 ) {
		converter.getDomSubtreeFromData( data.slice( 1, -1 ), wrapper );
		contentsHtml = wrapper.innerHTML; // Returns '' if wrapper is empty
		originalHtml = ve.getProp( mwData, 'body', 'html' ) || '';
		originalHtmlWrapper.innerHTML = originalHtml;
		// Only set body.html if contentsHtml and originalHtml are actually different
		if ( !originalHtmlWrapper.isEqualNode( wrapper ) ) {
			ve.setProp( mwData, 'body', 'html', contentsHtml );
		}
	}

	// If mwData and originalMw are the same, use originalMw to prevent reserialization.
	// Reserialization has the potential to reorder keys and so change the DOM unnecessarily
	if ( originalMw && ve.compare( mwData, originalMwData ) ) {
		el.setAttribute( 'data-mw', originalMw );
	} else {
		el.setAttribute( 'data-mw', JSON.stringify( mwData ) );
	}

	return els;
};

ve.dm.MWReferencesListNode.static.describeChange = function ( key, change ) {
	if ( key === 'refGroup' ) {
		if ( !change.from ) {
			return ve.htmlMsg( 'cite-ve-changedesc-reflist-group-to', this.wrapText( 'ins', change.to ) );
		} else if ( !change.to ) {
			return ve.htmlMsg( 'cite-ve-changedesc-reflist-group-from', this.wrapText( 'del', change.from ) );
		} else {
			return ve.htmlMsg( 'cite-ve-changedesc-reflist-group-both', this.wrapText( 'del', change.from ), this.wrapText( 'ins', change.to ) );
		}
	}

	if ( key === 'isResponsive' ) {
		if ( change.from ) {
			return ve.msg( 'cite-ve-changedesc-reflist-responsive-unset' );
		}
		return ve.msg( 'cite-ve-changedesc-reflist-responsive-set' );
	}

	if ( key === 'originalMw' ) {
		return null;
	}

	return null;
};

ve.dm.MWReferencesListNode.static.getHashObject = function ( dataElement ) {
	return {
		type: dataElement.type,
		attributes: {
			refGroup: dataElement.attributes.refGroup,
			listGroup: dataElement.attributes.listGroup,
			isResponsive: dataElement.attributes.isResponsive,
			templateGenerated: dataElement.attributes.templateGenerated
		}
	};
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWReferencesListNode );
