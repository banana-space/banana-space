/*!
 * VisualEditor MWGalleryContextItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWGallery.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWGalleryContextItem = function VeUiMWGalleryContextItem() {
	// Parent constructor
	ve.ui.MWGalleryContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwGalleryContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWGalleryContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWGalleryContextItem.static.name = 'mwGallery';

ve.ui.MWGalleryContextItem.static.icon = 'imageGallery';

ve.ui.MWGalleryContextItem.static.label = OO.ui.deferMsg( 'visualeditor-mwgallerydialog-title' );

ve.ui.MWGalleryContextItem.static.modelClasses = [ ve.dm.MWGalleryNode ];

ve.ui.MWGalleryContextItem.static.commandName = 'gallery';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWGalleryContextItem.prototype.getDescription = function () {
	return ve.msg( 'visualeditor-mwgallerycontext-description', this.model.children.length );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWGalleryContextItem );
