( function () {
	QUnit.module( 'mmv.ui.description', QUnit.newMwEnvironment() );

	QUnit.test( 'Sanity test, object creation and UI construction', function ( assert ) {
		var description = new mw.mmv.ui.Description( $( '#qunit-fixture' ) );

		assert.ok( description, 'Image description UI element is created' );
		assert.strictEqual( description.$imageDescDiv.length, 1, 'Image description div is created' );
		assert.strictEqual( description.$imageDesc.length, 1, 'Image description element is created' );
	} );

	QUnit.test( 'Setting data in different combinations works well', function ( assert ) {
		var description = new mw.mmv.ui.Description( $( '#qunit-fixture' ) );

		description.set( null, null );
		assert.strictEqual( description.$imageDescDiv.hasClass( 'empty' ), true,
			'Image description div is marked empty when neither description nor caption is available' );

		description.set( null, 'foo' );
		assert.strictEqual( description.$imageDescDiv.hasClass( 'empty' ), true,
			'Image description div is marked empty when there is no description' );

		description.set( 'blah', null );
		assert.strictEqual( description.$imageDescDiv.hasClass( 'empty' ), true,
			'Image description div is marked empty when there is no caption (description will be shown in title)' );

		description.set( 'foo', 'bar' );
		assert.strictEqual( description.$imageDescDiv.hasClass( 'empty' ), false,
			'Image description div is not marked empty when both description and caption are available' );
		assert.strictEqual( description.$imageDesc.text(), 'foo',
			'Image description text is set correctly, caption is ignored' );
	} );

	QUnit.test( 'Emptying data works as expected', function ( assert ) {
		var description = new mw.mmv.ui.Description( $( '#qunit-fixture' ) );

		description.set( 'foo', 'bar' );
		description.empty();
		assert.strictEqual( description.$imageDescDiv.hasClass( 'empty' ), true, 'Image description div is marked empty when emptied' );
		assert.strictEqual( description.$imageDesc.text(), '', 'Image description text is emptied correctly' );
	} );
}() );
