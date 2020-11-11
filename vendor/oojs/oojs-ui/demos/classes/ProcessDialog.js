Demo.ProcessDialog = function DemoProcessDialog( config ) {
	Demo.ProcessDialog.parent.call( this, config );
};
OO.inheritClass( Demo.ProcessDialog, OO.ui.ProcessDialog );
Demo.ProcessDialog.static.title = 'Process dialog';
Demo.ProcessDialog.static.actions = [
	{ action: 'save', label: 'Done', flags: [ 'primary', 'progressive' ] },
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] },
	{ action: 'other', label: 'Other', flags: 'other' }
];
Demo.ProcessDialog.prototype.initialize = function () {
	var $content;
	Demo.ProcessDialog.parent.prototype.initialize.apply( this, arguments );

	$content = $( '<div>' ).append(
		$( '<p>' ).text( 'Dialog content' ),
		$( '<a>' )
			.text( 'Be alert!' )
			.on( 'click', function () {
				OO.ui.alert( 'You are alert!' );
			} )
	);

	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.content.$element.append( $content );
	this.$body.append( this.content.$element );
};
Demo.ProcessDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if ( action ) {
		return new OO.ui.Process( function () {
			dialog.close( { action: action } );
		} );
	}
	return Demo.ProcessDialog.parent.prototype.getActionProcess.call( this, action );
};
Demo.ProcessDialog.prototype.getBodyHeight = function () {
	return this.content.$element.outerHeight( true );
};
