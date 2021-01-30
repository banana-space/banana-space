QUnit.module( 'ext.flow.dm mw.flow.dm.Content' );

/* Tests */

QUnit.test( 'Stores different content representations (formats)', function ( assert ) {
	var content = new mw.flow.dm.Content( {
		content: 'content in default format (wikitext, for instance)',
		format: 'wikitext',
		html: 'content in html format',
		plaintext: 'content in plaintext format',
		someNewFormat: 'content in some new format'
	} );

	assert.strictEqual( content.get( 'html' ), 'content in html format' );
	assert.strictEqual( content.get( 'wikitext' ), 'content in default format (wikitext, for instance)' );
	assert.strictEqual( content.get(), 'content in default format (wikitext, for instance)' );
	assert.strictEqual( content.get( 'unknown format' ), null );
} );

QUnit.test( 'Behaves when empty', function ( assert ) {
	var content = new mw.flow.dm.Content();

	assert.strictEqual( content.get(), null );
	assert.strictEqual( content.get( 'whatever format' ), null );
} );
