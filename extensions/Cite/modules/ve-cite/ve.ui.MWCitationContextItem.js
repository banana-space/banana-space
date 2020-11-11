/*!
 * VisualEditor MWCitationContextItem class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Context item for a MWCitation.
 *
 * @class
 * @extends ve.ui.MWReferenceContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWCitationContextItem = function VeUiMWCitationContextItem() {
	// Parent constructor
	ve.ui.MWCitationContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwCitationContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCitationContextItem, ve.ui.MWReferenceContextItem );

/* Static Properties */

/**
 * Only display item for single-template transclusions of these templates.
 *
 * @property {string|string[]|null}
 * @static
 * @inheritable
 */
ve.ui.MWCitationContextItem.static.template = null;

/* Static Methods */

/**
 * @static
 * @localdoc Sharing implementation with ve.ui.MWCitationDialogTool
 */
ve.ui.MWCitationContextItem.static.isCompatibleWith =
	ve.ui.MWCitationDialogTool.static.isCompatibleWith;
