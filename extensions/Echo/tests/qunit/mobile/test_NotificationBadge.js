( function ( M ) {
	var OverlayManager = M.require( 'mobile.startup' ).OverlayManager,
		NotificationBadge = require( '../../../modules/mobile/NotificationBadge.js' );

	QUnit.module( 'ext.echo.mobile - NotificationBadge', {
		beforeEach: function () {
			this.OverlayManager = OverlayManager.getSingleton();
		}
	} );

	QUnit.test( '#setCount', function ( assert ) {
		var initialClassExpectationsMet,
			badge = new NotificationBadge( {
				overlayManager: this.OverlayManager,
				hasNotifications: true,
				hasUnseenNotifications: true,
				notificationCountRaw: 5
			} );
		initialClassExpectationsMet = badge.$el.find( '.mw-ui-icon' ).length === 0 &&
			badge.$el.find( '.zero' ).length === 0;

		badge.setCount( 0 );
		assert.ok( initialClassExpectationsMet, 'No icon and no zero class' );
		assert.strictEqual( badge.$el.find( '.zero' ).length, 1, 'A zero class is present on the badge' );
		badge.setCount( 105 );
		assert.strictEqual( badge.options.notificationCountRaw, 100, 'Number is capped to 100.' );
	} );

	QUnit.test( '#setCount (Eastern Arabic numerals)', function ( assert ) {
		var badge;

		this.sandbox.stub( mw.language, 'convertNumber' )
			.withArgs( 2 ).returns( '۲' )
			.withArgs( 5 ).returns( '۵' );
		this.sandbox.stub( mw, 'message' )
			.withArgs( 'echo-badge-count', '۵' ).returns( { text: function () { return '۵'; } } )
			.withArgs( 'echo-badge-count', '۲' ).returns( { text: function () { return '۲'; } } );

		badge = new NotificationBadge( {
			overlayManager: this.OverlayManager,
			el: $( '<div><a title="n" href="/" class="notification-unseen"><div class="circle" ><span data-notification-count="2">۲</span></div></a></div>' )
		} );
		assert.strictEqual( badge.options.notificationCountRaw, 2,
			'Number is parsed from Eastern Arabic numerals' );
		assert.strictEqual( badge.options.notificationCountString, '۲',
			'Number will be rendered in Eastern Arabic numerals' );
		badge.setCount( 5 );
		assert.strictEqual( badge.options.notificationCountString, '۵',
			'Number will be rendered in Eastern Arabic numerals' );
	} );

	QUnit.test( '#render [hasUnseenNotifications]', function ( assert ) {
		var badge = new NotificationBadge( {
			notificationCountRaw: 0,
			overlayManager: this.OverlayManager,
			hasNotifications: false,
			hasUnseenNotifications: false
		} );
		assert.strictEqual( badge.$el.find( '.mw-ui-icon' ).length, 1, 'A bell icon is visible' );
	} );

	QUnit.test( '#markAsSeen', function ( assert ) {
		var badge = new NotificationBadge( {
			notificationCountRaw: 2,
			overlayManager: this.OverlayManager,
			hasNotifications: true,
			hasUnseenNotifications: true
		} );
		// Badge resets counter to zero
		badge.setCount( 0 );
		assert.strictEqual( badge.$el.find( '.mw-ui-icon' ).length, 0, 'The bell icon is not visible' );
		badge.markAsSeen();
		assert.strictEqual( badge.$el.find( '.notification-unseen' ).length, 0,
			'Unseen class disappears after markAsSeen called.' );
	} );
}( mw.mobileFrontend ) );
