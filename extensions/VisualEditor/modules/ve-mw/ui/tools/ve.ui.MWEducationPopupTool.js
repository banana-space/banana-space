/*!
 * VisualEditor UserInterface MediaWiki EducationPopupTool class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * UserInterface education popup tool. Used as a mixin to show a pulsating blue dot
 * which, when you click, reveals a popup with useful information.
 *
 * @class
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWEducationPopupTool = function VeUiMwEducationPopupTool( config ) {
	var popupCloseButton, $popupContent, $shield,
		tool = this;

	config = config || {};

	// Do not display on platforms other than desktop
	if ( !( ve.init.mw.DesktopArticleTarget && ve.init.target instanceof ve.init.mw.DesktopArticleTarget ) ) {
		return;
	}

	// Do not display if the user already acknowledged the popups
	if ( !mw.libs.ve.shouldShowEducationPopups() ) {
		return;
	}

	if ( !( this.toolGroup instanceof OO.ui.BarToolGroup ) ) {
		// The popup gets hideously deformed in other cases. Getting it to work would probably be
		// difficult. Let's just not show it. (T170919)
		return;
	}

	popupCloseButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'visualeditor-educationpopup-dismiss' ),
		flags: [ 'progressive', 'primary' ],
		classes: [ 've-ui-educationPopup-dismiss' ]
	} );
	popupCloseButton.connect( this, { click: 'onPopupCloseButtonClick' } );
	$popupContent = $( '<div>' ).append(
		$( '<div>' ).addClass( 've-ui-educationPopup-header' ),
		$( '<h3>' ).text( config.title ),
		$( '<p>' ).text( config.text ),
		popupCloseButton.$element
	);

	this.popup = new OO.ui.PopupWidget( {
		$floatableContainer: this.$element,
		$content: $popupContent,
		padded: true,
		width: 300
	} );

	this.shownEducationPopup = false;
	this.$pulsatingDot = $( '<div>' ).addClass( 'mw-pulsating-dot' );
	$shield = $( '<div>' ).addClass( 've-ui-educationPopup-shield' );
	this.$element
		.addClass( 've-ui-educationPopup' )
		.append( $shield, this.popup.$element, this.$pulsatingDot );
	this.$element.children().not( this.popup.$element ).on( 'click', function () {
		if ( !tool.shownEducationPopup ) {
			if ( ve.init.target.openEducationPopupTool ) {
				ve.init.target.openEducationPopupTool.popup.toggle( false );
				ve.init.target.openEducationPopupTool.setActive( false );
				ve.init.target.openEducationPopupTool.$pulsatingDot.removeClass( 'oo-ui-element-hidden' );
			}
			ve.init.target.openEducationPopupTool = tool;
			tool.$pulsatingDot.addClass( 'oo-ui-element-hidden' );
			tool.popup.toggle( true );
			popupCloseButton.focus();
			$shield.remove();

			ve.track( 'activity.' + tool.constructor.static.name + 'EducationPopup', { action: 'show' } );
		}
	} );
};

/* Inheritance */

OO.initClass( ve.ui.MWEducationPopupTool );

/* Methods */

/**
 * Click handler for the popup close button
 */
ve.ui.MWEducationPopupTool.prototype.onPopupCloseButtonClick = function () {
	this.shownEducationPopup = true;
	this.popup.toggle( false );
	this.setActive( false );
	ve.init.target.openEducationPopupTool = undefined;
	mw.libs.ve.stopShowingEducationPopups();

	this.onSelect();
};
