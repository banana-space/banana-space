/*!
 * VisualEditor UserInterface MWReferenceSearchWidget class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Creates an ve.ui.MWReferenceSearchWidget object.
 *
 * @class
 * @extends OO.ui.SearchWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWReferenceSearchWidget = function VeUiMWReferenceSearchWidget( config ) {
	// Configuration initialization
	config = ve.extendObject( {
		placeholder: ve.msg( 'cite-ve-reference-input-placeholder' )
	}, config );

	// Parent constructor
	ve.ui.MWReferenceSearchWidget.super.call( this, config );

	// Properties
	this.index = [];
	this.indexEmpty = true;
	this.built = false;

	// Initialization
	this.$element.addClass( 've-ui-mwReferenceSearchWidget' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceSearchWidget, OO.ui.SearchWidget );

/* Methods */

/**
 * Handle query change events.
 *
 * @method
 * @param {string} value New value
 */
ve.ui.MWReferenceSearchWidget.prototype.onQueryChange = function () {
	// Parent method
	ve.ui.MWReferenceSearchWidget.super.prototype.onQueryChange.call( this );

	// Populate
	this.addResults();
};

/**
 * Set the internal list and check if it contains any references
 *
 * @param {ve.dm.InternalList} internalList Internal list
 */
ve.ui.MWReferenceSearchWidget.prototype.setInternalList = function ( internalList ) {
	var i, iLen, groupNames, groupName, groups = internalList.getNodeGroups();

	if ( this.results.findSelectedItem() ) {
		this.results.findSelectedItem().setSelected( false );
	}

	this.internalList = internalList;
	this.internalList.connect( this, { update: 'onInternalListUpdate' } );
	this.internalList.getListNode().connect( this, { update: 'onListNodeUpdate' } );

	groupNames = Object.keys( groups );
	for ( i = 0, iLen = groupNames.length; i < iLen; i++ ) {
		groupName = groupNames[ i ];
		if ( groupName.lastIndexOf( 'mwReference/' ) !== 0 ) {
			continue;
		}
		if ( groups[ groupName ].indexOrder.length ) {
			this.indexEmpty = false;
			return;
		}
	}
	this.indexEmpty = true;
};

/**
 * Handle the updating of the InternalList object.
 *
 * This will occur after a document transaction.
 *
 * @method
 * @param {string[]} groupsChanged A list of groups which have changed in this transaction
 */
ve.ui.MWReferenceSearchWidget.prototype.onInternalListUpdate = function ( groupsChanged ) {
	var i, len;
	for ( i = 0, len = groupsChanged.length; i < len; i++ ) {
		if ( groupsChanged[ i ].indexOf( 'mwReference/' ) === 0 ) {
			this.built = false;
			break;
		}
	}
};

/**
 * Handle the updating of the InternalListNode.
 *
 * This will occur after changes to any InternalItemNode.
 *
 * @method
 */
ve.ui.MWReferenceSearchWidget.prototype.onListNodeUpdate = function () {
	this.built = false;
};

/**
 * Build a searchable index of references.
 *
 * @method
 */
ve.ui.MWReferenceSearchWidget.prototype.buildIndex = function () {
	var n, i, iLen, j, jLen, refModel, group, groupName, groupNames, view, text, firstNodes, indexOrder,
		refGroup, refNode, matches, name, citation,
		groups = this.internalList.getNodeGroups();

	if ( this.built ) {
		return;
	}

	function extractAttrs() {
		text += ' ' + this.getAttribute( 'href' );
	}

	this.index = [];
	groupNames = Object.keys( groups ).sort();

	for ( i = 0, iLen = groupNames.length; i < iLen; i++ ) {
		groupName = groupNames[ i ];
		if ( groupName.lastIndexOf( 'mwReference/' ) !== 0 ) {
			continue;
		}
		group = groups[ groupName ];
		firstNodes = group.firstNodes;
		indexOrder = group.indexOrder;

		n = 0;
		for ( j = 0, jLen = indexOrder.length; j < jLen; j++ ) {
			refNode = firstNodes[ indexOrder[ j ] ];
			// Exclude placeholder references
			if ( refNode.getAttribute( 'placeholder' ) ) {
				continue;
			}
			// Only increment counter for real references
			n++;
			refModel = ve.dm.MWReferenceModel.static.newFromReferenceNode( refNode );
			view = new ve.ui.MWPreviewElement(
				this.internalList.getItemNode( refModel.getListIndex() )
			);

			refGroup = refModel.getGroup();
			citation = ( refGroup && refGroup.length ? refGroup + ' ' : '' ) + n;
			matches = refModel.getListKey().match( /^literal\/(.*)$/ );
			name = matches && matches[ 1 ] || '';
			// Hide previously auto-generated reference names
			if ( name.match( /^:[0-9]+$/ ) ) {
				name = '';
			}

			// TODO: At some point we need to make sure this text is updated in
			// case the view node is still rendering. This shouldn't happen because
			// all references are supposed to be in the store and therefore are
			// immediately rendered, but we shouldn't trust that on principle to
			// account for edge cases.

			// Make visible text, citation and reference name searchable
			text = [ view.$element.text().toLowerCase(), citation, name ].join( ' ' );
			// Make URLs searchable
			view.$element.find( 'a[href]' ).each( extractAttrs );

			this.index.push( {
				$element: view.$element,
				text: text,
				reference: refModel,
				citation: citation,
				name: name
			} );
		}
	}

	// Re-populate
	this.onQueryChange();

	this.built = true;
};

/**
 * Check whether buildIndex will create an empty index based on the current internalList.
 *
 * @return {boolean} Index is empty
 */
ve.ui.MWReferenceSearchWidget.prototype.isIndexEmpty = function () {
	return this.indexEmpty;
};

/**
 * Handle media query response events.
 *
 * @method
 */
ve.ui.MWReferenceSearchWidget.prototype.addResults = function () {
	var i, len, item, $citation, $name,
		query = this.query.getValue().trim().toLowerCase(),
		items = [];

	for ( i = 0, len = this.index.length; i < len; i++ ) {
		item = this.index[ i ];
		if ( item.text.indexOf( query ) >= 0 ) {
			$citation = $( '<div>' )
				.addClass( 've-ui-mwReferenceSearchWidget-citation' )
				.text( '[' + item.citation + ']' );
			$name = $( '<div>' )
				.addClass( 've-ui-mwReferenceSearchWidget-name' )
				.text( item.name );
			items.push(
				new ve.ui.MWReferenceResultWidget( {
					data: item.reference,
					label: $citation.add( $name ).add( item.$element )
				} )
			);
		}
	}

	this.results.addItems( items );
};
