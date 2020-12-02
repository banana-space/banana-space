/*!
 * VisualEditor UserInterface Actions ListAction tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.ListAction' );

/* Tests */

QUnit.test( '(un)wrap', function ( assert ) {
	var i,
		cases = [
			{
				rangeOrSelection: new ve.Range( 56, 60 ),
				method: 'wrap',
				style: 'bullet',
				expectedRangeOrSelection: new ve.Range( 58, 64 ),
				expectedData: function ( data ) {
					data.splice( 55, 0, { type: 'list', attributes: { style: 'bullet' } }, { type: 'listItem' } );
					data.splice( 60, 0, { type: '/listItem' }, { type: 'listItem' } );
					data.splice( 65, 0, { type: '/listItem' }, { type: '/list' } );
				},
				undo: true,
				msg: 'wrapping two paragraphs in a list'
			},
			{
				html: ve.dm.example.isolationHtml,
				rangeOrSelection: new ve.Range( 191, 211 ),
				method: 'unwrap',
				expectedRangeOrSelection: new ve.Range( 187, 205 ),
				expectedData: function ( data ) {
					delete data[ 190 ].internal;
					delete data[ 202 ].internal;
					data.splice( 186, 4 );
					data.splice( 196, 2 );
					data.splice( 206, 2,
						{ type: 'list', attributes: { style: 'bullet' } },
						{ type: 'listItem' },
						{ type: 'list', attributes: { style: 'number' } },
						{ type: 'listItem' }
					);
				},
				expectedOriginalData: function ( data ) {
					// generated: 'wrapper' is removed by the action and not restored by undo
					delete data[ 190 ].internal;
					delete data[ 202 ].internal;
				},
				undo: true,
				msg: 'unwrapping two double listed paragraphs'
			}
		];

	for ( i = 0; i < cases.length; i++ ) {
		ve.test.utils.runActionTest(
			'list', assert, cases[ i ].html, false, cases[ i ].method, [ cases[ i ].style ], cases[ i ].rangeOrSelection, cases[ i ].msg,
			{
				expectedData: cases[ i ].expectedData,
				expectedOriginalData: cases[ i ].expectedOriginalData,
				expectedRangeOrSelection: cases[ i ].expectedRangeOrSelection,
				undo: cases[ i ].undo
			}
		);
	}
} );
