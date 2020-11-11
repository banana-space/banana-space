/**
 * Drag/drop items with custom handle
 *
 * @param {Object} [config] Configuration options
 */
Demo.DraggableHandledItemWidget = function DemoDraggableHandledItemWidget( config ) {
	// Configuration initialization
	config = config || {};

	// Parent constructor
	Demo.DraggableHandledItemWidget.parent.call( this, config );

	// Mixin constructors
	OO.ui.mixin.DraggableElement.call( this, $.extend( { $handle: this.$icon }, config ) );
};

/* Setup */
OO.inheritClass( Demo.DraggableHandledItemWidget, Demo.SimpleWidget );
OO.mixinClass( Demo.DraggableHandledItemWidget, OO.ui.mixin.DraggableElement );
