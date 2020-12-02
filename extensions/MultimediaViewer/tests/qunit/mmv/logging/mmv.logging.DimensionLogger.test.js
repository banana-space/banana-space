( function () {
	QUnit.module( 'mmv.logging.DimensionLogger', QUnit.newMwEnvironment() );

	QUnit.test( 'log()', function ( assert ) {
		var fakeEventLog = { logEvent: this.sandbox.stub() },
			logger = new mw.mmv.logging.DimensionLogger();

		this.sandbox.stub( logger, 'loadDependencies' ).returns( $.Deferred().resolve() );
		this.sandbox.stub( mw, 'log' );

		logger.samplingFactor = 1;
		logger.setEventLog( fakeEventLog );

		logger.logDimensions( 640, 480, 200, 'resize' );
		assert.ok( true, 'logDimensions() did not throw errors' );
	} );
}() );
