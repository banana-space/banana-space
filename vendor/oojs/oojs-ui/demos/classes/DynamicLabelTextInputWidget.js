Demo.DynamicLabelTextInputWidget = function DemoDynamicLabelTextInputWidget( config ) {
	// Configuration initialization
	config = $.extend( { getLabelText: $.noop }, config );
	// Parent constructor
	Demo.DynamicLabelTextInputWidget.parent.call( this, config );
	// Properties
	this.getLabelText = config.getLabelText;
	// Events
	this.connect( this, { change: 'onChange' } );
	// Initialization
	this.setLabel( this.getLabelText( this.getValue() ) );
};
OO.inheritClass( Demo.DynamicLabelTextInputWidget, OO.ui.TextInputWidget );

Demo.DynamicLabelTextInputWidget.prototype.onChange = function ( value ) {
	this.setLabel( this.getLabelText( value ) );
};
