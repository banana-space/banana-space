/*!
 * VisualEditor UserInterface MWPreviewElement class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWPreviewElement object.
 *
 * @class
 * @extends ve.ui.PreviewElement
 *
 * @constructor
 * @param {ve.dm.Node} [model]
 * @param {Object} [config]
 */
ve.ui.MWPreviewElement = function VeUiMwPreviewElement() {
	// Parent constructor
	ve.ui.MWPreviewElement.super.apply( this, arguments );

	// Initialize
	this.$element.addClass( 've-ui-mwPreviewElement mw-body-content mw-parser-output' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWPreviewElement, ve.ui.PreviewElement );

/* Method */

/**
 * @inheritdoc
 */
ve.ui.MWPreviewElement.prototype.setModel = function ( model ) {
	// Parent method
	ve.ui.MWPreviewElement.super.prototype.setModel.call( this, model );

	// The following classes are used here:
	// * mw-content-ltr
	// * mw-content-rtl
	this.$element.addClass( 'mw-content-' + this.model.getDocument().getDir() );
};

/**
 * @inheritdoc
 */
ve.ui.MWPreviewElement.prototype.replaceWithModelDom = function () {
	// Parent method
	ve.ui.MWPreviewElement.super.prototype.replaceWithModelDom.apply( this, arguments );

	ve.init.platform.linkCache.styleParsoidElements(
		this.$element,
		// The DM node should be attached, but check just in case.
		this.model.getDocument() && this.model.getDocument().getHtmlDocument()
	);
};
