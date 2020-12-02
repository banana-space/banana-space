/*!
 * VisualEditor UserInterface MWParameterCheckboxInputWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWParameterCheckboxInputWidget object.
 *
 * @class
 * @extends OO.ui.CheckboxInputWidget
 *
 * @constructor
 */
ve.ui.MWParameterCheckboxInputWidget = function VeUiMWParameterCheckboxInputWidget() {
	// Parent constructor
	ve.ui.MWParameterCheckboxInputWidget.parent.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWParameterCheckboxInputWidget, OO.ui.CheckboxInputWidget );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWParameterCheckboxInputWidget.prototype.getValue = function () {
	return this.isSelected() ? '1' : '0';
};

/**
 * @inheritdoc
 */
ve.ui.MWParameterCheckboxInputWidget.prototype.setValue = function ( value ) {
	return this.setSelected( value === '1' );
};
