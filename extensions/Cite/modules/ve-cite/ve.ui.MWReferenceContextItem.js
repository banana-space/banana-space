/*!
 * VisualEditor MWReferenceContextItem class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Context item for a MWReference.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWReferenceContextItem = function VeUiMWReferenceContextItem() {
	// Parent constructor
	ve.ui.MWReferenceContextItem.super.apply( this, arguments );
	this.view = null;
	// Initialization
	this.$element.addClass( 've-ui-mwReferenceContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWReferenceContextItem.static.name = 'reference';

ve.ui.MWReferenceContextItem.static.icon = 'reference';

ve.ui.MWReferenceContextItem.static.label = OO.ui.deferMsg( 'cite-ve-dialogbutton-reference-title' );

ve.ui.MWReferenceContextItem.static.modelClasses = [ ve.dm.MWReferenceNode ];

ve.ui.MWReferenceContextItem.static.commandName = 'reference';

/* Methods */

/**
 * Get a DOM rendering of the reference.
 *
 * @private
 * @return {jQuery} DOM rendering of reference
 */
ve.ui.MWReferenceContextItem.prototype.getRendering = function () {
	var refNode = this.getReferenceNode();
	if ( refNode ) {
		this.view = new ve.ui.MWPreviewElement( refNode );

		// The $element property may be rendered into asynchronously, update the context's size when the
		// rendering is complete if that's the case
		this.view.once( 'render', this.context.updateDimensions.bind( this.context ) );

		return this.view.$element;
	} else {
		return $( '<div>' )
			.addClass( 've-ui-mwReferenceContextItem-muted' )
			.text( ve.msg( 'cite-ve-referenceslist-missingref' ) );
	}
};

/**
 * Get a DOM rendering of a warning if this reference is reused.
 *
 * @private
 * @return {jQuery|null}
 */
ve.ui.MWReferenceContextItem.prototype.getReuseWarning = function () {
	var
		refModel = ve.dm.MWReferenceModel.static.newFromReferenceNode( this.model ),
		group = this.getFragment().getDocument().getInternalList()
			.getNodeGroup( refModel.getListGroup() );
	if ( ve.getProp( group, 'keyedNodes', refModel.getListKey(), 'length' ) > 1 ) {
		return $( '<div>' )
			.addClass( 've-ui-mwReferenceContextItem-muted' )
			.text( mw.msg(
				'cite-ve-dialog-reference-editing-reused',
				group.keyedNodes[ refModel.getListKey() ].length
			) );
	}
};

/**
 * Get the reference node in the containing document (not the internal list document)
 *
 * @return {ve.dm.InternalItemNode|null} Reference item node
 */
ve.ui.MWReferenceContextItem.prototype.getReferenceNode = function () {
	var refModel;
	if ( !this.model.isEditable() ) {
		return null;
	}
	if ( !this.referenceNode ) {
		refModel = ve.dm.MWReferenceModel.static.newFromReferenceNode( this.model );
		this.referenceNode = this.getFragment().getDocument().getInternalList().getItemNode( refModel.getListIndex() );
	}
	return this.referenceNode;
};

/**
 * @inheritdoc
 */
ve.ui.MWReferenceContextItem.prototype.getDescription = function () {
	return this.model.isEditable() ? this.getRendering().text() : ve.msg( 'cite-ve-referenceslist-missingref' );
};

/**
 * @inheritdoc
 */
ve.ui.MWReferenceContextItem.prototype.renderBody = function () {
	this.$body.empty().append( this.getRendering(), this.getReuseWarning() );
};

/**
 * @inheritdoc
 */
ve.ui.MWReferenceContextItem.prototype.teardown = function () {
	if ( this.view ) {
		this.view.destroy();
	}

	// Call parent
	ve.ui.MWReferenceContextItem.super.prototype.teardown.call( this );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWReferenceContextItem );
