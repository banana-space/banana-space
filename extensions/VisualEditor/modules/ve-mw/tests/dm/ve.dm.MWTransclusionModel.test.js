/*!
 * VisualEditor DataModel MWTransclusionModel tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

( function () {
	QUnit.module( 've.dm.MWTransclusionModel', QUnit.newMwEnvironment( {
		setup: function () {
			// Mock XHR for mw.Api()
			this.server = this.sandbox.useFakeServer();
			this.server.respondImmediately = true;

			ve.test.utils.mwEnvironment.setup.call( this );
		},
		teardown: function () {
			ve.test.utils.mwEnvironment.teardown.call( this );
		}
	} ) );

	function runAddPartTest( assert, name, response, server, callback ) {
		var doc = ve.dm.Document.static.newBlankDocument(),
			transclusion = new ve.dm.MWTransclusionModel( doc ),
			part = ve.dm.MWTemplateModel.newFromName( transclusion, name ),
			done = assert.async();

		server.respondWith( [ 200, { 'Content-Type': 'application/json' }, JSON.stringify( response ) ] );

		transclusion.addPart( part )
			.then( function () {
				callback( transclusion );
			} )
			.always( function () {
				done();
			} );
	}

	QUnit.test( 'fetch template part data', function ( assert ) {
		var response = {
			batchcomplete: '',
			pages: {
				1331311: {
					title: 'Template:Test',
					description: { en: 'MWTransclusionModel template test' },
					params: {
						test: {
							label: { en: 'Test param' },
							type: 'string',
							description: { en: 'This is a test param' },
							required: false,
							suggested: false,
							example: null,
							deprecated: false,
							aliases: [],
							autovalue: null,
							default: null
						}
					},
					paramOrder: [ 'test' ],
					format: 'inline',
					sets: [],
					maps: {}
				}
			}
		};

		runAddPartTest( assert, 'Test', response, this.server, function ( transclusion ) {
			var parts = transclusion.getParts(),
				spec = parts[ 0 ].getSpec();

			assert.strictEqual( parts.length, 1 );
			assert.strictEqual( spec.getDescription( 'en' ), 'MWTransclusionModel template test' );
			assert.strictEqual( spec.getParameterLabel( 'test', 'en' ), 'Test param' );
		} );
	} );

	// T243868
	QUnit.test( 'fetch part data for parameterized template with no TemplateData', function ( assert ) {
		var response = {
			batchcomplete: '',
			pages: {
				1331311: {
					title: 'Template:NoData',
					notemplatedata: true,
					params: {
						foo: [],
						bar: []
					}
				}
			}
		};

		runAddPartTest( assert, 'NoData', response, this.server, function ( transclusion ) {
			var parts = transclusion.getParts(),
				spec = parts[ 0 ].getSpec();

			assert.strictEqual( parts.length, 1 );
			assert.deepEqual( spec.getParameterNames(), [ 'foo', 'bar' ] );
		} );
	} );

	QUnit.test( 'fetch part data for template with no TemplateData and no params', function ( assert ) {
		var response = {
			batchcomplete: '',
			pages: {
				1331311: {
					title: 'Template:NoParams',
					notemplatedata: true,
					params: []
				}
			}
		};

		runAddPartTest( assert, 'NoParams', response, this.server, function ( transclusion ) {
			var parts = transclusion.getParts(),
				spec = parts[ 0 ].getSpec();

			assert.strictEqual( parts.length, 1 );
			assert.deepEqual( spec.getParameterNames(), [] );
		} );
	} );
}() );
