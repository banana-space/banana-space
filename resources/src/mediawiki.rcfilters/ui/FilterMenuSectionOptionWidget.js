/**
 * A widget representing a menu section for filter groups
 *
 * @class mw.rcfilters.ui.FilterMenuSectionOptionWidget
 * @extends OO.ui.MenuSectionOptionWidget
 *
 * @constructor
 * @param {mw.rcfilters.Controller} controller RCFilters controller
 * @param {mw.rcfilters.dm.FilterGroup} model Filter group model
 * @param {Object} config Configuration object
 * @cfg {jQuery} [$overlay] Overlay
 */
var FilterMenuSectionOptionWidget = function MwRcfiltersUiFilterMenuSectionOptionWidget( controller, model, config ) {
	var whatsThisMessages,
		$header = $( '<div>' )
			.addClass( 'mw-rcfilters-ui-filterMenuSectionOptionWidget-header' ),
		$popupContent = $( '<div>' )
			.addClass( 'mw-rcfilters-ui-filterMenuSectionOptionWidget-whatsThisButton-popup-content' );

	config = config || {};

	this.controller = controller;
	this.model = model;
	this.$overlay = config.$overlay || this.$element;

	// Parent
	FilterMenuSectionOptionWidget.parent.call( this, $.extend( {
		label: this.model.getTitle(),
		$label: $( '<div>' )
			.addClass( 'mw-rcfilters-ui-filterMenuSectionOptionWidget-header-title' )
	}, config ) );

	$header.append( this.$label );

	if ( this.model.hasWhatsThis() ) {
		whatsThisMessages = this.model.getWhatsThis();

		// Create popup
		if ( whatsThisMessages.header ) {
			$popupContent.append(
				( new OO.ui.LabelWidget( {
					// eslint-disable-next-line mediawiki/msg-doc
					label: mw.msg( whatsThisMessages.header ),
					classes: [ 'mw-rcfilters-ui-filterMenuSectionOptionWidget-whatsThisButton-popup-content-header' ]
				} ) ).$element
			);
		}
		if ( whatsThisMessages.body ) {
			$popupContent.append(
				( new OO.ui.LabelWidget( {
					// eslint-disable-next-line mediawiki/msg-doc
					label: mw.msg( whatsThisMessages.body ),
					classes: [ 'mw-rcfilters-ui-filterMenuSectionOptionWidget-whatsThisButton-popup-content-body' ]
				} ) ).$element
			);
		}
		if ( whatsThisMessages.linkText && whatsThisMessages.url ) {
			$popupContent.append(
				( new OO.ui.ButtonWidget( {
					framed: false,
					flags: [ 'progressive' ],
					href: whatsThisMessages.url,
					// eslint-disable-next-line mediawiki/msg-doc
					label: mw.msg( whatsThisMessages.linkText ),
					classes: [ 'mw-rcfilters-ui-filterMenuSectionOptionWidget-whatsThisButton-popup-content-link' ]
				} ) ).$element
			);
		}

		// Add button
		this.whatsThisButton = new OO.ui.PopupButtonWidget( {
			framed: false,
			label: mw.msg( 'rcfilters-filterlist-whatsthis' ),
			$overlay: this.$overlay,
			classes: [ 'mw-rcfilters-ui-filterMenuSectionOptionWidget-whatsThisButton' ],
			flags: [ 'progressive' ],
			popup: {
				padded: false,
				align: 'center',
				position: 'above',
				$content: $popupContent,
				classes: [ 'mw-rcfilters-ui-filterMenuSectionOptionWidget-whatsThisButton-popup' ]
			}
		} );

		$header
			.append( this.whatsThisButton.$element );
	}

	// Events
	this.model.connect( this, { update: 'updateUiBasedOnState' } );

	// Initialize
	// eslint-disable-next-line mediawiki/class-doc
	this.$element
		.addClass( 'mw-rcfilters-ui-filterMenuSectionOptionWidget' )
		.addClass( 'mw-rcfilters-ui-filterMenuSectionOptionWidget-name-' + this.model.getName() )
		.append( $header );
	this.updateUiBasedOnState();
};

/* Initialize */

OO.inheritClass( FilterMenuSectionOptionWidget, OO.ui.MenuSectionOptionWidget );

/* Methods */

/**
 * Respond to model update event
 */
FilterMenuSectionOptionWidget.prototype.updateUiBasedOnState = function () {
	this.$element.toggleClass(
		'mw-rcfilters-ui-filterMenuSectionOptionWidget-active',
		this.model.isActive()
	);
	this.toggle( this.model.isVisible() );
};

/**
 * Get the group name
 *
 * @return {string} Group name
 */
FilterMenuSectionOptionWidget.prototype.getName = function () {
	return this.model.getName();
};

module.exports = FilterMenuSectionOptionWidget;
