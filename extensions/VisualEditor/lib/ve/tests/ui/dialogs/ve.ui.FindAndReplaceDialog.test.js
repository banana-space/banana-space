/*!
 * VisualEditor UserInterface FindAndReplaceDialog tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.FindAndReplaceDialog' );

/* Tests */

QUnit.test( 'find fragments', function ( assert ) {
	var done = assert.async(),
		surface = ve.test.utils.createSurfaceFromHtml( '<p>Foo bar fooq.</p><p>baz foob</p>' ),
		cases = [
			{
				msg: 'Simple case insensitive',
				find: 'Foo',
				ranges: [
					new ve.Range( 1, 4 ),
					new ve.Range( 9, 12 ),
					new ve.Range( 20, 23 )
				]
			},
			{
				msg: 'Simple case sensitive',
				find: 'Foo',
				matchCase: true,
				ranges: [
					new ve.Range( 1, 4 )
				]
			},
			{
				msg: 'Case insensitive regex',
				find: 'fo[^ ]+',
				regex: true,
				ranges: [
					new ve.Range( 1, 4 ),
					new ve.Range( 9, 14 ),
					new ve.Range( 20, 24 )
				]
			},
			{
				msg: 'Case sensitive regex',
				find: 'fo[^ ]+',
				regex: true,
				matchCase: true,
				ranges: [
					new ve.Range( 9, 14 ),
					new ve.Range( 20, 24 )
				]
			},
			{
				msg: 'Regex to end of line',
				find: 'q.*',
				regex: true,
				ranges: [
					new ve.Range( 12, 14 )
				]
			},
			{
				msg: 'Overlapping regex',
				find: '.*',
				regex: true,
				ranges: [
					new ve.Range( 1, 14 ),
					new ve.Range( 16, 24 )
				]
			},
			{
				msg: 'Invalid regex',
				find: '(.*',
				regex: true,
				ranges: []
			}
		];

	surface.getToolbarDialogs().getWindow( 'findAndReplace' ).then( function ( dialog ) {
		dialog.open( {
			surface: surface,
			fragment: surface.getModel().getFragment()
		} ).opening.then( function () {
			var i, ranges;
			for ( i = 0; i < cases.length; i++ ) {
				dialog.matchCaseToggle.setValue( !!cases[ i ].matchCase );
				dialog.regexToggle.setValue( !!cases[ i ].regex );
				dialog.findText.setValue( cases[ i ].find );
				ranges = dialog.fragments.map( function ( fragment ) {
					return fragment.getSelection().getRange();
				} );
				assert.deepEqual( ranges, cases[ i ].ranges, cases[ i ].msg );
				dialog.findText.setValue( '' );
			}
			done();
		} );
	} );

} );

QUnit.test( 'replace all', function ( assert ) {
	var done = assert.async(),
		surface = ve.test.utils.createSurfaceFromHtml( '<p>Foo bar fooq.</p><p>baz foob</p>' ),
		cases = [
			{
				msg: 'Simple case insensitive',
				find: 'Foo',
				replace: 'baz',
				expected: 'baz bar bazq.baz bazb'
			},
			{
				msg: 'Recursive',
				find: 'baz',
				replace: 'foofoo',
				expected: 'foofoo bar foofooq.foofoo foofoob'
			},
			{
				msg: 'Regex',
				find: '(foo)+',
				replace: 'X',
				regex: true,
				expected: 'X bar Xq.X Xb'
			}
		];

	surface.getToolbarDialogs().getWindow( 'findAndReplace' ).done( function ( dialog ) {
		dialog.open( {
			surface: surface,
			fragment: surface.getModel().getFragment()
		} ).opening.then( function () {
			var i;
			for ( i = 0; i < cases.length; i++ ) {
				dialog.matchCaseToggle.setValue( !!cases[ i ].matchCase );
				dialog.regexToggle.setValue( !!cases[ i ].regex );
				dialog.findText.setValue( cases[ i ].find );
				dialog.replaceText.setValue( cases[ i ].replace );
				dialog.onReplaceAllButtonClick();
				assert.strictEqual( surface.getModel().getDocument().data.getText(), cases[ i ].expected, cases[ i ].msg );
				dialog.findText.setValue( '' );
				dialog.replaceText.setValue( '' );
			}
			done();
		} ).fail( function ( ex ) {
			assert.ok( false, 'Error thrown: ' + ex.stack );
			done();
		} );
	} );

} );
