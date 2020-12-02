( function () {
	QUnit.module( 'mmv.logging.AttributionLogger', QUnit.newMwEnvironment() );

	QUnit.test( 'log()', function ( assert ) {
		var fakeEventLog = { logEvent: this.sandbox.stub() },
			logger = new mw.mmv.logging.AttributionLogger(),
			image = { author: 'foo', source: 'bar', license: {} },
			emptyImage = {};

		this.sandbox.stub( logger, 'loadDependencies' ).returns( $.Deferred().resolve() );
		this.sandbox.stub( mw, 'log' );

		logger.samplingFactor = 1;
		logger.setEventLog( fakeEventLog );

		logger.logAttribution( image );
		assert.ok( true, 'logDimensions() did not throw errors' );

		logger.logAttribution( emptyImage );
		assert.ok( true, 'logDimensions() did not throw errors for empty image' );
	} );
}() );
