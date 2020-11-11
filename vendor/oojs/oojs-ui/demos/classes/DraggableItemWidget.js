/**
 * Drag/drop items
 *
 * @param {Object} [config] Configuration options
 */
Demo.DraggableItemWidget = function DemoDraggableItemWidget( config ) {
	// Configuration initialization
	config = config || {};

	// Parent constructor
	Demo.DraggableItemWidget.parent.call( this, config );

	// Mixin constructors
	OO.ui.mixin.DraggableElement.call( this, config );
};

/* Setup */
OO.inheritClass( Demo.DraggableItemWidget, Demo.SimpleWidget );
OO.mixinClass( Demo.DraggableItemWidget, OO.ui.mixin.DraggableElement );
