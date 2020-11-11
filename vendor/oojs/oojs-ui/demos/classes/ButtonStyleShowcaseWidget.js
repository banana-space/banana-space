Demo.ButtonStyleShowcaseWidget = function DemoButtonStyleShowcaseWidget( config ) {
	var widget = this;

	Demo.ButtonStyleShowcaseWidget.parent.call( this, config );

	this.$element.addClass( 'demo-buttonStyleShowcaseWidget' );

	$.each( this.constructor.static.styles, function ( i, style ) {
		var $buttonRow = $( '<div>' );
		$.each( widget.constructor.static.states, function ( j, state ) {
			$buttonRow.append(
				new OO.ui.ButtonWidget( $.extend( {}, style, state ) ).$element
			);
		} );
		widget.$element.append( $buttonRow );
	} );
};

OO.inheritClass( Demo.ButtonStyleShowcaseWidget, OO.ui.Widget );

Demo.ButtonStyleShowcaseWidget.static.styles = [
	{},
	{
		flags: [ 'progressive' ]
	},
	{
		flags: [ 'destructive' ]
	},
	{
		flags: [ 'primary', 'progressive' ]
	},
	{
		flags: [ 'primary', 'destructive' ]
	}
];

Demo.ButtonStyleShowcaseWidget.static.states = [
	{
		label: 'Button'
	},
	{
		label: 'Button',
		icon: 'tag'
	},
	{
		label: 'Button',
		icon: 'tag',
		indicator: 'down'
	},
	{
		icon: 'tag',
		title: 'Title text'
	},
	{
		indicator: 'down'
	},
	{
		icon: 'tag',
		indicator: 'down'
	},
	{
		label: 'Button',
		disabled: true
	},
	{
		icon: 'tag',
		title: 'Title text',
		disabled: true
	},
	{
		indicator: 'down',
		disabled: true
	}
];
