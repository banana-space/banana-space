/*!
 * VisualEditor DataModel Converter tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.dm.Converter' );

/* Tests */

QUnit.test( 'getModelFromDom', function ( assert ) {
	var msg, cases = ve.dm.example.domToDataCases;

	for ( msg in cases ) {
		ve.test.utils.runGetModelFromDomTest( assert, ve.copy( cases[ msg ] ), msg );
	}
} );

QUnit.test( 'getModelFromDom with store argument', function ( assert ) {
	var model,
		store = new ve.dm.HashValueStore();
	model = ve.dm.converter.getModelFromDom(
		ve.createDocumentFromHtml( '<p>foo</p>' ),
		{ lang: 'en', dir: 'ltr' },
		store
	);
	assert.strictEqual( model.getStore() === store, true, 'Document store is reference-equal to store argument' );
} );

QUnit.test( 'getDomFromModel', function ( assert ) {
	var msg, cases = ve.dm.example.domToDataCases;

	for ( msg in cases ) {
		ve.test.utils.runGetDomFromModelTest( assert, ve.copy( cases[ msg ] ), msg );
	}
} );

QUnit.test( 'getFullData', function ( assert ) {
	var doc, tx, cases, msg, caseItem;

	cases = {
		'Metadata in ContentBranchNode gets moved outside by any change': {
			beforeHtml: '<!-- w --><meta foo="x"><p>ab<meta foo="y">cd</p><p>ef<meta foo="z">gh</p>',
			transaction: function ( doc ) {
				return ve.dm.TransactionBuilder.static.newFromRemoval( doc, new ve.Range( 10, 11 ) );
			},
			afterHtml: '<!-- w --><meta foo="x"><p>abc</p><meta foo="y"><p>ef<meta foo="z">gh</p>'
		},
		'Removable metadata (empty annotation) in ContentBranchNode is removed by any change': {
			beforeHtml: '<p>ab<i></i>cd</p><p>efgh</p>',
			transaction: function ( doc ) {
				return ve.dm.TransactionBuilder.static.newFromRemoval( doc, new ve.Range( 4, 5 ) );
			},
			afterHtml: '<p>abc</p><p>efgh</p>'
		}
	};

	for ( msg in cases ) {
		caseItem = cases[ msg ];

		doc = ve.dm.converter.getModelFromDom( ve.createDocumentFromHtml( '<body>' + caseItem.beforeHtml ) );
		tx = caseItem.transaction( doc );

		doc.commit( tx );
		assert.strictEqual(
			ve.dm.converter.getDomFromModel( doc ).body.innerHTML,
			caseItem.afterHtml,
			msg
		);

		doc.commit( tx.reversed() );
		assert.strictEqual(
			ve.dm.converter.getDomFromModel( doc ).body.innerHTML,
			caseItem.beforeHtml,
			msg + ' (undo)'
		);
	}
} );

QUnit.test( 'roundTripMetadata', function ( assert ) {
	var doc, tx,
		beforeHtml = '<!-- w --><meta foo="x"><p>ab<meta foo="y">cd</p><p>ef<meta foo="z">gh</p>',
		afterHtml = '<!-- w --><meta foo="x"><p>abc</p><meta foo="y"><p>ef<meta foo="z">gh</p>';

	doc = ve.dm.converter.getModelFromDom( ve.createDocumentFromHtml( '<body>' + beforeHtml ) );
	tx = ve.dm.TransactionBuilder.static.newFromRemoval( doc, new ve.Range( 10, 11 ) );
	doc.commit( tx );
	assert.strictEqual(
		ve.dm.converter.getDomFromModel( doc ).body.innerHTML,
		afterHtml,
		'Metadata in ContentBranchNode gets moved outside by change to ContentBranchNode'
	);
	doc.commit( tx.reversed() );
	assert.strictEqual(
		ve.dm.converter.getDomFromModel( doc ).body.innerHTML,
		beforeHtml,
		'Undo restores metadata to inside ContentBranchNode'
	);
} );
