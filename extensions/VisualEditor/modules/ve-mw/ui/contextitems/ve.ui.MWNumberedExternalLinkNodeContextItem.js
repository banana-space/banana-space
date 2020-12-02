/*!
 * VisualEditor MWNumberedExternalLinkNodeContextItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWNumberedExternalLinkNode.
 *
 * @class
 * @extends ve.ui.LinkContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWNumberedExternalLinkNodeContextItem = function VeUiMWNumberedExternalLinkNodeContextItem() {
	// Parent constructor
	ve.ui.MWNumberedExternalLinkNodeContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwNumberedExternalLinkNodeContextItem' );

	if ( this.labelButton ) {
		this.labelButton.setLabel( OO.ui.deferMsg( 'visualeditor-linknodeinspector-add-label' ) );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWNumberedExternalLinkNodeContextItem, ve.ui.LinkContextItem );

/* Static Properties */

ve.ui.MWNumberedExternalLinkNodeContextItem.static.name = 'link/mwNumberedExternal';

ve.ui.MWNumberedExternalLinkNodeContextItem.static.modelClasses = [ ve.dm.MWNumberedExternalLinkNode ];

ve.ui.MWNumberedExternalLinkNodeContextItem.static.clearable = false;

ve.ui.MWNumberedExternalLinkNodeContextItem.static.deletable = true;

/* Methods */

ve.ui.MWNumberedExternalLinkNodeContextItem.prototype.isDeletable = function () {
	// We don't care about whether the context wants to show delete buttons, so override the check.
	return this.constructor.static.deletable && !this.isReadOnly();
};

/**
 * @inheritdoc
 */
ve.ui.MWNumberedExternalLinkNodeContextItem.prototype.onLabelButtonClick = function () {
	var annotation, annotations, content,
		surfaceModel = this.context.getSurface().getModel(),
		surfaceView = this.context.getSurface().getView(),
		doc = surfaceModel.getDocument(),
		nodeRange = this.model.getOuterRange();

	// TODO: this is very similar to part of
	// ve.ui.MWLinkNodeInspector.prototype.getTeardownProcess, and should
	// perhaps be consolidated into a reusable "replace node with annotated
	// text and select that text" method somewhere appropriate.

	annotation = new ve.dm.MWExternalLinkAnnotation( {
		type: 'link/mwExternal',
		attributes: {
			href: this.model.getHref()
		}
	} );
	annotations = doc.data.getAnnotationsFromOffset( nodeRange.start ).clone();
	annotations.push( annotation );
	content = this.model.getHref().split( '' );
	ve.dm.Document.static.addAnnotationsToData( content, annotations );
	surfaceModel.change(
		ve.dm.TransactionBuilder.static.newFromReplacement( doc, nodeRange, content )
	);
	setTimeout( function () {
		surfaceView.selectAnnotation( function ( view ) {
			return view.model instanceof ve.dm.MWExternalLinkAnnotation;
		} );
	} );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWNumberedExternalLinkNodeContextItem );
