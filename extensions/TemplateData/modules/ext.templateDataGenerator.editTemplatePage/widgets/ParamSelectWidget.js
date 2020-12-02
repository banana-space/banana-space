/**
 * TemplateData parameter select widget
 *
 * @class
 * @extends {OO.ui.SelectWidget}
 * @mixins OO.ui.mixin.DraggableGroupElement
 *
 * @param {Object} config Dialog configuration object
 */
mw.TemplateData.ParamSelectWidget = function mwTemplateDataParamSelectWidget( config ) {
	// Parent constructor
	mw.TemplateData.ParamSelectWidget.parent.call( this, config );

	// Mixin constructors
	OO.ui.mixin.DraggableGroupElement.call( this, $.extend( {}, config, { $group: this.$element } ) );

	// Initialize
	this.$element.addClass( 'tdg-templateDataParamSelectWidget' );
};

/* Inheritance */

OO.inheritClass( mw.TemplateData.ParamSelectWidget, OO.ui.SelectWidget );

OO.mixinClass( mw.TemplateData.ParamSelectWidget, OO.ui.mixin.DraggableGroupElement );

mw.TemplateData.ParamSelectWidget.prototype.onMouseDown = function ( e ) {
	if ( $( e.target ).closest( '.oo-ui-draggableElement-handle' ).length || e.shiftKey ) {
		return true;
	}
	return mw.TemplateData.ParamSelectWidget.parent.prototype.onMouseDown.apply( this, arguments );
};
