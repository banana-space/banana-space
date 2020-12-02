/*!
 * VisualEditor UserInterface MWNoParametersResultWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWNoParametersResultWidget object.
 *
 * @class
 * @extends OO.ui.OptionWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWNoParametersResultWidget = function VeUiMWNoParametersResultWidget( config ) {
	// Parent constructor
	ve.ui.MWNoParametersResultWidget.super.call( this, config );

	// Initialization
	this.$element.addClass( 've-ui-mwNoParametersResultWidget' );
	this.setLabel( this.buildLabel() );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWNoParametersResultWidget, OO.ui.OptionWidget );

/* Methods */

/**
 * Build the label element
 *
 * @return {jQuery}
 */
ve.ui.MWNoParametersResultWidget.prototype.buildLabel = function () {
	return $( '<div>' )
		.addClass( 've-ui-mwNoParametersResultWidget-label' )
		.text( ve.msg( 'visualeditor-parameter-search-no-unused' ) );
};
