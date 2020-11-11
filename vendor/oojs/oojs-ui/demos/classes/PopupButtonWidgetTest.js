Demo.PopupButtonWidgetTest = function DemoPopupButtonWidgetTest( config ) {
	Demo.PopupButtonWidgetTest.parent.call( this, config );
};
OO.inheritClass( Demo.PopupButtonWidgetTest, OO.ui.ProcessDialog );
Demo.PopupButtonWidgetTest.static.title = 'PopupButtonWidget test';
Demo.PopupButtonWidgetTest.static.actions = [
	{ action: 'save', label: 'Done', flags: [ 'primary', 'progressive' ] },
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] }
];
Demo.PopupButtonWidgetTest.prototype.initialize = function () {
	Demo.PopupButtonWidgetTest.parent.prototype.initialize.apply( this, arguments );

	this.panel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );

	this.$center = $( '<td>' ).attr( { colspan: 3, rowspan: 3 } );

	this.toggleOverlayWidget = new OO.ui.ToggleSwitchWidget( { value: true } );
	this.toggleAnchorWidget = new OO.ui.ToggleSwitchWidget( { value: true } );
	this.showAllWidget = new OO.ui.ButtonWidget( { label: 'Toggle all' } );

	this.toggleOverlayWidget.connect( this, { change: 'makeTable' } );
	this.toggleAnchorWidget.connect( this, { change: 'makeTable' } );
	this.showAllWidget.connect( this, { click: 'toggleAll' } );

	this.fieldset = new OO.ui.FieldsetLayout( {
		items: [
			new OO.ui.FieldLayout( this.toggleAnchorWidget, {
				align: 'inline',
				label: 'Enable anchors'
			} ),
			new OO.ui.FieldLayout( this.toggleOverlayWidget, {
				align: 'inline',
				label: 'Use overlay'
			} ),
			new OO.ui.FieldLayout( this.showAllWidget, {
				align: 'top'
			} )
		]
	} );

	this.$center.append( this.fieldset.$element );

	this.makeTable();

	this.$body.append( this.panel.$element );
};
Demo.PopupButtonWidgetTest.prototype.makeContents = function () {
	var loremIpsum = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' +
		'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\u200E';
	return $( [] )
		.add( $( '<p>' ).text( loremIpsum ) )
		.add( $( '<p>' ).text( loremIpsum ) )
		.add( $( '<p>' ).text( loremIpsum ) );
};
Demo.PopupButtonWidgetTest.prototype.makeTable = function () {
	this.buttons = [];
	if ( this.$table ) {
		this.$table.detach();
	}

	this.$table = $( '<table>' ).append(
		$( '<tr>' ).append(
			$( '<td>' ),
			$( '<td>' ).append( this.getButton( 'above', 'backwards' ).$element ),
			$( '<td>' ).append( this.getButton( 'above', 'center' ).$element ),
			$( '<td>' ).append( this.getButton( 'above', 'forwards' ).$element ),
			$( '<td>' )
		),
		$( '<tr>' ).append(
			$( '<td>' ).append( this.getButton( 'before', 'backwards' ).$element ),
			this.$center,
			$( '<td>' ).append( this.getButton( 'after', 'backwards' ).$element ).css( 'text-align', this.getDir() === 'rtl' ? 'left' : 'right' )
		),
		$( '<tr>' ).append(
			$( '<td>' ).append( this.getButton( 'before', 'center' ).$element ),
			$( '<td>' ).append( this.getButton( 'after', 'center' ).$element ).css( 'text-align', this.getDir() === 'rtl' ? 'left' : 'right' )
		),
		$( '<tr>' ).append(
			$( '<td>' ).append( this.getButton( 'before', 'forwards' ).$element ),
			$( '<td>' ).append( this.getButton( 'after', 'forwards' ).$element ).css( 'text-align', this.getDir() === 'rtl' ? 'left' : 'right' )
		),
		$( '<tr>' ).append(
			$( '<td>' ),
			$( '<td>' ).append( this.getButton( 'below', 'backwards' ).$element ),
			$( '<td>' ).append( this.getButton( 'below', 'center' ).$element ),
			$( '<td>' ).append( this.getButton( 'below', 'forwards' ).$element ),
			$( '<td>' )
		)
	);
	this.panel.$element.append( this.$table );
};
Demo.PopupButtonWidgetTest.prototype.getButton = function ( position, align ) {
	var button = new OO.ui.PopupButtonWidget( {
		$overlay: ( this.toggleOverlayWidget.getValue() ? this.$overlay : null ),
		label: $( '<span>' ).append( position + '<br>' + align ),
		popup: {
			position: position,
			align: align,
			anchor: this.toggleAnchorWidget.getValue(),
			padded: true,
			$content: this.makeContents()
		}
	} );

	this.buttons.push( button );
	return button;
};
Demo.PopupButtonWidgetTest.prototype.toggleAll = function () {
	this.buttons.forEach( function ( button ) {
		button.getPopup().toggle();
	} );
};
Demo.PopupButtonWidgetTest.prototype.getActionProcess = function ( action ) {
	if ( action ) {
		return new OO.ui.Process( function () {
			this.close( { action: action } );
		}, this );
	}
	return Demo.PopupButtonWidgetTest.parent.prototype.getActionProcess.call( this, action );
};
