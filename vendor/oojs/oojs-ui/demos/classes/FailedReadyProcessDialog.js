Demo.FailedReadyProcessDialog = function DemoFailedReadyProcessDialog( config ) {
	Demo.FailedReadyProcessDialog.parent.call( this, config );
};
OO.inheritClass( Demo.FailedReadyProcessDialog, Demo.SimpleDialog );
Demo.FailedReadyProcessDialog.prototype.getReadyProcess = function () {
	return Demo.FailedReadyProcessDialog.parent.prototype.getReadyProcess.call( this ).next( function () {
		return $.Deferred().reject().promise();
	} );
};
