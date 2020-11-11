Demo.LongProcessDialog = function DemoLongProcessDialog( config ) {
	Demo.LongProcessDialog.parent.call( this, config );
};
OO.inheritClass( Demo.LongProcessDialog, OO.ui.ProcessDialog );
Demo.LongProcessDialog.static.title = 'Process dialog';
Demo.LongProcessDialog.static.actions = [
	{ action: 'save', label: 'Done', flags: [ 'primary', 'progressive' ] },
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] },
	{ action: 'other', label: 'Other', flags: 'other' }
];
Demo.LongProcessDialog.prototype.initialize = function () {
	var i;

	Demo.LongProcessDialog.parent.prototype.initialize.apply( this, arguments );
	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	for ( i = 0; i < 100; i++ ) {
		this.content.$element.append( '<p>Dialog content</p>' );
	}
	this.$body.append( this.content.$element );
};
Demo.LongProcessDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if ( action ) {
		return new OO.ui.Process( function () {
			dialog.close( { action: action } );
		} );
	}
	return Demo.LongProcessDialog.parent.prototype.getActionProcess.call( this, action );
};
Demo.LongProcessDialog.prototype.getBodyHeight = function () {
	return this.content.$element.outerHeight( true );
};
