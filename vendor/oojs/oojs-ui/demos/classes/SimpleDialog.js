Demo.SimpleDialog = function DemoSimpleDialog( config ) {
	Demo.SimpleDialog.parent.call( this, config );
};
OO.inheritClass( Demo.SimpleDialog, OO.ui.Dialog );
Demo.SimpleDialog.static.title = 'Simple dialog';
Demo.SimpleDialog.prototype.initialize = function () {
	var closeButton,
		dialog = this;

	Demo.SimpleDialog.parent.prototype.initialize.apply( this, arguments );
	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.content.$element.append( '<p>Dialog content</p>' );

	closeButton = new OO.ui.ButtonWidget( {
		label: OO.ui.msg( 'ooui-dialog-process-dismiss' )
	} );
	closeButton.on( 'click', function () {
		dialog.close();
	} );

	this.content.$element.append( closeButton.$element );
	this.$body.append( this.content.$element );
};
Demo.SimpleDialog.prototype.getBodyHeight = function () {
	return this.content.$element.outerHeight( true );
};
