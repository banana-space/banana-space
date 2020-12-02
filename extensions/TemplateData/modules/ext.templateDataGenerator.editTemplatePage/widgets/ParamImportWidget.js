/**
 * TemplateData Param Import Widget
 *
 * @extends {OO.ui.ButtonWidget}
 */
mw.TemplateData.ParamImportWidget = function mwTemplateDataParamImportWidget( config ) {
	config = config || {};

	// Parent constructor
	mw.TemplateData.ParamImportWidget.parent.call( this, $.extend( {
		icon: 'parameter-set'
	}, config ) );

	// Initialize
	this.$element.addClass( 'tdg-templateDataParamImportWidget' );
};

/* Inheritance */

OO.inheritClass( mw.TemplateData.ParamImportWidget, OO.ui.ButtonWidget );

/**
 * Build the parameter label in the parameter select widget
 *
 * @param {string[]} params Param names
 */
mw.TemplateData.ParamImportWidget.prototype.buildParamLabel = function ( params ) {
	var paramNames = params.slice( 0, 9 ).join( mw.msg( 'comma-separator' ) ),
		$paramName = $( '<div>' )
			.addClass( 'tdg-templateDataParamWidget-param-name' ),
		$description = $( '<div>' )
			.addClass( 'tdg-templateDataParamWidget-param-description' );

	$paramName.text( mw.msg( 'templatedata-modal-table-param-importoption', params.length ) );
	$description.text( mw.msg( 'templatedata-modal-table-param-importoption-subtitle', paramNames ) );

	this.setLabel( $paramName.add( $description ) );
};
