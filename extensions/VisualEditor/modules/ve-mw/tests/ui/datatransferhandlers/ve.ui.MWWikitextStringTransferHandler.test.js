/*!
 * VisualEditor UserInterface MWWikitextStringTransferHandler tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.MWWikitextStringTransferHandler', QUnit.newMwEnvironment( {
	setup: function () {
		// Mock XHR for mw.Api()
		this.server = this.sandbox.useFakeServer();
		// Random number, chosen by a fair dice roll.
		// Used to make #mwt ID deterministic
		this.randomStub = sinon.stub( Math, 'random' ).returns( 0.04 );
		ve.test.utils.mwEnvironment.setup.call( this );
	},
	teardown: function () {
		this.randomStub.restore();
		ve.test.utils.mwEnvironment.teardown.call( this );
	}
} ) );

/* Tests */

ve.test.utils.runWikitextStringHandlerTest = function ( assert, server, string, mimeType, expectedResponse, expectedData, annotations, assertDom, msg ) {
	var handler, i, j, name,
		done = assert.async(),
		item = ve.ui.DataTransferItem.static.newFromString( string, mimeType ),
		doc = ve.dm.Document.static.newBlankDocument(),
		mockSurface = {
			getModel: function () {
				return {
					getDocument: function () {
						return doc;
					}
				};
			},
			createProgress: function () {
				return ve.createDeferred().promise();
			}
		};

	// Preprocess the expectedData array
	for ( i = 0; i < expectedData.length; i++ ) {
		if ( Array.isArray( expectedData[ i ] ) ) {
			for ( j = 0; j < expectedData[ i ][ 1 ].length; j++ ) {
				if ( typeof expectedData[ i ][ 1 ][ j ] === 'number' ) {
					expectedData[ i ][ 1 ][ j ] = annotations[ expectedData[ i ][ 1 ][ j ] ];
				}
			}
		}
	}

	// Check we match the wikitext string handler
	name = ve.ui.dataTransferHandlerFactory.getHandlerNameForItem( item );
	assert.strictEqual( name, 'wikitextString', msg + ': triggers match function' );

	// Invoke the handler
	handler = ve.ui.dataTransferHandlerFactory.create( 'wikitextString', mockSurface, item );

	handler.getInsertableData().done( function ( docOrData ) {
		var actualData, store;
		if ( docOrData instanceof ve.dm.Document ) {
			actualData = docOrData.getData();
			store = docOrData.getStore();
		} else {
			actualData = docOrData;
			store = new ve.dm.HashValueStore();
		}
		ve.dm.example.postprocessAnnotations( actualData, store );
		if ( assertDom ) {
			assert.equalLinearDataWithDom( store, actualData, expectedData, msg + ': data match (with DOM)' );
		} else {
			assert.equalLinearData( actualData, expectedData, msg + ': data match' );
		}
		done();
	} );

	if ( server && expectedResponse ) {
		server.respond( [ 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
			visualeditor: {
				result: 'success',
				content: '<body lang="en" class="mw-content-ltr sitedir-ltr ltr mw-body mw-body-content mediawiki" dir="ltr">' +
					expectedResponse +
					'</body>'
			}
		} ) ] );
	}
};

QUnit.test( 'convert', function ( assert ) {
	var i,
		cases = [
			{
				msg: 'Simple link',
				// Put link in the middle of text to verify that the
				// start-of-line and end-or-line anchors on the heading
				// identification pattern don't affect link identification
				pasteString: 'some [[Foo]] text',
				pasteType: 'text/plain',
				parsoidResponse: '<p>some <a rel="mw:WikiLink" href="./Foo" title="Foo">Foo</a> text</p>',
				annotations: [ {
					type: 'link/mwInternal',
					attributes: {
						lookupTitle: 'Foo',
						normalizedTitle: 'Foo',
						origTitle: 'Foo',
						title: 'Foo'
					}
				} ],
				expectedData: [
					{ type: 'paragraph' },
					's',
					'o',
					'm',
					'e',
					' ',
					[ 'F', [ 0 ] ],
					[ 'o', [ 0 ] ],
					[ 'o', [ 0 ] ],
					' ',
					't',
					'e',
					'x',
					't',
					{ type: '/paragraph' },
					{ type: 'internalList' },
					{ type: '/internalList' }
				]
			},
			{
				msg: 'Simple link with no p-wrapping',
				pasteString: '*[[Foo]]',
				pasteType: 'text/plain',
				parsoidResponse: '<ul><li><a rel="mw:WikiLink" href="./Foo" title="Foo">Foo</a></li></ul>',
				annotations: [ {
					type: 'link/mwInternal',
					attributes: {
						lookupTitle: 'Foo',
						normalizedTitle: 'Foo',
						origTitle: 'Foo',
						title: 'Foo'
					}
				} ],
				expectedData: [
					{
						type: 'list',
						attributes: { style: 'bullet' }
					},
					{ type: 'listItem' },
					{
						type: 'paragraph',
						internal: { generated: 'wrapper' }
					},
					[ 'F', [ 0 ] ],
					[ 'o', [ 0 ] ],
					[ 'o', [ 0 ] ],
					{ type: '/paragraph' },
					{ type: '/listItem' },
					{ type: '/list' },
					{ type: 'internalList' },
					{ type: '/internalList' }
				]
			},
			{
				msg: 'Simple template',
				pasteString: '{{Template}}',
				pasteType: 'text/plain',
				parsoidResponse: '<div typeof="mw:Transclusion" about="#mwt1">Template</div>',
				assertDom: true,
				expectedData: [
					{
						type: 'mwTransclusionBlock',
						attributes: {
							mw: {}
						},
						originalDomElements: $( '<div typeof="mw:Transclusion" about="#mwt40000000">Template</div>' ).toArray()
					},
					{ type: '/mwTransclusionBlock' },
					{ type: 'internalList' },
					{ type: '/internalList' }
				]
			},
			{
				msg: 'Headings, only RESTBase IDs stripped',
				pasteString: '==heading==',
				pasteType: 'text/plain',
				parsoidResponse: '<h2 id="mwAB">foo</h2><h2 id="mw-meaningful-id">bar</h2>',
				annotations: [],
				assertDom: true,
				expectedData: [
					{ type: 'mwHeading', attributes: { level: 2 }, internal: { changesSinceLoad: 0, metaItems: [] }, originalDomElements: $( '<h2>foo</h2>' ).toArray() },
					'f', 'o', 'o',
					{ type: '/mwHeading' },
					{ type: 'mwHeading', attributes: { level: 2 }, internal: { changesSinceLoad: 0, metaItems: [] }, originalDomElements: $( '<h2 id="mw-meaningful-id">bar</h2>' ).toArray() },
					'b', 'a', 'r',
					{ type: '/mwHeading' },
					{ type: 'internalList' },
					{ type: '/internalList' }
				]
			},
			{
				msg: 'Headings, parsoid fallback ids don\'t interfere with whitespace stripping',
				pasteString: '== Tudnivalók ==',
				pasteType: 'text/plain',
				parsoidResponse: '<h2 id="Tudnivalók"><span id="Tudnival.C3.B3k" typeof="mw:FallbackId"></span> Tudnivalók </h2>',
				annotations: [],
				assertDom: true,
				expectedData: [
					{ type: 'mwHeading', attributes: { level: 2 }, internal: { changesSinceLoad: 0, metaItems: [] }, originalDomElements: $( '<h2 id="Tudnivalók"> Tudnivalók </h2>' ).toArray() },
					'T', 'u', 'd', 'n', 'i', 'v', 'a', 'l', 'ó', 'k',
					{ type: '/mwHeading' },
					{ type: 'internalList' },
					{ type: '/internalList' }
				]
			},
			{
				msg: 'Magic link (RFC)',
				pasteString: 'RFC 1234',
				pasteType: 'text/plain',
				parsoidResponse: false,
				annotations: [],
				expectedData: [
					{
						type: 'link/mwMagic',
						attributes: {
							content: 'RFC 1234'
						}
					},
					{
						type: '/link/mwMagic'
					}
				]
			},
			{
				msg: 'Magic link (PMID)',
				pasteString: 'PMID 1234',
				pasteType: 'text/plain',
				parsoidResponse: false,
				annotations: [],
				expectedData: [
					{
						type: 'link/mwMagic',
						attributes: {
							content: 'PMID 1234'
						}
					},
					{
						type: '/link/mwMagic'
					}
				]
			},
			{
				msg: 'Magic link (ISBN)',
				pasteString: 'ISBN 123456789X',
				pasteType: 'text/plain',
				parsoidResponse: false,
				annotations: [],
				expectedData: [
					{
						type: 'link/mwMagic',
						attributes: {
							content: 'ISBN 123456789X'
						}
					},
					{
						type: '/link/mwMagic'
					}
				]
			}
		];

	for ( i = 0; i < cases.length; i++ ) {
		ve.test.utils.runWikitextStringHandlerTest(
			assert, this.server, cases[ i ].pasteString, cases[ i ].pasteType, cases[ i ].parsoidResponse,
			cases[ i ].expectedData, cases[ i ].annotations, cases[ i ].assertDom, cases[ i ].msg
		);
	}
} );
