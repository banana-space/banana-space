( function () {
	QUnit.module( 'Thanks mobilediff' );

	QUnit.test( 'render button for logged in users', function ( assert ) {
		var $container = $( '<div>' ),
			$user = $( '<div>' ).data( 'user-name', 'jon' )
				.data( 'revision-id', 1 )
				.data( 'user-gender', 'male' );

		// eslint-disable-next-line no-underscore-dangle
		mw.thanks._mobileDiffInit( $user, $container );
		assert.strictEqual( $container.find( '.mw-ui-button' ).length, 1, 'Thanks button was created.' );
	} );

}() );
