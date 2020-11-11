Demo.FailedSetupProcessDialog = function DemoFailedSetupProcessDialog( config ) {
	Demo.FailedSetupProcessDialog.parent.call( this, config );
};
OO.inheritClass( Demo.FailedSetupProcessDialog, Demo.SimpleDialog );
Demo.FailedSetupProcessDialog.prototype.getSetupProcess = function () {
	return Demo.FailedSetupProcessDialog.parent.prototype.getSetupProcess.call( this ).next( function () {
		return $.Deferred().reject().promise();
	} );
};
