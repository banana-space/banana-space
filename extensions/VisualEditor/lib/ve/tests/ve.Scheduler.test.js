/*!
 * VisualEditor Scheduler tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.Scheduler' );

/* Stubs */

ve.TestScheduler = function VeTestScheduler() {
	// Parent constructor
	ve.TestScheduler.super.apply( this, arguments );

	this.nowCounter = 0;
};

OO.inheritClass( ve.TestScheduler, ve.Scheduler );

ve.TestScheduler.static.maxDelay = 5;

ve.TestScheduler.prototype.now = function () {
	return this.nowCounter++;
};

/* Tests */

QUnit.test( 'Call with no real async', function ( assert ) {
	var scheduler = new ve.TestScheduler(),
		begun = assert.async(),
		done = assert.async();

	scheduler.schedule(
		function () {
			assert.ok( true, 'initial action was called' );
			begun();
		},
		function () {
			return true;
		}
	).done( function () {
		assert.ok( true, 'promise was resolved' );
		done();
	} );
} );

QUnit.test( 'Call with delay', function ( assert ) {
	var scheduler = new ve.TestScheduler(),
		begun = assert.async(),
		done = assert.async(),
		delayed = false;

	scheduler.schedule(
		function () {
			assert.ok( true, 'initial action was called' );
			setTimeout( function () {
				delayed = true;
				assert.ok( true, 'setTimeout delay occurred' );
			} );
			begun();
		},
		function () {
			return delayed;
		}
	).done( function () {
		assert.ok( true, 'promise was resolved' );
		done();
	} );
} );

QUnit.test( 'Test that throws an exception', function ( assert ) {
	var scheduler = new ve.TestScheduler(),
		begun = assert.async(),
		done = assert.async();

	scheduler.schedule(
		function () {
			assert.ok( true, 'initial action was called' );
			begun();
		},
		function () {
			throw new Error();
		}
	).done( function () {
		assert.ok( false, 'promise was wrongly resolved as successful' );
	} ).fail( function () {
		assert.ok( true, 'promise was rejected' );
		done();
	} );
} );

QUnit.test( 'Test that never succeeds', function ( assert ) {
	var scheduler = new ve.TestScheduler(),
		begun = assert.async(),
		done = assert.async();

	scheduler.schedule(
		function () {
			assert.ok( true, 'initial action was called' );
			begun();
		},
		function () {
			return false;
		}
	).done( function () {
		assert.ok( false, 'promise was wrongly resolved as successful' );
		done();
	} ).fail( function () {
		assert.ok( true, 'promise was rejected' );
		done();
	} );
} );
