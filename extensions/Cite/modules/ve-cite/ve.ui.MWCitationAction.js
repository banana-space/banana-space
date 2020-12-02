/*!
 * VisualEditor UserInterface MWCitationAction class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Link action.
 *
 * Opens either MWLinkAnnotationInspector or MWLinkNodeInspector depending on what is selected.
 *
 * @class
 * @extends ve.ui.Action
 * @constructor
 * @param {ve.ui.Surface} surface Surface to act on
 */
ve.ui.MWCitationAction = function VeUiMWCitationAction() {
	// Parent constructor
	ve.ui.MWCitationAction.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCitationAction, ve.ui.Action );

/* Static Properties */

ve.ui.MWCitationAction.static.name = 'mwcite';

/**
 * @inheritdoc
 */
ve.ui.MWCitationAction.static.methods = [ 'open' ];

/* Methods */

/**
 * When opening a citation, send the dialog a property of the surface
 * dialog name.
 *
 * @method
 * @param {Object} windowData Data to send to the dialog
 * @return {boolean} Action was executed
 */
ve.ui.MWCitationAction.prototype.open = function ( windowData ) {
	windowData = $.extend( {
		inDialog: this.surface.getInDialog()
	}, windowData );

	this.surface.execute( 'window', 'open', 'cite', windowData );
	return true;
};

/* Registration */

ve.ui.actionFactory.register( ve.ui.MWCitationAction );
