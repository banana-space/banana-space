/**
 * Draggable group widget containing drag/drop items
 *
 * @param {Object} [config] Configuration options
 */
Demo.DraggableGroupWidget = function DemoDraggableGroupWidget( config ) {
	// Configuration initialization
	config = config || {};

	// Parent constructor
	Demo.DraggableGroupWidget.parent.call( this, config );

	// Mixin constructors
	OO.ui.mixin.DraggableGroupElement.call( this, $.extend( {}, config, { $group: this.$element } ) );
};

/* Setup */
OO.inheritClass( Demo.DraggableGroupWidget, OO.ui.Widget );
OO.mixinClass( Demo.DraggableGroupWidget, OO.ui.mixin.DraggableGroupElement );
