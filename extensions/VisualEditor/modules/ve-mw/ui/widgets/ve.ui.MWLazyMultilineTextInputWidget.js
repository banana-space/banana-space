/*!
 * VisualEditor UserInterface MWLazyMultilineTextInputWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates a ve.ui.MWLazyMultilineTextInputWidget object.
 *
 * This widget is a hack to be used when you are building a UI
 * that potentially contains lots of multi-line text input widgets,
 * such as the template param editor.
 *
 * It defers the calculation of the auto height until the first focus,
 * as doing this hundreds of times is slow.
 *
 * @class
 * @extends OO.ui.MultilineTextInputWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWLazyMultilineTextInputWidget = function VeUiMWLazyMultilineTextInputWidget() {
	var widget = this;

	// Parent constructor
	ve.ui.MWLazyMultilineTextInputWidget.super.apply( this, arguments );

	// Check autosize is set, but if it isn't you probably shouldn't be using this widget!
	if ( this.autosize ) {
		this.autosize = false;
		this.$input.one( 'focus', function () {
			widget.autosize = true;
			widget.adjustSize();
		} );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWLazyMultilineTextInputWidget, OO.ui.MultilineTextInputWidget );
