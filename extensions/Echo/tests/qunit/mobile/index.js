mw.template.add( 'ext.echo.mobile', 'NotificationBadge.mustache',
	mw.template.get( 'test.Echo', 'NotificationBadge.mustache' ).getSource()
);

mw.loader.using( 'mobile.startup' ).then( function () {
	require( './test_NotificationBadge.js' );
} );
