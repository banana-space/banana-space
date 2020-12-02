/*!
 * VisualEditor ContentEditable Document tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ce.RangeState' );

/* Tests */

QUnit.test( 'Basic tests', function ( assert ) {
	var i, rangeState, nativeRange,
		oldState = null,
		view = ve.test.utils.createSurfaceViewFromHtml( ve.dm.example.html ),
		nativeSelection = view.nativeSelection,
		doc = view.getDocument().getDocumentNode(),
		cases = [
			{
				msg: 'From null to null state',
				resetOld: true,
				range: null,
				expected: {
					branchNodeChanged: false,
					contentChanged: false,
					hash: null,
					node: null,
					selectionChanged: false,
					text: null,
					veRange: null
				}
			},
			{
				msg: 'From null to inside heading',
				resetOld: true,
				range: {
					startNode: doc.children[ 0 ].$element[ 0 ],
					startOffset: 1
				},
				expected: {
					branchNodeChanged: true,
					contentChanged: false,
					hash: '<H1>#<B>#</B><I>#</I></H1>',
					node: doc.children[ 0 ],
					selectionChanged: true,
					text: 'abc',
					veRange: new ve.Range( 2 )
				}
			},
			{
				msg: 'From heading to paragraph',
				range: {
					startNode: doc.children[ 4 ].$element[ 0 ],
					startOffset: 0,
					endNode: doc.children[ 4 ].$element[ 0 ],
					endOffset: 1
				},
				expected: {
					branchNodeChanged: true,
					contentChanged: false,
					hash: '<P>#</P>',
					node: doc.children[ 4 ],
					selectionChanged: true,
					text: 'l',
					veRange: new ve.Range( 56, 57 )
				}
			},
			{
				msg: 'Selection changing anchor node only',
				range: {
					startNode: doc.children[ 4 ].$element[ 0 ],
					startOffset: 1,
					endNode: doc.children[ 4 ].$element[ 0 ],
					endOffset: 1
				},
				expected: {
					branchNodeChanged: false,
					contentChanged: false,
					hash: '<P>#</P>',
					node: doc.children[ 4 ],
					selectionChanged: true,
					text: 'l',
					veRange: new ve.Range( 57, 57 )
				}
			},
			{
				msg: 'From paragraph back to null state',
				expected: {
					branchNodeChanged: true,
					contentChanged: false,
					hash: null,
					node: null,
					selectionChanged: true,
					text: null,
					veRange: null
				}
			},
			{
				msg: 'From null state to null state',
				expected: {
					branchNodeChanged: false,
					contentChanged: false,
					hash: null,
					node: null,
					selectionChanged: false,
					text: null,
					veRange: null
				}
			}
		];

	function getSummary( state ) {
		return {
			branchNodeChanged: state.branchNodeChanged,
			selectionChanged: state.selectionChanged,
			contentChanged: state.contentChanged,
			veRange: state.veRange && state.veRange.toJSON(),
			node: state.node,
			text: state.text,
			hash: state.hash
		};
	}

	for ( i = 0; i < cases.length; i++ ) {
		nativeSelection.removeAllRanges();
		if ( cases[ i ].range ) {
			nativeRange = document.createRange();
			nativeRange.setStart( cases[ i ].range.startNode, cases[ i ].range.startOffset );
			if ( cases[ i ].range.endNode ) {
				nativeRange.setEnd( cases[ i ].range.endNode, cases[ i ].range.endOffset );
			}
			nativeSelection.addRange( nativeRange );
		}
		if ( cases[ i ].resetOld ) {
			oldState = null;
		}
		rangeState = new ve.ce.RangeState( oldState, doc );
		assert.deepEqualWithNodeTree(
			getSummary( rangeState ),
			getSummary( cases[ i ].expected ),
			cases[ i ].msg
		);
		oldState = rangeState;
	}
	view.destroy();
} );
