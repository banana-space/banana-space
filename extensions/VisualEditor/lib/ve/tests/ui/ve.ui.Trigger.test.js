/*!
 * VisualEditor UserInterface Trigger tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.Trigger' );

/* Tests */

QUnit.test( 'constructor', function ( assert ) {
	var i, len, tests;
	function event( options ) {
		return $.Event( 'keydown', options );
	}

	tests = [
		{
			trigger: 'ctrl+b',
			event: event( { ctrlKey: true, which: 66 } )
		}
	];

	for ( i = 0, len = tests.length; i < len; i++ ) {
		assert.strictEqual(
			new ve.ui.Trigger( tests[ i ].trigger ).toString(),
			tests[ i ].trigger,
			'trigger is parsed correctly'
		);
		assert.strictEqual(
			new ve.ui.Trigger( tests[ i ].event ).toString(),
			tests[ i ].trigger,
			'event is parsed correctly'
		);
	}
} );
