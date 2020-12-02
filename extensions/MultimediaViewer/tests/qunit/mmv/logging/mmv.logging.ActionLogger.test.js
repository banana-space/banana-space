( function () {
	QUnit.module( 'mmv.logging.ActionLogger', QUnit.newMwEnvironment() );

	QUnit.test( 'log()', function ( assert ) {
		var fakeEventLog = { logEvent: this.sandbox.stub() },
			logger = new mw.mmv.logging.ActionLogger(),
			action1key = 'test-1',
			action1value = 'Test',
			action2key = 'test-2',
			action2value = 'Foo $1 $2 bar',
			unknownAction = 'test-3',
			clock = this.sandbox.useFakeTimers();

		this.sandbox.stub( logger, 'loadDependencies' ).returns( $.Deferred().resolve() );
		this.sandbox.stub( mw, 'log' );

		logger.samplingFactorMap = { default: 1 };
		logger.setEventLog( fakeEventLog );
		logger.logActions = {};
		logger.logActions[ action1key ] = action1value;
		logger.logActions[ action2key ] = action2value;

		logger.log( unknownAction );
		clock.tick( 10 );

		assert.strictEqual( mw.log.lastCall.args[ 0 ], unknownAction, 'Log message defaults to unknown key' );
		assert.strictEqual( fakeEventLog.logEvent.called, true, 'event log has been recorded' );

		mw.log.reset();
		fakeEventLog.logEvent.reset();
		logger.log( action1key );
		clock.tick( 10 );

		assert.strictEqual( mw.log.lastCall.args[ 0 ], action1value, 'Log message is translated to its text' );
		assert.strictEqual( fakeEventLog.logEvent.called, true, 'event log has been recorded' );

		mw.log.reset();
		fakeEventLog.logEvent.reset();
		logger.samplingFactorMap = { default: 0 };
		logger.log( action1key, true );
		clock.tick( 10 );

		assert.strictEqual( mw.log.called, false, 'No logging when disabled' );
		assert.strictEqual( fakeEventLog.logEvent.called, true, 'event log has been recorded' );

		clock.restore();
	} );
}() );
