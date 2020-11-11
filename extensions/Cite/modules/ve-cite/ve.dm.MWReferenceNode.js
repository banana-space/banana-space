/*!
 * VisualEditor DataModel MWReferenceNode class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * DataModel MediaWiki reference node.
 *
 * @class
 * @extends ve.dm.LeafNode
 * @mixins ve.dm.FocusableNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 */
ve.dm.MWReferenceNode = function VeDmMWReferenceNode() {
	// Parent constructor
	ve.dm.MWReferenceNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.FocusableNode.call( this );

	// Event handlers
	this.connect( this, {
		root: 'onRoot',
		unroot: 'onUnroot',
		attributeChange: 'onAttributeChange'
	} );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWReferenceNode, ve.dm.LeafNode );

OO.mixinClass( ve.dm.MWReferenceNode, ve.dm.FocusableNode );

/* Static members */

ve.dm.MWReferenceNode.static.name = 'mwReference';

ve.dm.MWReferenceNode.static.matchTagNames = null;

ve.dm.MWReferenceNode.static.matchRdfaTypes = [ 'mw:Extension/ref' ];

ve.dm.MWReferenceNode.static.allowedRdfaTypes = [ 'dc:references' ];

ve.dm.MWReferenceNode.static.isContent = true;

ve.dm.MWReferenceNode.static.blacklistedAnnotationTypes = [ 'link' ];

/**
 * Regular expression for parsing the listKey attribute
 * @static
 * @property {RegExp}
 * @inheritable
 */
ve.dm.MWReferenceNode.static.listKeyRegex = /^(auto|literal)\/(.*)$/;

ve.dm.MWReferenceNode.static.toDataElement = function ( domElements, converter ) {
	var dataElement, mwDataJSON, mwData, reflistItemId, body, refGroup, listGroup, autoKeyed, listKey, queueResult, listIndex, contentsUsed;

	function getReflistItemHtml( id ) {
		var elem = converter.getHtmlDocument().getElementById( id );
		return elem && elem.innerHTML || '';
	}

	mwDataJSON = domElements[ 0 ].getAttribute( 'data-mw' );
	mwData = mwDataJSON ? JSON.parse( mwDataJSON ) : {};
	reflistItemId = mwData.body && mwData.body.id;
	body = ( mwData.body && mwData.body.html ) ||
		( reflistItemId && getReflistItemHtml( reflistItemId ) ) ||
		'';
	refGroup = mwData.attrs && mwData.attrs.group || '';
	listGroup = this.name + '/' + refGroup;
	autoKeyed = !mwData.attrs || mwData.attrs.name === undefined;
	listKey = autoKeyed ?
		'auto/' + converter.internalList.getNextUniqueNumber() :
		'literal/' + mwData.attrs.name;
	queueResult = converter.internalList.queueItemHtml( listGroup, listKey, body );
	listIndex = queueResult.index;
	contentsUsed = ( body !== '' && queueResult.isNew );

	dataElement = {
		type: this.name,
		attributes: {
			mw: mwData,
			originalMw: mwDataJSON,
			listIndex: listIndex,
			listGroup: listGroup,
			listKey: listKey,
			refGroup: refGroup,
			contentsUsed: contentsUsed
		}
	};
	if ( reflistItemId ) {
		dataElement.attributes.refListItemId = reflistItemId;
	}
	return dataElement;
};

ve.dm.MWReferenceNode.static.toDomElements = function ( dataElement, doc, converter ) {
	var itemNodeHtml, originalHtml, mwData, i, iLen, keyedNodes, setContents, contentsAlreadySet,
		originalMw, listKeyParts, name, group, $link,
		isForClipboard = converter.isForClipboard(),
		el = doc.createElement( 'sup' ),
		itemNodeWrapper = doc.createElement( 'div' ),
		originalHtmlWrapper = doc.createElement( 'div' ),
		itemNode = converter.internalList.getItemNode( dataElement.attributes.listIndex ),
		itemNodeRange = itemNode.getRange();

	el.setAttribute( 'typeof', 'mw:Extension/ref' );

	mwData = dataElement.attributes.mw ? ve.copy( dataElement.attributes.mw ) : {};
	mwData.name = 'ref';

	setContents = dataElement.attributes.contentsUsed;

	keyedNodes = converter.internalList
		.getNodeGroup( dataElement.attributes.listGroup )
		.keyedNodes[ dataElement.attributes.listKey ];

	if ( setContents ) {
		// Check if a previous node has already set the content. If so, we don't overwrite this
		// node's contents.
		contentsAlreadySet = false;
		if ( keyedNodes ) {
			for ( i = 0, iLen = keyedNodes.length; i < iLen; i++ ) {
				if ( keyedNodes[ i ].element === dataElement ) {
					break;
				}
				if ( keyedNodes[ i ].element.attributes.contentsUsed ) {
					contentsAlreadySet = true;
					break;
				}
			}
		}
	} else {
		// Check if any other nodes with this key provided content. If not
		// then we attach the contents to the first reference with this key

		// Check that this is the first reference with its key
		if ( keyedNodes && dataElement === keyedNodes[ 0 ].element ) {
			setContents = true;
			// Check no other reference originally defined the contents
			// As this is keyedNodes[0] we can start at 1
			for ( i = 1, iLen = keyedNodes.length; i < iLen; i++ ) {
				if ( keyedNodes[ i ].element.attributes.contentsUsed ) {
					setContents = false;
					break;
				}
			}
		}
	}

	if ( setContents && !contentsAlreadySet ) {
		converter.getDomSubtreeFromData(
			itemNode.getDocument().getFullData( itemNodeRange, true ),
			itemNodeWrapper
		);
		itemNodeHtml = itemNodeWrapper.innerHTML; // Returns '' if itemNodeWrapper is empty
		originalHtml = ve.getProp( mwData, 'body', 'html' ) ||
			( ve.getProp( mwData, 'body', 'id' ) !== undefined && itemNode.getAttribute( 'originalHtml' ) ) ||
			'';
		originalHtmlWrapper.innerHTML = originalHtml;
		// Only set body.html if itemNodeHtml and originalHtml are actually different,
		// or we are writing the clipboard for use in another VE instance
		if ( isForClipboard || !originalHtmlWrapper.isEqualNode( itemNodeWrapper ) ) {
			ve.setProp( mwData, 'body', 'html', itemNodeHtml );
		}
	}

	// If we have no internal item data for this reference, don't let it get pasted into
	// another VE document. T110479
	if ( isForClipboard && itemNodeRange.isCollapsed() ) {
		el.setAttribute( 'data-ve-ignore', 'true' );
	}

	// Generate name
	listKeyParts = dataElement.attributes.listKey.match( this.listKeyRegex );
	if ( listKeyParts[ 1 ] === 'auto' ) {
		// Only render a name if this key was reused
		if ( keyedNodes.length > 1 ) {
			// Allocate a unique list key, then strip the 'literal/'' prefix
			name = converter.internalList.getUniqueListKey(
				dataElement.attributes.listGroup,
				dataElement.attributes.listKey,
				// Generate a name starting with ':' to distinguish it from normal names
				'literal/:'
			).slice( 'literal/'.length );
		} else {
			name = undefined;
		}
	} else {
		// Use literal name
		name = listKeyParts[ 2 ];
	}
	// Set name
	if ( name !== undefined ) {
		ve.setProp( mwData, 'attrs', 'name', name );
	}

	// Set or clear group
	if ( dataElement.attributes.refGroup !== '' ) {
		ve.setProp( mwData, 'attrs', 'group', dataElement.attributes.refGroup );
	} else if ( mwData.attrs ) {
		delete mwData.attrs.refGroup;
	}

	// If mwAttr and originalMw are the same, use originalMw to prevent reserialization,
	// unless we are writing the clipboard for use in another VE instance
	// Reserialization has the potential to reorder keys and so change the DOM unnecessarily
	originalMw = dataElement.attributes.originalMw;
	if ( !isForClipboard && originalMw && ve.compare( mwData, JSON.parse( originalMw ) ) ) {
		el.setAttribute( 'data-mw', originalMw );

		// Return the original DOM elements if possible
		if ( dataElement.originalDomElementsHash !== undefined ) {
			return ve.copyDomElements( converter.getStore().value( dataElement.originalDomElementsHash ), doc );
		}
	} else {
		el.setAttribute( 'data-mw', JSON.stringify( mwData ) );

		// HTML for the external clipboard, it will be ignored by the converter
		group = this.getGroup( dataElement );
		$link = $( '<a>', doc ).css(
			'counterReset', 'mw-Ref ' + this.getIndex( dataElement, converter.internalList )
		);
		if ( group ) {
			$link.attr( 'data-mw-group', this.getGroup( dataElement ) );
		}
		$( el ).addClass( 'mw-ref' ).append(
			$link.append(
				$( '<span>', doc ).addClass( 'mw-reflink-text' ).text( this.getIndexLabel( dataElement, converter.internalList ) )
			)
		);
	}

	return [ el ];
};

ve.dm.MWReferenceNode.static.remapInternalListIndexes = function ( dataElement, mapping, internalList ) {
	var listKeyParts;
	// Remap listIndex
	dataElement.attributes.listIndex = mapping[ dataElement.attributes.listIndex ];

	// Remap listKey if it was automatically generated
	listKeyParts = dataElement.attributes.listKey.match( this.listKeyRegex );
	if ( listKeyParts[ 1 ] === 'auto' ) {
		dataElement.attributes.listKey = 'auto/' + internalList.getNextUniqueNumber();
	}
};

ve.dm.MWReferenceNode.static.remapInternalListKeys = function ( dataElement, internalList ) {
	var suffix = '';
	// Try name, name2, name3, ... until unique
	while ( internalList.keys.indexOf( dataElement.attributes.listKey + suffix ) !== -1 ) {
		suffix = suffix ? suffix + 1 : 2;
	}
	if ( suffix ) {
		dataElement.attributes.listKey = dataElement.attributes.listKey + suffix;
	}
};

/**
 * Gets the index for the reference
 *
 * @static
 * @param {Object} dataElement Element data
 * @param {ve.dm.InternalList} internalList Internal list
 * @return {number} Index
 */
ve.dm.MWReferenceNode.static.getIndex = function ( dataElement, internalList ) {
	var listIndex, listGroup, position,
		overrideIndex = ve.getProp( dataElement, 'internal', 'overrideIndex' );

	if ( overrideIndex ) {
		return overrideIndex;
	}

	listIndex = dataElement.attributes.listIndex;
	listGroup = dataElement.attributes.listGroup;
	position = internalList.getIndexPosition( listGroup, listIndex );

	return position + 1;
};

/**
 * Gets the group for the reference
 *
 * @static
 * @param {Object} dataElement Element data
 * @return {string} Group
 */
ve.dm.MWReferenceNode.static.getGroup = function ( dataElement ) {
	return dataElement.attributes.refGroup;
};

/**
 * Gets the index label for the reference
 *
 * @static
 * @param {Object} dataElement Element data
 * @param {ve.dm.InternalList} internalList Internal list
 * @return {string} Reference label
 */
ve.dm.MWReferenceNode.static.getIndexLabel = function ( dataElement, internalList ) {
	var refGroup = dataElement.attributes.refGroup,
		index = ve.dm.MWReferenceNode.static.getIndex( dataElement, internalList );

	return '[' + ( refGroup ? refGroup + ' ' : '' ) + index + ']';
};

/**
 * @inheritdoc
 */
ve.dm.MWReferenceNode.static.cloneElement = function () {
	var clone = ve.dm.MWReferenceNode.super.static.cloneElement.apply( this, arguments );
	delete clone.attributes.contentsUsed;
	delete clone.attributes.mw;
	delete clone.attributes.originalMw;
	return clone;
};

/**
 * @inheritdoc
 */
ve.dm.MWReferenceNode.static.describeChange = function ( key, change ) {
	if ( key === 'refGroup' ) {
		if ( change.from ) {
			if ( change.to ) {
				return ve.msg( 'cite-ve-changedesc-ref-group-both', change.from, change.to );
			} else {
				return ve.msg( 'cite-ve-changedesc-ref-group-from', change.from );
			}
		}
		return ve.msg( 'cite-ve-changedesc-ref-group-to', change.to );
	}
	if ( key === 'refListItemId' ) {
		return ve.msg( 'cite-ve-changedesc-reflist-item-id' );
	}
};

/* Methods */

/**
 * Don't allow reference nodes to be edited if we can't find their contents.
 *
 * @inheritdoc
 */
ve.dm.MWReferenceNode.prototype.isEditable = function () {
	var internalItem = this.getInternalItem();
	return internalItem && internalItem.getLength() > 0;
};

/**
 * Gets the internal item node associated with this node
 *
 * @return {ve.dm.InternalItemNode} Item node
 */
ve.dm.MWReferenceNode.prototype.getInternalItem = function () {
	return this.getDocument().getInternalList().getItemNode( this.getAttribute( 'listIndex' ) );
};

/**
 * Gets the index for the reference
 *
 * @return {number} Index
 */
ve.dm.MWReferenceNode.prototype.getIndex = function () {
	return this.constructor.static.getIndex( this.element, this.getDocument().getInternalList() );
};

/**
 * Gets the group for the reference
 *
 * @return {string} Group
 */
ve.dm.MWReferenceNode.prototype.getGroup = function () {
	return this.constructor.static.getGroup( this.element );
};

/**
 * Gets the index label for the reference
 *
 * @return {string} Reference label
 */
ve.dm.MWReferenceNode.prototype.getIndexLabel = function () {
	return this.constructor.static.getIndexLabel( this.element, this.getDocument().getInternalList() );
};

/**
 * Handle the node being attached to the root
 */
ve.dm.MWReferenceNode.prototype.onRoot = function () {
	this.addToInternalList();
};

/**
 * Handle the node being detached from the root
 *
 * @param {ve.dm.DocumentNode} oldRoot Old document root
 */
ve.dm.MWReferenceNode.prototype.onUnroot = function ( oldRoot ) {
	if ( this.getDocument().getDocumentNode() === oldRoot ) {
		this.removeFromInternalList();
	}
};

/**
 * Register the node with the internal list
 */
ve.dm.MWReferenceNode.prototype.addToInternalList = function () {
	if ( this.getRoot() === this.getDocument().getDocumentNode() ) {
		this.registeredListGroup = this.element.attributes.listGroup;
		this.registeredListKey = this.element.attributes.listKey;
		this.registeredListIndex = this.element.attributes.listIndex;
		this.getDocument().getInternalList().addNode(
			this.registeredListGroup,
			this.registeredListKey,
			this.registeredListIndex,
			this
		);
	}
};

/**
 * Unregister the node from the internal list
 */
ve.dm.MWReferenceNode.prototype.removeFromInternalList = function () {
	if ( !this.registeredListGroup ) {
		// Don't try to remove if we haven't been added in the first place.
		return;
	}
	this.getDocument().getInternalList().removeNode(
		this.registeredListGroup,
		this.registeredListKey,
		this.registeredListIndex,
		this
	);
};

ve.dm.MWReferenceNode.prototype.onAttributeChange = function ( key, from, to ) {
	if (
		( key !== 'listGroup' && key !== 'listKey' ) ||
		( key === 'listGroup' && this.registeredListGroup === to ) ||
		( key === 'listKey' && this.registeredListKey === to )
	) {
		return;
	}

	// Need the old list keys and indexes, so we register them in addToInternalList
	// They've already been updated in this.element.attributes before this code runs
	this.removeFromInternalList();
	this.addToInternalList();
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWReferenceNode );
