( function ( mw ) {
	QUnit.module( 'mmv.lightboximage', QUnit.newMwEnvironment() );

	QUnit.test( 'Sanity test, object creation', function ( assert ) {
		var lightboxImage = new mw.mmv.LightboxImage( 'foo.png' );

		assert.ok( lightboxImage, 'Object created !' );
	} );

}( mediaWiki, jQuery ) );
