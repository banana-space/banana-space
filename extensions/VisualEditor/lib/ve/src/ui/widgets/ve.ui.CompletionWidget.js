/*!
 * VisualEditor UserInterface CompletionWidget class.
 *
 * @copyright 2011-2019 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Widget that displays autocompletion suggestions
 *
 * @class
 * @extends OO.ui.Widget
 *
 * @constructor
 * @param {ve.ui.Surface} surface Surface to complete into
 * @param {Object} [config] Configuration options
 * @cfg {Object} [validate] Validation pattern passed to TextInputWidgets
 * @cfg {boolean} [readOnly=false] Prevent changes to the value of the widget.
 */
ve.ui.CompletionWidget = function VeUiCompletionWidget( surface, config ) {
	var $doc = surface.getView().getDocument().getDocumentNode().$element;

	this.surface = surface;
	this.surfaceModel = surface.getModel();

	// Configuration
	config = config || {
		anchor: false
	};

	// Parent constructor
	ve.ui.CompletionWidget.super.call( this, config );

	this.$tabIndexed = this.$element;

	this.menu = new OO.ui.MenuSelectWidget( {
		widget: this,
		$input: $doc,
		width: 'auto'
	} );

	// Events
	this.menu.connect( this, { choose: 'onMenuChoose' } );

	// Setup
	this.$element.addClass( 've-ui-completionWidget' )
		.append(
			this.menu.$element
		);
};

/* Inheritance */

OO.inheritClass( ve.ui.CompletionWidget, OO.ui.Widget );

ve.ui.CompletionWidget.prototype.setup = function ( action ) {
	var offset = this.surfaceModel.getSelection().getRange();
	if ( !offset.isCollapsed() ) {
		return;
	}
	this.action = action;
	this.initialOffset = offset.end - this.action.constructor.static.triggerLength;

	this.update();

	this.surfaceModel.connect( this, { select: 'onModelSelect' } );
};

ve.ui.CompletionWidget.prototype.teardown = function () {
	this.menu.toggle( false );
	this.surfaceModel.disconnect( this );
	this.action = undefined;
};

ve.ui.CompletionWidget.prototype.update = function () {
	var direction = this.surface.getDir(),
		range = this.getCompletionRange(),
		boundingRect = this.surface.getView().getSelection( new ve.dm.LinearSelection( range ) ).getSelectionBoundingRect(),
		style = {
			top: boundingRect.bottom
		},
		data = this.surfaceModel.getDocument().data,
		input = data.getText( false, range );

	if ( direction === 'rtl' ) {
		// This works because this.$element is a 0x0px box, with the menu positioned relative to it.
		// If this style was applied to the menu, we'd need to do some math here to align the right
		// edge of the menu with the right edge of the selection.
		style.left = boundingRect.right;
	} else {
		style.left = boundingRect.left;
	}
	this.$element.css( style );

	this.action.getSuggestions( input ).then( function ( items ) {
		this.menu.clearItems().addItems( items.map( this.action.getMenuItemForSuggestion.bind( this.action ) ) );
		if ( this.menu.getItems().length ) {
			this.menu.highlightItem( this.menu.getItems()[ 0 ] );
		}
		this.menu.toggle( true );
	}.bind( this ) );
};

ve.ui.CompletionWidget.prototype.onMenuChoose = function ( item ) {
	var fragment = this.action.insertCompletion( item.getData(), this.getCompletionRange( true ) );

	fragment.collapseToEnd().select();

	this.teardown();
};

ve.ui.CompletionWidget.prototype.onModelSelect = function () {
	var range = this.getCompletionRange();
	if ( !range || range.isBackwards() || this.action.shouldAbandon( this.surfaceModel.getDocument().data.getText( false, range ), this.menu.getItems().length ) ) {
		this.teardown();
	} else {
		this.update();
	}
};

ve.ui.CompletionWidget.prototype.getCompletionRange = function ( withTrigger ) {
	var range = this.surfaceModel.getSelection().getCoveringRange();
	if ( !range || !range.isCollapsed() || !this.action ) {
		return null;
	}
	return new ve.Range( this.initialOffset + ( withTrigger ? 0 : this.action.constructor.static.triggerLength ), range.end );
};
