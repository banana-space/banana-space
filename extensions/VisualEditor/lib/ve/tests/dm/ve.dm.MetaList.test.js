/*!
 * VisualEditor DataModel MetaList tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.dm.MetaList' );

/* Tests */

/**
 * Test whether the MetaList items cache is correct
 *
 * @param {Object} assert QUnit assert object
 * @param {ve.dm.MetaList} metaList The MetaList
 * @param {string} msg The message
 */
ve.test.utils.validateMetaListCache = function ( assert, metaList, msg ) {
	var newList, oldList, i, len, match;

	oldList = metaList.items;
	newList = [];
	// Populate a current list of items
	metaList.document.documentNode.traverse( function ( node ) {
		if ( node instanceof ve.dm.MetaItem ) {
			newList.push( node );
		}
	} );
	match = true;
	for ( i = 0, len = newList.length; i < len; i++ ) {
		if ( newList[ i ] !== oldList[ i ] ) {
			match = false;
			break;
		}
	}
	assert.ok( match, msg );
};

QUnit.test( 'constructor', function ( assert ) {
	var doc = ve.dm.example.createExampleDocument( 'withMeta' ),
		surface = new ve.dm.Surface( doc ),
		list = new ve.dm.MetaList( surface );

	ve.test.utils.validateMetaListCache( assert, list, 'Constructor' );
} );

QUnit.test( 'onTransact', function ( assert ) {
	var i, j, surface, txBuilder, tx, list,
		doc = ve.dm.example.createExampleDocument( 'withMeta' ),
		heading = { type: 'heading', attributes: { level: 2 } },
		cases = [
			{
				// delta: 0
				calls: [
					[ 'pushRetain', 5 ],
					[ 'pushReplacement', doc, 5, 0, [ 'Q', 'u', 'u', 'x' ] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', doc, 8, 7, [ '!' ] ]
				],
				msg: 'Transaction inserting, replacing and removing text'
			},
			{
				// delta: 0
				calls: [
					[ 'pushReplacement', doc, 0, 5, [ heading ] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', doc, 8, 3, [ { type: '/heading' } ] ]
				],
				msg: 'Transaction converting paragraph to heading'
			},
			{
				// delta: -9
				calls: [
					[ 'pushRetain', 5 ],
					[ 'pushReplacement', doc, 5, 3, [] ],
					[ 'pushRetain', 1 ]
				],
				msg: 'Transaction blanking paragraph'
			}
		];

	for ( i = 0; i < cases.length; i++ ) {
		txBuilder = new ve.dm.TransactionBuilder();
		for ( j = 0; j < cases[ i ].calls.length; j++ ) {
			txBuilder[ cases[ i ].calls[ j ][ 0 ] ].apply( txBuilder, cases[ i ].calls[ j ].slice( 1 ) );
		}
		tx = txBuilder.getTransaction();
		doc = ve.dm.example.createExampleDocument( 'withMeta' );
		surface = new ve.dm.Surface( doc );
		list = new ve.dm.MetaList( surface );
		// Test both the transaction-via-surface and transaction-via-document flows
		surface.change( tx );
		ve.test.utils.validateMetaListCache( assert, list, cases[ i ].msg );
		surface.change( tx.reversed() );
		ve.test.utils.validateMetaListCache( assert, list, cases[ i ].msg + ' (rollback)' );
	}
} );
