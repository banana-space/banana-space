/**
 * Very simple custom widget.
 *
 * @class
 * @extends OO.ui.Widget
 * @mixins OO.ui.mixin.IconElement
 * @mixins OO.ui.mixin.LabelElement
 *
 * @param {Object} [config] Configuration options
 */
Demo.SimpleWidget = function DemoSimpleWidget( config ) {
	// Configuration initialization
	config = config || {};

	// Parent constructor
	Demo.SimpleWidget.parent.call( this, config );

	// Mixin constructors
	OO.ui.mixin.IconElement.call( this, config );
	OO.ui.mixin.LabelElement.call( this, config );

	// Initialization
	this.$element
		.addClass( 'demo-simpleWidget' )
		.append( this.$icon, this.$label );
};

/* Setup */
OO.inheritClass( Demo.SimpleWidget, OO.ui.Widget );
OO.mixinClass( Demo.SimpleWidget, OO.ui.mixin.IconElement );
OO.mixinClass( Demo.SimpleWidget, OO.ui.mixin.LabelElement );
