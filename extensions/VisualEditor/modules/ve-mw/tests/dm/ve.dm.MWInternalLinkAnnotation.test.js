/*!
 * VisualEditor DataModel MWInternalLinkAnnotation tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.dm.MWInternalLinkAnnotation' );

QUnit.test( 'toDataElement', function ( assert ) {
	var i, l,
		doc = ve.dm.example.createExampleDocument(),
		externalLink = function ( href ) {
			var link = document.createElement( 'a' );
			link.setAttribute( 'href', href );
			return link;
		},
		internalLink = function ( pageTitle, params ) {
			var link = document.createElement( 'a' );
			link.setAttribute( 'href', location.origin + mw.Title.newFromText( pageTitle ).getUrl( params ) );
			return link;
		},
		cases = [
			{
				msg: 'Not an internal link',
				element: externalLink( 'http://example.com/' ),
				expected: {
					type: 'link/mwExternal',
					attributes: {
						href: 'http://example.com/'
					}
				}
			},
			{
				msg: 'Simple',
				element: internalLink( 'Foo' ),
				expected: {
					type: 'link/mwInternal',
					attributes: {
						lookupTitle: 'Foo',
						normalizedTitle: 'Foo',
						origTitle: 'Foo',
						title: 'Foo'
					}
				}
			},
			{
				msg: 'History link',
				element: internalLink( 'Foo', { action: 'history' } ),
				expected: {
					type: 'link/mwExternal',
					attributes: {
						href: location.origin + mw.Title.newFromText( 'Foo' ).getUrl( { action: 'history' } )
					}
				}
			},
			{
				msg: 'Diff link',
				element: internalLink( 'Foo', { diff: '3', oldid: '2' } ),
				expected: {
					type: 'link/mwExternal',
					attributes: {
						href: location.origin + mw.Title.newFromText( 'Foo' ).getUrl( { diff: '3', oldid: '2' } )
					}
				}
			},
			{
				msg: 'Red link',
				element: internalLink( 'Foo', { action: 'edit', redlink: '1' } ),
				expected: {
					type: 'link/mwInternal',
					attributes: {
						lookupTitle: 'Foo',
						normalizedTitle: 'Foo',
						origTitle: 'Foo',
						title: 'Foo'
					}
				}
			},
			{
				// Because percent-encoded URLs aren't valid titles, but what they decode to might be
				msg: 'Percent encoded characters',
				element: internalLink( 'Foo?' ),
				expected: {
					type: 'link/mwInternal',
					attributes: {
						lookupTitle: 'Foo?',
						normalizedTitle: 'Foo?',
						origTitle: 'Foo%3F',
						title: 'Foo?'
					}
				}
			},
			{
				// The fragment should make it into some parts of this, and not others
				msg: 'Fragments',
				element: internalLink( 'Foo#bar' ),
				expected: {
					type: 'link/mwInternal',
					attributes: {
						lookupTitle: 'Foo',
						normalizedTitle: 'Foo#bar',
						origTitle: 'Foo#bar',
						title: 'Foo#bar'
					}
				}
			},
			{
				// Question marks in the fragment shouldn't confuse this
				msg: 'Question marks in fragments',
				element: internalLink( 'Foo#bar?' ),
				expected: {
					type: 'link/mwInternal',
					attributes: {
						lookupTitle: 'Foo',
						normalizedTitle: 'Foo#bar.3F',
						origTitle: 'Foo#bar.3F',
						title: 'Foo#bar.3F'
					}
				}
			}
		],
		converter = new ve.dm.Converter( ve.dm.modelRegistry, ve.dm.nodeFactory, ve.dm.annotationFactory, ve.dm.metaItemFactory );

	// toDataElement is called during a converter run, so we need to fake up a bit of state to test it.
	// This would normally be done by ve.dm.converter.getModelFromDom.
	converter.doc = doc.getHtmlDocument();
	converter.targetDoc = doc.getHtmlDocument();
	converter.store = doc.getStore();
	converter.internalList = doc.getInternalList();
	converter.contextStack = [];

	for ( i = 0, l = cases.length; i < l; i++ ) {
		assert.deepEqual( ve.dm.MWInternalLinkAnnotation.static.toDataElement( [ cases[ i ].element ], converter ), cases[ i ].expected, cases[ i ].msg );
	}
} );

QUnit.test( 'getFragment', function ( assert ) {
	var i, l,
		cases = [
			{
				msg: 'No fragment returns null',
				original: 'Foo',
				expected: null
			},
			{
				msg: 'Invalid title returns null',
				original: 'A%20B',
				expected: null
			},
			{
				msg: 'Blank fragment returns empty string',
				original: 'Foo#',
				expected: ''
			},
			{
				msg: 'Extant fragment returns same string',
				original: 'Foo#bar',
				expected: 'bar'
			},
			{
				msg: 'Hash-bang works returns full string',
				original: 'Foo#!bar',
				expected: '!bar'
			},
			{
				msg: 'Double-hash returns everything after the first hash',
				original: 'Foo##bar',
				expected: '#bar'
			},
			{
				msg: 'Multi-fragment returns everything after the first hash',
				original: 'Foo#bar#baz#bat',
				expected: 'bar#baz#bat'
			}
		];

	for ( i = 0, l = cases.length; i < l; i++ ) {
		assert.strictEqual( ve.dm.MWInternalLinkAnnotation.static.getFragment( cases[ i ].original ), cases[ i ].expected, cases[ i ].msg );
	}
} );
