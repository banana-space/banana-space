/*!
 * VisualEditor MWMediaContextItem class.
 *
 * @copyright 2011-2017 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWImageNode.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWMediaContextItem = function VeUiMWMediaContextItem( context, model ) {
	var mediaClass;

	// Parent constructor
	ve.ui.MWMediaContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwMediaContextItem' );

	mediaClass = model.getAttribute( 'mediaClass' ) || 'Image';

	this.setIcon( model.getAttribute( 'isError' ) ? 'imageBroken' : {
		Image: 'image',
		// TODO: Better icons for audio/video
		Audio: 'play',
		Video: 'play'
	}[ mediaClass ] );
	// The following messages are used here:
	// * visualeditor-media-title-audio
	// * visualeditor-media-title-image
	// * visualeditor-media-title-video
	this.setLabel( ve.msg( 'visualeditor-media-title-' + mediaClass.toLowerCase() ) );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWMediaContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWMediaContextItem.static.name = 'mwMedia';

ve.ui.MWMediaContextItem.static.icon = 'image';

ve.ui.MWMediaContextItem.static.label =
	OO.ui.deferMsg( 'visualeditor-media-title-image' );

ve.ui.MWMediaContextItem.static.modelClasses = [ ve.dm.MWBlockImageNode, ve.dm.MWInlineImageNode ];

ve.ui.MWMediaContextItem.static.commandName = 'media';

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWMediaContextItem.prototype.getDescription = function () {
	return ve.ce.nodeFactory.getDescription( this.model );
};

/**
 * @inheritdoc
 */
ve.ui.MWMediaContextItem.prototype.renderBody = function () {
	var title = mw.Title.newFromText( mw.libs.ve.normalizeParsoidResourceName( this.model.getAttribute( 'resource' ) ) );
	this.$body.append(
		$( '<a>' )
			.text( this.getDescription() )
			.attr( {
				href: title.getUrl(),
				target: '_blank',
				rel: 'noopener'
			} )
	);
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWMediaContextItem );
