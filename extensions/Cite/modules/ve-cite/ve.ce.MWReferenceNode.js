/*!
 * VisualEditor ContentEditable MWReferenceNode class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * ContentEditable MediaWiki reference node.
 *
 * @class
 * @extends ve.ce.LeafNode
 * @mixins ve.ce.FocusableNode
 *
 * @constructor
 * @param {ve.dm.MWReferenceNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWReferenceNode = function VeCeMWReferenceNode() {
	// Parent constructor
	ve.ce.MWReferenceNode.super.apply( this, arguments );

	// Mixin constructors
	ve.ce.FocusableNode.call( this );

	// DOM changes
	this.$link = $( '<a>' ).attr( 'href', '#' );
	this.$element.addClass( 've-ce-mwReferenceNode mw-ref' ).append( this.$link )
		// In case we have received a version with old-style Cite HTML, remove the
		// old reference class
		.removeClass( 'reference' );
	// Add a backwards-compatible text for browsers that don't support counters
	this.$text = $( '<span>' ).addClass( 'mw-reflink-text' );
	this.$link.append( this.$text );

	this.index = '';
	this.internalList = this.model.getDocument().internalList;

	// Events
	this.connect( this, {
		setup: 'onSetup',
		teardown: 'onTeardown'
	} );
	this.model.connect( this, { attributeChange: 'onAttributeChange' } );

	// Initialization
	this.update();
};

/* Inheritance */

OO.inheritClass( ve.ce.MWReferenceNode, ve.ce.LeafNode );

OO.mixinClass( ve.ce.MWReferenceNode, ve.ce.FocusableNode );

/* Static Properties */

ve.ce.MWReferenceNode.static.name = 'mwReference';

ve.ce.MWReferenceNode.static.tagName = 'span';

ve.ce.MWReferenceNode.static.primaryCommandName = 'reference';

/* Methods */

/**
 * Handle setup event.
 */
ve.ce.MWReferenceNode.prototype.onSetup = function () {
	ve.ce.MWReferenceNode.super.prototype.onSetup.call( this );
	this.internalList.connect( this, { update: 'onInternalListUpdate' } );
};

/**
 * Handle teardown event.
 */
ve.ce.MWReferenceNode.prototype.onTeardown = function () {
	// As we are listening to the internal list, we need to make sure
	// we remove the listeners when this object is removed from the document
	this.internalList.disconnect( this );

	ve.ce.MWReferenceNode.super.prototype.onTeardown.call( this );
};

/**
 * Handle the updating of the InternalList object.
 *
 * This will occur after a document transaction.
 *
 * @param {string[]} groupsChanged A list of groups which have changed in this transaction
 */
ve.ce.MWReferenceNode.prototype.onInternalListUpdate = function ( groupsChanged ) {
	// Only update if this group has been changed
	if ( groupsChanged.indexOf( this.model.getAttribute( 'listGroup' ) ) !== -1 ) {
		this.update();
	}
};

/**
 * Handle attribute change events
 *
 * @param {string} key Attribute key
 * @param {string} from Old value
 * @param {string} to New value
 */
ve.ce.MWReferenceNode.prototype.onAttributeChange = function ( key ) {
	if ( key === 'placeholder' ) {
		this.update();
	}
};

/**
 * @inheritdoc ve.ce.FocusableNode
 */
ve.ce.MWReferenceNode.prototype.executeCommand = function () {
	var command, contextItem,
		items = ve.ui.contextItemFactory.getRelatedItems( [ this.model ] );

	if ( items.length ) {
		contextItem = ve.ui.contextItemFactory.lookup( items[ 0 ].name );
		if ( contextItem ) {
			command = this.getRoot().getSurface().getSurface().commandRegistry.lookup( contextItem.static.commandName );
			if ( command ) {
				command.execute( this.focusableSurface.getSurface() );
			}
		}
	}
};

/**
 * Update the rendering
 */
ve.ce.MWReferenceNode.prototype.update = function () {
	var group = this.model.getGroup();
	this.$text.text( this.model.getIndexLabel() );
	this.$link.css( 'counterReset', 'mw-Ref ' + this.model.getIndex() );
	if ( group ) {
		this.$link.attr( 'data-mw-group', group );
	} else {
		this.$link.removeAttr( 'data-mw-group' );
	}
	this.$element.toggleClass( 've-ce-mwReferenceNode-placeholder', !!this.model.getAttribute( 'placeholder' ) );
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWReferenceNode );
