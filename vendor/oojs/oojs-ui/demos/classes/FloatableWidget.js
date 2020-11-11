Demo.FloatableWidget = function DemoFloatableWidget( config ) {
	// Parent constructor
	Demo.FloatableWidget.parent.call( this, config );
	// Mixin constructors
	OO.ui.mixin.FloatableElement.call( this, config );
	OO.ui.mixin.ClippableElement.call( this, config );
};
OO.inheritClass( Demo.FloatableWidget, OO.ui.Widget );
OO.mixinClass( Demo.FloatableWidget, OO.ui.mixin.FloatableElement );
OO.mixinClass( Demo.FloatableWidget, OO.ui.mixin.ClippableElement );
