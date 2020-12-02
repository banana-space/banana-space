/*!
 * VisualEditor UserInterface Sequence tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.Sequence' );

/* Tests */

QUnit.test( 'checkSequences', function ( assert ) {
	var i, view, model, expectedSelection,
		emptyDocData = [ { type: 'paragraph' }, { type: '/paragraph' }, { type: 'internalList' }, { type: '/internalList' } ],
		tests = [
			{
				content: '* ',
				expectedData: [
					{ type: 'list', attributes: { style: 'bullet' } },
					{ type: 'listItem' },
					{ type: 'paragraph' },
					{ type: '/paragraph' },
					{ type: '/listItem' },
					{ type: '/list' }
				],
				expectedRangeOrSelection: new ve.Range( 3 ),
				msg: 'Bullet list'
			},
			{
				content: '1. ',
				expectedData: [
					{ type: 'list', attributes: { style: 'number' } },
					{ type: 'listItem' },
					{ type: 'paragraph' },
					{ type: '/paragraph' },
					{ type: '/listItem' },
					{ type: '/list' }
				],
				expectedRangeOrSelection: new ve.Range( 3 ),
				msg: 'Numbered list'
			},
			{
				content: '----',
				expectedData: [
					{ type: 'horizontalRule' },
					{ type: '/horizontalRule' },
					{ type: 'paragraph' },
					{ type: '/paragraph' }
				],
				expectedRangeOrSelection: new ve.Range( 3 ),
				msg: 'Horizontal rule'
			}
		];

	for ( i = 0; i < tests.length; i++ ) {
		view = ve.test.utils.createSurfaceViewFromDocument( ve.dm.example.createExampleDocumentFromData( emptyDocData ) );
		model = view.getModel();
		model.getLinearFragment( new ve.Range( 1 ) ).insertContent( tests[ i ].content ).collapseToEnd();
		view.checkSequences();
		assert.deepEqual(
			model.getDocument().getData( model.getDocument().getDocumentRange() ),
			tests[ i ].expectedData,
			tests[ i ].msg + ': data'
		);
		expectedSelection = ve.test.utils.selectionFromRangeOrSelection( model.getDocument(), tests[ i ].expectedRangeOrSelection );
		assert.equalHash(
			model.getSelection(),
			expectedSelection,
			tests[ i ].msg + ': selection'
		);
	}
} );
