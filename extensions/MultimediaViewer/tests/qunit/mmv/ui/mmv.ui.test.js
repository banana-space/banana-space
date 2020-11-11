( function ( mw, $ ) {
	QUnit.module( 'mmv.ui', QUnit.newMwEnvironment( {
		setup: function () {
			this.clock = this.sandbox.useFakeTimers();
		}
	} ) );

	QUnit.test( 'handleEvent()', function ( assert ) {
		var element = new mw.mmv.ui.Element( $( '<div>' ) );

		element.handleEvent( 'mmv-foo', function () {
			assert.ok( true, 'Event is handled' );
		} );

		$( document ).trigger( new $.Event( 'mmv-foo' ) );

		element.clearEvents();

		$( document ).trigger( new $.Event( 'mmv-foo' ) );
	} );

	QUnit.test( 'setInlineStyle()', function ( assert ) {
		var element = new mw.mmv.ui.Element( $( '<div>' ) ),
			$testDiv = $( '<div id="mmv-testdiv">!!!</div>' ).appendTo( '#qunit-fixture' );

		assert.ok( $testDiv.is( ':visible' ), 'Test div is visible' );

		element.setInlineStyle( 'test', '#mmv-testdiv { display: none; }' );

		assert.ok( !$testDiv.is( ':visible' ), 'Test div is hidden by inline style' );

		element.setInlineStyle( 'test', null );

		assert.ok( $testDiv.is( ':visible' ), 'Test div is visible again' );
	} );

	QUnit.test( 'setTimer()/clearTimer()/resetTimer()', function ( assert ) {
		var element = new mw.mmv.ui.Element( $( '<div>' ) ),
			element2 = new mw.mmv.ui.Element( $( '<div>' ) ),
			spy = this.sandbox.spy(),
			spy2 = this.sandbox.spy();

		element.setTimer( 'foo', spy, 10 );
		this.clock.tick( 100 );
		assert.ok( spy.called, 'Timeout callback was called' );
		assert.ok( spy.calledOnce, 'Timeout callback was called once' );
		assert.ok( spy.calledOn( element ), 'Timeout callback was called on the element' );

		spy = this.sandbox.spy();
		element.setTimer( 'foo', spy, 10 );
		element.setTimer( 'foo', spy2, 20 );
		this.clock.tick( 100 );
		assert.ok( !spy.called, 'Old timeout callback was not called after update' );
		assert.ok( spy2.called, 'New timeout callback was called after update' );

		spy = this.sandbox.spy();
		spy2 = this.sandbox.spy();
		element.setTimer( 'foo', spy, 10 );
		element.setTimer( 'bar', spy2, 20 );
		this.clock.tick( 100 );
		assert.ok( spy.called && spy2.called, 'Timeouts with different names do not conflict' );

		spy = this.sandbox.spy();
		spy2 = this.sandbox.spy();
		element.setTimer( 'foo', spy, 10 );
		element2.setTimer( 'foo', spy2, 20 );
		this.clock.tick( 100 );
		assert.ok( spy.called && spy2.called, 'Timeouts in different elements do not conflict' );

		spy = this.sandbox.spy();
		element.setTimer( 'foo', spy, 10 );
		element.clearTimer( 'foo' );
		this.clock.tick( 100 );
		assert.ok( !spy.called, 'Timeout is invalidated by clearing' );

		spy = this.sandbox.spy();
		element.setTimer( 'foo', spy, 100 );
		this.clock.tick( 80 );
		element.resetTimer( 'foo' );
		this.clock.tick( 80 );
		assert.ok( !spy.called, 'Timeout is reset' );
		this.clock.tick( 80 );
		assert.ok( spy.called, 'Timeout works after reset' );

		spy = this.sandbox.spy();
		element.setTimer( 'foo', spy, 100 );
		this.clock.tick( 80 );
		element.resetTimer( 'foo', 200 );
		this.clock.tick( 180 );
		assert.ok( !spy.called, 'Timeout is reset to the designated delay' );
		this.clock.tick( 80 );
		assert.ok( spy.called, 'Timeout works after changing the delay' );
	} );

	QUnit.test( 'correctEW()', function ( assert ) {
		var element = new mw.mmv.ui.Element( $( '<div>' ) );

		element.isRTL = this.sandbox.stub().returns( true );

		assert.strictEqual( element.correctEW( 'e' ), 'w', 'e (east) is flipped' );
		assert.strictEqual( element.correctEW( 'ne' ), 'nw', 'ne (northeast) is flipped' );
		assert.strictEqual( element.correctEW( 'W' ), 'E', 'uppercase is flipped' );
		assert.strictEqual( element.correctEW( 's' ), 's', 'non-horizontal directions are ignored' );

		element.isRTL.returns( false );

		assert.strictEqual( element.correctEW( 'e' ), 'e', 'no flipping in LTR documents' );
	} );
}( mediaWiki, jQuery ) );
