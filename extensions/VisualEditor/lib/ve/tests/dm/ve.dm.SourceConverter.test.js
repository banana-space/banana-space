/*!
 * VisualEditor DataModel SourceConverter tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.dm.SourceConverter' );

/* Tests */

QUnit.test( 'conversion', function ( assert ) {
	var i, doc,
		cases = [
			{
				msg: 'Simple text with language options',
				text: 'foo',
				textFromDataRange: 'foo\n',
				data: [
					{ type: 'paragraph' },
					'f', 'o', 'o',
					{ type: '/paragraph' }
				],
				options: {
					lang: 'he',
					dir: 'rtl'
				}
			},
			{
				msg: 'Multiple lines and whitespace',
				text: 'foo \n\n\tbar\n',
				textFromDataRange: 'foo \n\n\tbar\n\n',
				data: [
					{ type: 'paragraph' },
					'f', 'o', 'o', ' ',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					'\t', 'b', 'a', 'r',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					{ type: '/paragraph' }
				]
			},
			{
				msg: 'Different newlines are all converted to paragraphs (CR/LF/CRLF)',
				text: 'asd\nasd\rasd\r\nasd',
				textFromDataRange: 'asd\nasd\nasd\nasd\n',
				data: [
					{ type: 'paragraph' },
					'a', 's', 'd',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					'a', 's', 'd',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					'a', 's', 'd',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					'a', 's', 'd',
					{ type: '/paragraph' }
				],
				textRoundtrip: 'asd\nasd\nasd\nasd'
			}
		];

	for ( i = 0; i < cases.length; i++ ) {
		doc = ve.dm.sourceConverter.getModelFromSourceText( cases[ i ].text, cases[ i ].options );
		assert.deepEqual(
			doc.data.data,
			[].concat( cases[ i ].data, [ { type: 'internalList' }, { type: '/internalList' } ] ),
			cases[ i ].msg + ': getModelFromSourceText (data)'
		);
		if ( cases[ i ].options ) {
			assert.deepEqual(
				{ lang: doc.getLang(), dir: doc.getDir() },
				cases[ i ].options,
				cases[ i ].msg + ': getModelFromSourceText (language options)'
			);
		}
		assert.deepEqual(
			ve.dm.sourceConverter.getSourceTextFromModel( doc ),
			cases[ i ].textRoundtrip || cases[ i ].text,
			cases[ i ].msg + ': getSourceTextFromModel'
		);
		assert.deepEqual(
			ve.dm.sourceConverter.getDataFromSourceText( cases[ i ].text ),
			cases[ i ].data,
			cases[ i ].msg + ': getDataFromSourceText'
		);
		assert.deepEqual(
			ve.dm.sourceConverter.getDataFromSourceText( cases[ i ].text, true ),
			cases[ i ].data.slice( 1, -1 ),
			cases[ i ].msg + ': getDataFromSourceText (inline)'
		);
		assert.strictEqual(
			ve.dm.sourceConverter.getSourceTextFromDataRange( cases[ i ].data ),
			cases[ i ].textFromDataRange || cases[ i ].textRoundtrip || cases[ i ].text,
			cases[ i ].msg + ': getSourceTextFromDataRange'
		);
		// getSourceTextFromDataRange with a range argument is currently tested by ElementLinearData#getSourceText tests
	}
} );
