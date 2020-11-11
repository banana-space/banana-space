Demo.BrokenDialog = function DemoBrokenDialog( config ) {
	Demo.BrokenDialog.parent.call( this, config );
	this.broken = false;
};
OO.inheritClass( Demo.BrokenDialog, OO.ui.ProcessDialog );
Demo.BrokenDialog.static.title = 'Broken dialog';
Demo.BrokenDialog.static.actions = [
	{ action: 'save', label: 'Save', flags: [ 'primary', 'progressive' ] },
	{ action: 'delete', label: 'Delete', flags: 'destructive' },
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] }
];
Demo.BrokenDialog.prototype.getBodyHeight = function () {
	return 250;
};
Demo.BrokenDialog.prototype.initialize = function () {
	Demo.BrokenDialog.parent.prototype.initialize.apply( this, arguments );
	this.content = new OO.ui.PanelLayout( { padded: true } );
	this.fieldset = new OO.ui.FieldsetLayout( {
		label: 'Dialog with error handling',
		icon: 'alert'
	} );
	this.description = new OO.ui.LabelWidget( {
		label: 'Deleting will fail and will not be recoverable. ' +
			'Saving will fail the first time, but succeed the second time.'
	} );
	this.fieldset.addItems( [ this.description ] );
	this.content.$element.append( this.fieldset.$element );
	this.$body.append( this.content.$element );
};
Demo.BrokenDialog.prototype.getSetupProcess = function ( data ) {
	return Demo.BrokenDialog.parent.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.broken = true;
		}, this );
};
Demo.BrokenDialog.prototype.getActionProcess = function ( action ) {
	return Demo.BrokenDialog.parent.prototype.getActionProcess.call( this, action )
		.next( function () {
			return 1000;
		}, this )
		.next( function () {
			var state;

			if ( action === 'save' ) {
				if ( this.broken ) {
					this.broken = false;
					return new OO.ui.Error( 'Server did not respond' );
				}
			} else if ( action === 'delete' ) {
				return new OO.ui.Error( 'Permission denied', { recoverable: false } );
			}

			state = this.close( { action: action } );
			if ( action === 'save' ) {
				// Return a promise that is resolved when the dialog is closed,
				// so that it remains in "pending" state while closing
				return state.closed;
			}
			return Demo.BrokenDialog.parent.prototype.getActionProcess.call( this, action );
		}, this );
};
