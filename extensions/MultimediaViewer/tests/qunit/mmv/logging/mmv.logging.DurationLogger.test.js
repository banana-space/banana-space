( function ( mw, $ ) {
	QUnit.module( 'mmv.logging.DurationLogger', QUnit.newMwEnvironment( {
		setup: function () {
			this.clock = this.sandbox.useFakeTimers();

			// since jQuery 2/3, $.now will capture a reference to Date.now
			// before above fake timer gets a chance to override it, so I'll
			// override that new behavior in order to run these tests...
			// @see https://github.com/sinonjs/lolex/issues/76
			this.oldNow = $.now;
			$.now = function () { return +( new Date() ); };
		},

		teardown: function () {
			$.now = this.oldNow;
			this.clock.restore();
		}
	} ) );

	QUnit.test( 'start()', function ( assert ) {
		var durationLogger = new mw.mmv.durationLogger.constructor();
		durationLogger.samplingFactor = 1;

		try {
			durationLogger.start();
		} catch ( e ) {
			assert.ok( true, 'Exception raised when calling start() without parameters' );
		}
		assert.ok( $.isEmptyObject( durationLogger.starts ), 'No events saved by DurationLogger' );

		durationLogger.start( 'foo' );
		assert.strictEqual( durationLogger.starts.foo, 0, 'Event start saved' );

		this.clock.tick( 1000 );
		durationLogger.start( 'bar' );
		assert.strictEqual( durationLogger.starts.bar, 1000, 'Later event start saved' );

		durationLogger.start( 'foo' );
		assert.strictEqual( durationLogger.starts.foo, 0, 'Event start not overritten' );

		this.clock.tick( 666 );
		durationLogger.start( [ 'baz', 'bob', 'bar' ] );
		assert.strictEqual( durationLogger.starts.baz, 1666, 'First simultaneous event start saved' );
		assert.strictEqual( durationLogger.starts.bob, 1666, 'Second simultaneous event start saved' );
		assert.strictEqual( durationLogger.starts.bar, 1000, 'Third simultaneous event start not overwritten' );
	} );

	QUnit.test( 'stop()', function ( assert ) {
		var durationLogger = new mw.mmv.durationLogger.constructor();

		try {
			durationLogger.stop();
		} catch ( e ) {
			assert.ok( true, 'Exception raised when calling stop() without parameters' );
		}

		durationLogger.stop( 'foo' );

		assert.strictEqual( durationLogger.stops.foo, 0, 'Event stop saved' );

		this.clock.tick( 1000 );
		durationLogger.stop( 'foo' );

		assert.strictEqual( durationLogger.stops.foo, 0, 'Event stop not overwritten' );

		durationLogger.stop( 'foo', 1 );

		assert.strictEqual( durationLogger.starts.foo, 1, 'Event start saved' );

		durationLogger.stop( 'foo', 2 );

		assert.strictEqual( durationLogger.starts.foo, 1, 'Event start not overwritten' );
	} );

	QUnit.test( 'record()', function ( assert ) {
		var dependenciesDeferred = $.Deferred(),
			fakeEventLog = { logEvent: this.sandbox.stub() },
			durationLogger = new mw.mmv.durationLogger.constructor();

		durationLogger.samplingFactor = 1;
		durationLogger.schemaSupportsCountry = this.sandbox.stub().returns( true );

		this.sandbox.stub( mw.user, 'isAnon' ).returns( false );
		this.sandbox.stub( durationLogger, 'loadDependencies' ).returns( dependenciesDeferred.promise() );

		try {
			durationLogger.record();
		} catch ( e ) {
			assert.ok( true, 'Exception raised when calling record() without parameters' );
		}

		durationLogger.setEventLog( fakeEventLog );

		durationLogger.start( 'bar' );
		this.clock.tick( 1000 );
		durationLogger.stop( 'bar' );
		durationLogger.record( 'bar' );

		assert.ok( !fakeEventLog.logEvent.called, 'Event queued if dependencies not loaded' );

		// Queue a second item

		durationLogger.start( 'bob' );
		this.clock.tick( 4000 );
		durationLogger.stop( 'bob' );
		durationLogger.record( 'bob' );

		assert.ok( !fakeEventLog.logEvent.called, 'Event queued if dependencies not loaded' );

		dependenciesDeferred.resolve();
		this.clock.tick( 10 );

		assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 0 ], 'MultimediaViewerDuration', 'EventLogging schema is correct' );
		assert.deepEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ], { type: 'bar', duration: 1000, loggedIn: true, samplingFactor: 1 },
			'EventLogging data is correct' );

		assert.strictEqual( fakeEventLog.logEvent.getCall( 1 ).args[ 0 ], 'MultimediaViewerDuration', 'EventLogging schema is correct' );
		assert.deepEqual( fakeEventLog.logEvent.getCall( 1 ).args[ 1 ], { type: 'bob', duration: 4000, loggedIn: true, samplingFactor: 1 },
			'EventLogging data is correct' );

		assert.strictEqual( fakeEventLog.logEvent.callCount, 2, 'logEvent called when processing the queue' );

		durationLogger.start( 'foo' );
		this.clock.tick( 3000 );
		durationLogger.stop( 'foo' );
		durationLogger.record( 'foo' );
		this.clock.tick( 10 );

		assert.strictEqual( fakeEventLog.logEvent.getCall( 2 ).args[ 0 ], 'MultimediaViewerDuration', 'EventLogging schema is correct' );
		assert.deepEqual( fakeEventLog.logEvent.getCall( 2 ).args[ 1 ], { type: 'foo', duration: 3000, loggedIn: true, samplingFactor: 1 },
			'EventLogging data is correct' );

		assert.strictEqual( durationLogger.starts.bar, undefined, 'Start value deleted after record' );
		assert.strictEqual( durationLogger.stops.bar, undefined, 'Stop value deleted after record' );

		durationLogger.setGeo( { country: 'FR' } );
		mw.user.isAnon.returns( true );

		durationLogger.start( 'baz' );
		this.clock.tick( 2000 );
		durationLogger.stop( 'baz' );
		durationLogger.record( 'baz' );
		this.clock.tick( 10 );

		assert.strictEqual( fakeEventLog.logEvent.getCall( 3 ).args[ 0 ], 'MultimediaViewerDuration', 'EventLogging schema is correct' );
		assert.deepEqual( fakeEventLog.logEvent.getCall( 3 ).args[ 1 ], { type: 'baz', duration: 2000, loggedIn: false, country: 'FR', samplingFactor: 1 },
			'EventLogging data is correct' );

		assert.strictEqual( durationLogger.starts.bar, undefined, 'Start value deleted after record' );
		assert.strictEqual( durationLogger.stops.bar, undefined, 'Stop value deleted after record' );

		durationLogger.stop( 'fooz', $.now() - 9000 );
		durationLogger.record( 'fooz' );
		this.clock.tick( 10 );

		assert.deepEqual( fakeEventLog.logEvent.getCall( 4 ).args[ 1 ], { type: 'fooz', duration: 9000, loggedIn: false, country: 'FR', samplingFactor: 1 },
			'EventLogging data is correct' );

		assert.strictEqual( fakeEventLog.logEvent.callCount, 5, 'logEvent has been called fives times at this point in the test' );

		durationLogger.stop( 'foo' );
		durationLogger.record( 'foo' );
		this.clock.tick( 10 );

		assert.strictEqual( fakeEventLog.logEvent.callCount, 5, 'Record without a start doesn\'t get logged' );

		durationLogger.start( 'foofoo' );
		durationLogger.record( 'foofoo' );
		this.clock.tick( 10 );

		assert.strictEqual( fakeEventLog.logEvent.callCount, 5, 'Record without a stop doesn\'t get logged' );

		durationLogger.start( 'extra' );
		this.clock.tick( 5000 );
		durationLogger.stop( 'extra' );
		durationLogger.record( 'extra', { bim: 'bam' } );
		this.clock.tick( 10 );

		assert.deepEqual( fakeEventLog.logEvent.getCall( 5 ).args[ 1 ], { type: 'extra', duration: 5000, loggedIn: false, country: 'FR', samplingFactor: 1, bim: 'bam' },
			'EventLogging data is correct' );
	} );

	QUnit.test( 'loadDependencies()', function ( assert ) {
		var promise,
			durationLogger = new mw.mmv.durationLogger.constructor();

		this.sandbox.stub( mw.loader, 'using' );

		mw.loader.using.withArgs( [ 'ext.eventLogging', 'schema.MultimediaViewerDuration' ] ).throwsException( 'EventLogging is missing' );

		promise = durationLogger.loadDependencies();
		this.clock.tick( 10 );

		assert.strictEqual( promise.state(), 'rejected', 'Promise is rejected' );

		// It's necessary to reset the stub, otherwise the original withArgs keeps running alongside the new one
		mw.loader.using.restore();
		this.sandbox.stub( mw.loader, 'using' );

		mw.loader.using.withArgs( [ 'ext.eventLogging', 'schema.MultimediaViewerDuration' ] ).throwsException( 'EventLogging is missing' );

		promise = durationLogger.loadDependencies();
		this.clock.tick( 10 );

		assert.strictEqual( promise.state(), 'rejected', 'Promise is rejected' );

		// It's necessary to reset the stub, otherwise the original withArgs keeps running alongside the new one
		mw.loader.using.restore();
		this.sandbox.stub( mw.loader, 'using' );

		mw.loader.using.withArgs( [ 'ext.eventLogging', 'schema.MultimediaViewerDuration' ] ).callsArg( 1 );

		promise = durationLogger.loadDependencies();
		this.clock.tick( 10 );

		assert.strictEqual( promise.state(), 'resolved', 'Promise is resolved' );
	} );
}( mediaWiki, jQuery ) );
