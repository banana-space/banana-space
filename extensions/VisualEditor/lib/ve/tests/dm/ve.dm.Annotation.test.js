/*!
 * VisualEditor DataModel Annotation tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.dm.Annotation' );

QUnit.test( 'getHashObject', function ( assert ) {
	var i, l,
		cases = [
			{
				msg: 'Bold',
				annotation: new ve.dm.BoldAnnotation( {
					type: 'textStyle/bold',
					attributes: { nodeName: 'b' }
				} ),
				expected: {
					type: 'textStyle/bold',
					attributes: { nodeName: 'b' }
				}
			},
			{
				msg: 'Italic with original DOM elements',
				annotation: new ve.dm.ItalicAnnotation( {
					type: 'textStyle/italic',
					attributes: { nodeName: 'i' },
					originalDomElementsHash: 1
				} ),
				expected: {
					type: 'textStyle/italic',
					attributes: { nodeName: 'i' },
					originalDomElementsHash: 1
				}
			}
		];

	for ( i = 0, l = cases.length; i < l; i++ ) {
		assert.deepEqual( cases[ i ].annotation.getHashObject(), cases[ i ].expected, cases[ i ].msg );
	}
} );
