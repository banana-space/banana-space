( function ( mw, $ ) {
	QUnit.module( 'mmv.logging.ViewLogger', QUnit.newMwEnvironment( {
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

	QUnit.test( 'unview()', function ( assert ) {
		var logger = { log: $.noop },
			viewLogger = new mw.mmv.logging.ViewLogger( { recordVirtualViewBeaconURI: $.noop }, {}, logger );

		this.sandbox.stub( logger, 'log' );

		viewLogger.unview();

		assert.ok( !logger.log.called, 'action logger not called' );

		viewLogger.setLastViewLogged( false );
		viewLogger.unview();

		assert.ok( !logger.log.called, 'action logger not called' );

		viewLogger.setLastViewLogged( true );
		viewLogger.unview();

		assert.ok( logger.log.calledOnce, 'action logger called' );

		viewLogger.unview();

		assert.ok( logger.log.calledOnce, 'action logger not called again' );
	} );

	QUnit.test( 'focus and blur', function ( assert ) {
		var fakeWindow = $( '<div>' ),
			viewLogger = new mw.mmv.logging.ViewLogger( { recordVirtualViewBeaconURI: $.noop }, fakeWindow, { log: $.noop } );

		this.clock.tick( 1 ); // This is just so that $.now() > 0 in the fake timer environment

		viewLogger.attach();

		this.clock.tick( 5 );

		fakeWindow.triggerHandler( 'blur' );

		this.clock.tick( 2 );

		fakeWindow.triggerHandler( 'focus' );

		this.clock.tick( 3 );

		fakeWindow.triggerHandler( 'blur' );

		this.clock.tick( 4 );

		assert.strictEqual( viewLogger.viewDuration, 8, 'Only focus duration was logged' );
	} );

	QUnit.test( 'stopViewDuration before startViewDuration', function ( assert ) {
		var viewLogger = new mw.mmv.logging.ViewLogger( { recordVirtualViewBeaconURI: $.noop }, {}, { log: $.noop } );

		this.clock.tick( 1 ); // This is just so that $.now() > 0 in the fake timer environment

		viewLogger.stopViewDuration();

		this.clock.tick( 2 );

		viewLogger.startViewDuration();

		this.clock.tick( 3 );

		viewLogger.stopViewDuration();

		assert.strictEqual( viewLogger.viewDuration, 3, 'Only last timeframe was logged' );
	} );
}( mediaWiki, jQuery ) );
