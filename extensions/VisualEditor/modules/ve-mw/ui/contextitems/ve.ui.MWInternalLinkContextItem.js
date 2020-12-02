/*!
 * VisualEditor MWInternalLinkContextItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWInternalLink.
 *
 * @class
 * @extends ve.ui.LinkContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWInternalLinkContextItem = function VeUiMWInternalLinkContextItem() {
	// Parent constructor
	ve.ui.MWInternalLinkContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwInternalLinkContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWInternalLinkContextItem, ve.ui.LinkContextItem );

/* Static Properties */

ve.ui.MWInternalLinkContextItem.static.name = 'link/internal';

ve.ui.MWInternalLinkContextItem.static.modelClasses = [ ve.dm.MWInternalLinkAnnotation ];

/* Static methods */

/**
 * Generate the body of the link context item
 *
 * @param {ve.init.mw.LinkCache} linkCache The link cache to use
 * @param {ve.dm.MWInternalLinkAnnotation} model The annotation model
 * @param {HTMLDocument} htmlDoc The HTML document (for URL resolution)
 * @param {ve.ui.Context} context Context (for resizing)
 * @return {jQuery} The jQuery object of the link context item
 */
ve.ui.MWInternalLinkContextItem.static.generateBody = function ( linkCache, model, htmlDoc, context ) {
	var icon, $description,
		title = model.getAttribute( 'lookupTitle' ),
		normalizedTitle = model.getAttribute( 'normalizedTitle' ),
		href = model.getHref(),
		titleObj = mw.Title.newFromText( mw.libs.ve.normalizeParsoidResourceName( href ) ),
		fragment = model.getFragment(),
		usePageImages = mw.config.get( 'wgVisualEditorConfig' ).usePageImages,
		usePageDescriptions = mw.config.get( 'wgVisualEditorConfig' ).usePageDescriptions,
		$wrapper = $( '<div>' ),
		$link = $( '<a>' )
			.addClass( 've-ui-linkContextItem-link' )
			.text( normalizedTitle )
			.attr( {
				href: titleObj.getUrl(),
				target: '_blank',
				rel: 'noopener'
			} );

	// Style based on link cache information
	ve.init.platform.linkCache.styleElement( title, $link, fragment );
	// Don't style as a self-link in the context menu (but do elsewhere)
	$link.removeClass( 'mw-selflink' );

	if ( usePageImages ) {
		icon = new OO.ui.IconWidget( { icon: 'page-existing' } );
		$wrapper
			.addClass( 've-ui-mwInternalLinkContextItem-withImage' )
			.append( icon.$element );
	}

	$wrapper.append( $link );

	if ( usePageDescriptions ) {
		$wrapper.addClass( 've-ui-mwInternalLinkContextItem-withDescription' );
	}

	if ( usePageImages || usePageDescriptions ) {
		linkCache.get( title ).then( function ( linkData ) {
			if ( usePageImages ) {
				if ( linkData.imageUrl ) {
					icon.$element
						.addClass( 've-ui-mwInternalLinkContextItem-hasImage' )
						.css( 'background-image', 'url(' + linkData.imageUrl + ')' );
				} else {
					icon.setIcon( ve.init.platform.linkCache.constructor.static.getIconForLink( linkData ) );
				}
			}
			if ( usePageDescriptions && linkData.description ) {
				$description = $( '<span>' )
					.addClass( 've-ui-mwInternalLinkContextItem-description' )
					.text( linkData.description );
				$wrapper.append( $description );
				// Multiline descriptions may make the context bigger (T183650)
				context.updateDimensions();
			}
		} );
	}
	return $wrapper;
};

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWInternalLinkContextItem.prototype.getDescription = function () {
	return this.model.getAttribute( 'normalizedTitle' );
};

/**
 * @inheritdoc
 */
ve.ui.MWInternalLinkContextItem.prototype.renderBody = function () {
	this.$body.empty().append( this.constructor.static.generateBody(
		ve.init.platform.linkCache,
		this.model,
		this.context.getSurface().getModel().getDocument().getHtmlDocument(),
		this.context
	) );
	if ( !this.context.isMobile() ) {
		this.$body.append( this.$labelLayout );
	}
	this.updateLabelPreview();
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWInternalLinkContextItem );
