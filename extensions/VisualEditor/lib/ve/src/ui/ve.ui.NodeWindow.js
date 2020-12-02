/*!
 * VisualEditor user interface NodeWindow class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Mixin for window for working with a node.
 *
 * @class
 * @abstract
 * @extends ve.ui.FragmentWindow
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.NodeWindow = function VeUiNodeWindow() {
	// Parent method
	ve.ui.NodeWindow.super.apply( this, arguments );

	// Properties
	this.selectedNode = null;
};

/* Inheritance */

OO.inheritClass( ve.ui.NodeWindow, ve.ui.FragmentWindow );

/* Static Properties */

/**
 * Node classes compatible with this dialog.
 *
 * @static
 * @property {Function}
 * @inheritable
 */
ve.ui.NodeWindow.static.modelClasses = [];

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.NodeWindow.prototype.isEditing = function () {
	return !!this.getSelectedNode();
};

/**
 * Get the selected node.
 *
 * Should only be called after setup and before teardown.
 * If no node is selected or the selected node is incompatible, null will be returned.
 *
 * @param {Object} [data] Window opening data
 * @return {ve.dm.Node|null} Selected node
 */
ve.ui.NodeWindow.prototype.getSelectedNode = function () {
	var modelClasses = this.constructor.static.modelClasses,
		selectedNode = this.getFragment().getSelectedNode();

	if (
		selectedNode &&
		modelClasses.some( function ( modelClass ) {
			return selectedNode instanceof modelClass;
		} )
	) {
		return selectedNode;
	}
	return null;
};

/**
 * @inheritdoc
 */
ve.ui.NodeWindow.prototype.getSetupProcess = function ( data, process ) {
	// Parent method
	return ve.ui.NodeWindow.super.prototype.getSetupProcess.call( this, data, process )
		.next( function () {
			this.selectedNode = this.getSelectedNode( data );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.NodeWindow.prototype.getTeardownProcess = function ( data, process ) {
	return ve.ui.NodeWindow.super.prototype.getTeardownProcess.call( this, data, process )
		.next( function () {
			this.selectedNode = null;
		}, this );
};
