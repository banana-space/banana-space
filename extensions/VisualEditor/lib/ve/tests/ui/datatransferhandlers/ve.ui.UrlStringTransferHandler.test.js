/*!
 * VisualEditor UserInterface UrlStringTransferHandler tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.UrlStringTransferHandler' );

/* Tests */

ve.test.utils.runUrlStringHandlerTest = function ( assert, string, htmlString, mimeType, expectedDataFunc, msg ) {
	var handler,
		done = assert.async(),
		item = ve.ui.DataTransferItem.static.newFromString( string, mimeType, htmlString ),
		doc = ve.dm.example.createExampleDocument(),
		mockSurface = {
			getModel: function () {
				return {
					getDocument: function () {
						return doc;
					}
				};
			}
		},
		linkAction = ve.ui.actionFactory.create( 'link', mockSurface ),
		makeLinkAnnotation = function ( href ) {
			return linkAction.getLinkAnnotation( href ).element;
		};

	// Invoke the handler
	handler = ve.ui.dataTransferHandlerFactory.create( 'urlString', mockSurface, item );

	handler.getInsertableData().done( function ( actualData ) {
		ve.dm.example.postprocessAnnotations( actualData, doc.getStore() );
		assert.equalLinearData( actualData, expectedDataFunc( makeLinkAnnotation ), msg + ': data match' );
		done();
	} );
};

QUnit.test( 'paste', function ( assert ) {
	var i,
		cases = [
			{
				msg: 'Simple external link',
				pasteString: 'http://example.com',
				pasteType: 'text/plain',
				expectedData: function ( makeAnnotation ) {
					var a = makeAnnotation( 'http://example.com' );
					return [
						[ 'h', [ a ] ],
						[ 't', [ a ] ],
						[ 't', [ a ] ],
						[ 'p', [ a ] ],
						[ ':', [ a ] ],
						[ '/', [ a ] ],
						[ '/', [ a ] ],
						[ 'e', [ a ] ],
						[ 'x', [ a ] ],
						[ 'a', [ a ] ],
						[ 'm', [ a ] ],
						[ 'p', [ a ] ],
						[ 'l', [ a ] ],
						[ 'e', [ a ] ],
						[ '.', [ a ] ],
						[ 'c', [ a ] ],
						[ 'o', [ a ] ],
						[ 'm', [ a ] ]
					];
				}
			},
			{
				msg: 'DnD standard URI list without HTML',
				pasteString: '#comment\nhttp://example.com\n',
				pasteType: 'text/uri-list',
				expectedData: function ( makeAnnotation ) {
					var a = makeAnnotation( 'http://example.com' );
					return [
						[ 'h', [ a ] ],
						[ 't', [ a ] ],
						[ 't', [ a ] ],
						[ 'p', [ a ] ],
						[ ':', [ a ] ],
						[ '/', [ a ] ],
						[ '/', [ a ] ],
						[ 'e', [ a ] ],
						[ 'x', [ a ] ],
						[ 'a', [ a ] ],
						[ 'm', [ a ] ],
						[ 'p', [ a ] ],
						[ 'l', [ a ] ],
						[ 'e', [ a ] ],
						[ '.', [ a ] ],
						[ 'c', [ a ] ],
						[ 'o', [ a ] ],
						[ 'm', [ a ] ]
					];
				}
			},
			{
				msg: 'DnD standard URI list with HTML',
				pasteString: '#comment\nhttp://example.com\n',
				pasteType: 'text/uri-list',
				pasteHtml: '<a href="http://example.com/foo">Foo</a>',
				expectedData: function ( makeAnnotation ) {
					var a = makeAnnotation( 'http://example.com/foo' );
					return [
						[ 'F', [ a ] ],
						[ 'o', [ a ] ],
						[ 'o', [ a ] ]
					];
				}
			},
			{
				msg: 'Mozilla URI list',
				pasteString: 'http://example.com\n[[Foo]]\nhttp://example.org\nBar',
				pasteType: 'text/x-moz-url',
				expectedData: function ( makeAnnotation ) {
					var a1 = makeAnnotation( 'http://example.com' ),
						a2 = makeAnnotation( 'http://example.org' );
					return [
						[ '[', [ a1 ] ],
						[ '[', [ a1 ] ],
						[ 'F', [ a1 ] ],
						[ 'o', [ a1 ] ],
						[ 'o', [ a1 ] ],
						[ ']', [ a1 ] ],
						[ ']', [ a1 ] ],
						' ',
						[ 'B', [ a2 ] ],
						[ 'a', [ a2 ] ],
						[ 'r', [ a2 ] ]
					];
				}
			}
		];

	for ( i = 0; i < cases.length; i++ ) {
		ve.test.utils.runUrlStringHandlerTest( assert, cases[ i ].pasteString, cases[ i ].pasteHtml, cases[ i ].pasteType, cases[ i ].expectedData, cases[ i ].msg );
	}
} );
