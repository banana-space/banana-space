/*!
 * VisualEditor UserInterface FragmentInspector tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.FragmentInspector (MW)', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Mock XHR for mw.Api()
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		ve.test.utils.mwEnvironment.setup.call( this );
	},
	afterEach: ve.test.utils.mwEnvironment.teardown
} ) );

/* Tests */

QUnit.test( 'Wikitext link inspector', function ( assert ) {
	var done = assert.async(),
		surface = ve.init.target.createSurface(
			ve.dm.converter.getModelFromDom(
				ve.createDocumentFromHtml(
					'<p>Foo [[bar]] [[Quux|baz]]  x</p>' +
					'<p>wh]]ee</p>'
				)
			),
			{ mode: 'source' }
		),
		cases = [
			{
				msg: 'Collapsed selection expands to word',
				name: 'wikitextLink',
				range: new ve.Range( 2 ),
				expectedRange: new ve.Range( 1, 8 ),
				expectedData: function ( data ) {
					data.splice(
						1, 3,
						'[', '[', 'F', 'o', 'o', ']', ']'
					);
				}
			},
			{
				msg: 'Collapsed selection in word (noExpand)',
				name: 'wikitextLink',
				range: new ve.Range( 2 ),
				setupData: { noExpand: true },
				expectedRange: new ve.Range( 2 ),
				expectedData: function () {}
			},
			{
				msg: 'Cancel restores original data & selection',
				name: 'wikitextLink',
				range: new ve.Range( 2 ),
				expectedRange: new ve.Range( 2 ),
				expectedData: function () {},
				actionData: {}
			},
			{
				msg: 'Collapsed selection inside existing link',
				name: 'wikitextLink',
				range: new ve.Range( 5 ),
				expectedRange: new ve.Range( 5, 12 ),
				expectedData: function () {}
			},
			{
				msg: 'Selection inside existing link',
				name: 'wikitextLink',
				range: new ve.Range( 19, 20 ),
				expectedRange: new ve.Range( 13, 25 ),
				expectedData: function () {}
			},
			{
				msg: 'Selection spanning existing link',
				name: 'wikitextLink',
				range: new ve.Range( 3, 8 ),
				expectedRange: new ve.Range( 3, 8 ),
				expectedData: function () {}
			},
			{
				msg: 'Selection with whitespace is trimmed',
				name: 'wikitextLink',
				range: new ve.Range( 1, 5 ),
				expectedRange: new ve.Range( 1, 8 )
			},
			{
				msg: 'Link insertion',
				name: 'wikitextLink',
				range: new ve.Range( 26 ),
				input: function () {
					this.annotationInput.getTextInputWidget().setValue( 'quux' );
				},
				expectedRange: new ve.Range( 34 ),
				expectedData: function ( data ) {
					data.splice.apply( data, [ 26, 0 ].concat( '[[quux]]'.split( '' ) ) );
				}
			},
			{
				msg: 'Link insertion to file page',
				name: 'wikitextLink',
				range: new ve.Range( 26 ),
				input: function () {
					this.annotationInput.getTextInputWidget().setValue( 'File:foo.jpg' );
				},
				expectedRange: new ve.Range( 43 ),
				expectedData: function ( data ) {
					data.splice.apply( data, [ 26, 0 ].concat( '[[:File:foo.jpg]]'.split( '' ) ) );
				}
			},
			{
				msg: 'Link insertion with no input is no-op',
				name: 'wikitextLink',
				range: new ve.Range( 26 ),
				expectedRange: new ve.Range( 26 ),
				expectedData: function () {}
			},
			{
				msg: 'Link modified',
				name: 'wikitextLink',
				range: new ve.Range( 5, 12 ),
				input: function () {
					this.annotationInput.getTextInputWidget().setValue( 'quux' );
				},
				expectedRange: new ve.Range( 5, 17 ),
				expectedData: function ( data ) {
					data.splice.apply( data, [ 7, 3 ].concat( 'Quux|bar'.split( '' ) ) );
				}
			},
			{
				msg: 'Link modified with initial selection including whitespace',
				name: 'wikitextLink',
				range: new ve.Range( 4, 13 ),
				input: function () {
					this.annotationInput.getTextInputWidget().setValue( 'quux' );
				},
				expectedRange: new ve.Range( 5, 17 ),
				expectedData: function ( data ) {
					data.splice.apply( data, [ 7, 3 ].concat( 'Quux|bar'.split( '' ) ) );
				}
			},
			{
				msg: 'Piped link modified',
				name: 'wikitextLink',
				range: new ve.Range( 16 ),
				input: function () {
					this.annotationInput.getTextInputWidget().setValue( 'whee' );
				},
				expectedRange: new ve.Range( 13, 25 ),
				expectedData: function ( data ) {
					data.splice.apply( data, [ 15, 4 ].concat( 'Whee'.split( '' ) ) );
				}
			},
			{
				msg: 'Link modified',
				name: 'wikitextLink',
				range: new ve.Range( 30, 36 ),
				input: function () {
					this.annotationInput.getTextInputWidget().setValue( 'foo' );
				},
				expectedRange: new ve.Range( 30, 61 ),
				expectedData: function ( data ) {
					data.splice.apply( data, [ 30, 6 ].concat( '[[Foo|wh<nowiki>]]</nowiki>ee]]'.split( '' ) ) );
				}
			}
			// Skips clear annotation test, not implement yet
		];

	ve.test.utils.runFragmentInspectorTests( surface, assert, cases ).finally( function () {
		done();
	} );
} );
