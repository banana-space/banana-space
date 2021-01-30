( function () {
	QUnit.module( 'ext.flow: Handlebars helpers', {
		beforeEach: function () {
			var stub = this.sandbox.stub( mw.template, 'get' ),
				stubUser;

			stub.withArgs( 'ext.flow.templating', 'foo.handlebars' ).returns( {
				render: function ( data ) {
					return data && data.val ? '<div>Magic.</div>' : 'Stubbed.';
				}
			} );
			this.handlebarsProto = mw.flow.FlowHandlebars.prototype;
			this.handlebarsProto._qunit_helper_test = function ( a, b ) {
				return a + b;
			};

			// Stub user
			stubUser = this.sandbox.stub( mw.user, 'isAnon' );
			stubUser.onCall( 0 ).returns( true );
			stubUser.onCall( 1 ).returns( false );
			this.opts = {
				fn: function () {
					return 'ok';
				},
				inverse: function () {
					return 'nope';
				}
			};
		}
	} );

	QUnit.test( 'Handlebars.prototype.processTemplate', function ( assert ) {
		assert.strictEqual( this.handlebarsProto.processTemplate( 'foo', { val: 'Hello' } ),
			'<div>Magic.</div>', 'Getting a template works.' );
	} );

	QUnit.test( 'Handlebars.prototype.processTemplateGetFragment', function ( assert ) {
		assert.strictEqual( this.handlebarsProto.processTemplateGetFragment( 'foo', { val: 'Hello' } ).childNodes.length,
			1, 'Return a fragment with the div child node' );
	} );

	QUnit.test( 'Handlebars.prototype.getTemplate', function ( assert ) {
		assert.strictEqual( this.handlebarsProto.getTemplate( 'foo' )(), 'Stubbed.', 'Getting a template works.' );
		assert.strictEqual( this.handlebarsProto.getTemplate( 'foo' )(), 'Stubbed.', 'Getting a template from cache works.' );
	} );

	// Helpers
	QUnit.test( 'Handlebars.prototype.callHelper', function ( assert ) {
		assert.strictEqual( this.handlebarsProto.callHelper( '_qunit_helper_test', 1, 2 ),
			3, 'Check the helper was called.' );
	} );

	QUnit.test( 'Handlebars.prototype.eachPost', function ( assert ) {
		var ctx = {
			posts: {
				1: [ 300 ],
				// Purposely points to a missing revision to deal with edge case
				2: [ 500 ]
			},
			revisions: {
				300: { content: 'a' }
			}
		};

		assert.deepEqual( this.handlebarsProto.eachPost( ctx, 1, {} ), { content: 'a' }, 'Matches given id.' );
		assert.deepEqual( this.handlebarsProto.eachPost( ctx, 1, this.opts ), 'ok', 'Runs fn when given.' );
		assert.deepEqual( this.handlebarsProto.eachPost( ctx, 2, {} ), { content: null }, 'Missing revision id.' );
	} );

	QUnit.test( 'Handlebars.prototype.ifCond', function ( assert ) {
		assert.strictEqual( this.handlebarsProto.ifCond( 'foo', '===', 'bar', this.opts ), 'nope', 'not equal' );
		assert.strictEqual( this.handlebarsProto.ifCond( 'foo', '===', 'foo', this.opts ), 'ok', 'equal' );
		assert.strictEqual( this.handlebarsProto.ifCond( true, 'or', false, this.opts ), 'ok', 'true || false' );
		assert.strictEqual( this.handlebarsProto.ifCond( true, 'or', true, this.opts ), 'ok', 'true || true' );
		assert.strictEqual( this.handlebarsProto.ifCond( false, 'or', false, this.opts ), 'nope', 'false || false' );
		assert.strictEqual( this.handlebarsProto.ifCond( false, 'monkeypunch', this.opts ), '', 'Unknown operator' );
		assert.strictEqual( this.handlebarsProto.ifCond( 'foo', '!==', 'foo', this.opts ), 'nope' );
		assert.strictEqual( this.handlebarsProto.ifCond( 'foo', '!==', 'bar', this.opts ), 'ok' );
	} );

	QUnit.test( 'Handlebars.prototype.ifAnonymous', function ( assert ) {
		assert.strictEqual( this.handlebarsProto.ifAnonymous( this.opts ), 'ok', 'User should be anonymous first time.' );
		assert.strictEqual( this.handlebarsProto.ifAnonymous( this.opts ), 'nope', 'User should be logged in on second call.' );
	} );

	QUnit.test( 'Handlebars.prototype.concat', function ( assert ) {
		assert.strictEqual( this.handlebarsProto.concat( 'a', 'b', 'c', this.opts ), 'abc', 'Check concat working fine.' );
		assert.strictEqual( this.handlebarsProto.concat( this.opts ), '', 'Without arguments.' );
	} );

	QUnit.test( 'Handlebars.prototype.progressiveEnhancement', function ( assert ) {
		var opts = $.extend( { hash: { type: 'insert', target: 'abc', id: 'def' } }, this.opts ),
			$div = $( document.createElement( 'div' ) );

		// Render script tag
		assert.strictEqual(
			this.handlebarsProto.progressiveEnhancement( opts ).string,
			// eslint-disable-next-line no-useless-concat
			'<scr' + 'ipt' +
				' type="text/x-handlebars-template-progressive-enhancement"' +
				' data-type="' + opts.hash.type + '"' +
				' data-target="' + opts.hash.target + '"' +
				' id="' + opts.hash.id + '">' +
				'ok' +
			// eslint-disable-next-line no-useless-concat
			'</scr' + 'ipt>',
			'Should output exact replica of script tag.'
		);

		// Replace itself: no target (default to self), no type (default to insert)
		$div.empty().append( this.handlebarsProto.processTemplateGetFragment(
			Handlebars.compile( '{{#progressiveEnhancement}}hello{{/progressiveEnhancement}}' )
		) );
		assert.strictEqual(
			$div.html(),
			'hello',
			'progressiveEnhancement should be processed in template string.'
		);

		// Replace a target entirely: target + type=replace
		$div.empty().append( this.handlebarsProto.processTemplateGetFragment(
			Handlebars.compile( '{{#progressiveEnhancement target="~ .pgetest" type="replace"}}hello{{/progressiveEnhancement}}<div class="pgetest">foo</div>' )
		) );
		assert.strictEqual(
			$div.html(),
			'hello',
			'progressiveEnhancement should replace target node.'
		);

		// Insert before a target: target + type=insert
		$div.empty().append(
			this.handlebarsProto.processTemplateGetFragment(
				Handlebars.compile( '{{#progressiveEnhancement target="~ .pgetest" type="insert"}}hello{{/progressiveEnhancement}}<div class="pgetest">foo</div>' )
			)
		);
		assert.strictEqual(
			$div.html(),
			'hello<div class="pgetest">foo</div>',
			'progressiveEnhancement should insert before target.'
		);

		// Replace target's content: target + type=content
		$div.empty().append(
			this.handlebarsProto.processTemplateGetFragment(
				Handlebars.compile( '{{#progressiveEnhancement target="~ .pgetest" type="content"}}hello{{/progressiveEnhancement}}<div class="pgetest">foo</div>' )
			)
		);
		assert.strictEqual(
			$div.html(),
			'<div class="pgetest">hello</div>',
			'progressiveEnhancement should replace target content.'
		);
	} );

}() );
