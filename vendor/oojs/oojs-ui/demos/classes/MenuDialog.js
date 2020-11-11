Demo.MenuDialog = function DemoMenuDialog( config ) {
	Demo.MenuDialog.parent.call( this, config );
};
OO.inheritClass( Demo.MenuDialog, OO.ui.ProcessDialog );
Demo.MenuDialog.static.title = 'Menu dialog';
Demo.MenuDialog.static.actions = [
	{ action: 'save', label: 'Done', flags: [ 'primary', 'progressive' ] },
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] }
];
Demo.MenuDialog.prototype.getBodyHeight = function () {
	return 350;
};
Demo.MenuDialog.prototype.initialize = function () {
	var menuLayout, positionField, showField, expandField, menuPanel, contentPanel;
	Demo.MenuDialog.parent.prototype.initialize.apply( this, arguments );

	menuLayout = new OO.ui.MenuLayout();
	positionField = new OO.ui.FieldLayout(
		new OO.ui.ButtonSelectWidget( {
			items: [
				new OO.ui.ButtonOptionWidget( {
					data: 'before',
					label: 'Before'
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'after',
					label: 'After'
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'top',
					label: 'Top'
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'bottom',
					label: 'Bottom'
				} )
			]
		} ).on( 'select', function ( item ) {
			menuLayout.setMenuPosition( item.getData() );
		} ),
		{
			label: 'Menu position',
			align: 'top'
		}
	);
	showField = new OO.ui.FieldLayout(
		new OO.ui.ToggleSwitchWidget( { value: true } ).on( 'change', function ( value ) {
			menuLayout.toggleMenu( value );
		} ),
		{
			label: 'Show menu',
			align: 'top'
		}
	);
	expandField = new OO.ui.FieldLayout(
		new OO.ui.ToggleSwitchWidget( { value: true } ).on( 'change', function ( value ) {
			menuLayout.$element.toggleClass( 'oo-ui-menuLayout-expanded', value );
			menuLayout.$element.toggleClass( 'oo-ui-menuLayout-static', !value );
			menuPanel.$element.toggleClass( 'oo-ui-panelLayout-expanded', value );
			contentPanel.$element.toggleClass( 'oo-ui-panelLayout-expanded', value );
		} ),
		{
			label: 'Expand layout',
			align: 'top'
		}
	);
	menuPanel = new OO.ui.PanelLayout( { padded: true, expanded: true, scrollable: true } );
	contentPanel = new OO.ui.PanelLayout( { padded: true, expanded: true, scrollable: true } );

	menuLayout.$menu.append(
		menuPanel.$element.append( 'Menu panel' )
	);
	menuLayout.$content.append(
		contentPanel.$element.append(
			positionField.$element,
			expandField.$element,
			showField.$element
		)
	);

	this.$body.append( menuLayout.$element );
};
Demo.MenuDialog.prototype.getActionProcess = function ( action ) {
	if ( action ) {
		return new OO.ui.Process( function () {
			this.close( { action: action } );
		}, this );
	}
	return Demo.MenuDialog.parent.prototype.getActionProcess.call( this, action );
};
