/*!
 * VisualEditor UserInterface MWTargetWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWTargetWidget object.
 *
 * @class
 * @abstract
 * @extends ve.ui.TargetWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWTargetWidget = function VeUiMWTargetWidget() {
	// Parent constructor
	ve.ui.MWTargetWidget.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwTargetWidget' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTargetWidget, ve.ui.TargetWidget );

/**
 * @inheritdoc
 */
ve.ui.MWTargetWidget.prototype.createTarget = function () {
	return new ve.init.mw.Target( {
		register: false,
		toolbarGroups: this.toolbarGroups,
		inTargetWidget: true,
		modes: this.modes,
		defaultMode: this.defaultMode
	} );
};

/**
 * @inheritdoc
 */
ve.ui.MWTargetWidget.prototype.setDocument = function () {
	// Parent method
	ve.ui.MWTargetWidget.super.prototype.setDocument.apply( this, arguments );

	// Add MW specific classes to the surface
	this.getSurface().getView().$element.addClass( 'mw-body-content' );
	this.getSurface().$placeholder.addClass( 'mw-body-content' );
};
