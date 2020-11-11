Demo.DelayedReadyProcessDialog = function DemoDelayedReadyProcessDialog( config ) {
	Demo.DelayedReadyProcessDialog.parent.call( this, config );
};
OO.inheritClass( Demo.DelayedReadyProcessDialog, Demo.SimpleDialog );
Demo.DelayedReadyProcessDialog.prototype.getReadyProcess = function () {
	return Demo.DelayedReadyProcessDialog.parent.prototype.getReadyProcess.call( this ).next( function () {
		var deferred = $.Deferred();
		setTimeout( function () {
			deferred.resolve();
		}, 2000 );
		return deferred.promise();
	} );
};
