Demo.SearchWidgetDialog = function DemoSearchWidgetDialog( config ) {
	Demo.SearchWidgetDialog.parent.call( this, config );
	this.broken = false;
};
OO.inheritClass( Demo.SearchWidgetDialog, OO.ui.ProcessDialog );
Demo.SearchWidgetDialog.static.title = 'Search widget dialog';
Demo.SearchWidgetDialog.prototype.initialize = function () {
	var i, items, searchWidget;
	Demo.SearchWidgetDialog.parent.prototype.initialize.apply( this, arguments );
	items = [];
	searchWidget = new OO.ui.SearchWidget();
	for ( i = 1; i <= 20; i++ ) {
		items.push( new OO.ui.OptionWidget( { data: i, label: 'Item ' + i } ) );
	}
	searchWidget.results.addItems( items );
	searchWidget.onQueryChange = function () {};
	this.$body.append( searchWidget.$element );
};
Demo.SearchWidgetDialog.prototype.getBodyHeight = function () {
	return 300;
};
Demo.SearchWidgetDialog.static.actions = [
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] }
];
Demo.SearchWidgetDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	return new OO.ui.Process( function () {
		dialog.close( { action: action } );
	} );
};
