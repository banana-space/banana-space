/*!
 * VisualEditor MWTransclusionContextItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWTransclusion.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWTransclusionContextItem = function VeUiMWTransclusionContextItem() {
	// Parent constructor
	ve.ui.MWTransclusionContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwTransclusionContextItem' );
	if ( !this.model.isSingleTemplate() ) {
		this.setLabel( ve.msg( 'visualeditor-dialogbutton-transclusion-tooltip' ) );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWTransclusionContextItem.static.name = 'transclusion';

ve.ui.MWTransclusionContextItem.static.icon = 'puzzle';

ve.ui.MWTransclusionContextItem.static.label =
	OO.ui.deferMsg( 'visualeditor-dialogbutton-template-tooltip' );

ve.ui.MWTransclusionContextItem.static.modelClasses = [ ve.dm.MWTransclusionNode ];

ve.ui.MWTransclusionContextItem.static.commandName = 'transclusion';

/**
 * Only display item for single-template transclusions of these templates.
 *
 * @property {string|string[]|null}
 * @static
 * @inheritable
 */
ve.ui.MWTransclusionContextItem.static.template = null;

/* Static Methods */

/**
 * @static
 * @localdoc Sharing implementation with ve.ui.MWTransclusionDialogTool
 */
ve.ui.MWTransclusionContextItem.static.isCompatibleWith =
	ve.ui.MWTransclusionDialogTool.static.isCompatibleWith;

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionContextItem.prototype.getDescription = function () {
	var nodeClass = ve.ce.nodeFactory.lookup( this.model.constructor.static.name );
	return ve.msg(
		'visualeditor-dialog-transclusion-contextitem-description',
		nodeClass.static.getDescription( this.model ),
		nodeClass.static.getTemplatePartDescriptions( this.model ).length
	);
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionContextItem.prototype.onEditButtonClick = function () {
	var surfaceModel = this.context.getSurface().getModel(),
		selection = surfaceModel.getSelection();

	if ( selection instanceof ve.dm.TableSelection ) {
		surfaceModel.setLinearSelection( selection.getOuterRanges(
			surfaceModel.getDocument()
		)[ 0 ] );
	}

	// Parent method
	ve.ui.MWTransclusionContextItem.super.prototype.onEditButtonClick.apply( this, arguments );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWTransclusionContextItem );
