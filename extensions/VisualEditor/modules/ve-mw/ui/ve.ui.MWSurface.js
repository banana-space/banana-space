/*!
 * VisualEditor UserInterface MWSurface class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * @class
 * @extends ve.ui.Surface
 *
 * @constructor
 * @param {HTMLDocument|Array|ve.dm.LinearData|ve.dm.Document} dataOrDoc Document data to edit
 * @param {Object} [config] Configuration options
 */
ve.ui.MWSurface = function VeUiMWSurface() {
	// Parent constructor
	ve.ui.MWSurface.super.apply( this, arguments );

	// Events
	this.getView().getDocument().connect( this, { langChange: 'onDocumentViewLangChange' } );

	// DOM changes
	this.onDocumentViewLangChange();
	this.$element.addClass( 've-ui-mwSurface' );
	// T164790
	this.getView().$attachedRootNode.addClass( 'mw-parser-output' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWSurface, ve.ui.Surface );

/* Methods */

/**
 * Handle document view langChange events
 */
ve.ui.MWSurface.prototype.onDocumentViewLangChange = function () {
	// Add appropriately mw-content-ltr or mw-content-rtl class
	this.getView().$attachedRootNode
		.removeClass( 'mw-content-ltr mw-content-rtl' )
		// The following classes are used here:
		// * mw-content-ltr
		// * mw-content-rtl
		.addClass( 'mw-content-' + this.getView().getDocument().getDir() );
};
