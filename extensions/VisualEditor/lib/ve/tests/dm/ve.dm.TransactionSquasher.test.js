/*!
 * VisualEditor DataModel TransactionSquasher tests.
 *
 * @copyright 2011-2018 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.dm.TransactionSquasher' );

QUnit.test( 'squash', function ( assert ) {
	var tests, i, iLen, test, transactions, squashed, j, jLen,
		squashedBefore, squashedAfter,
		boldHash = 'hfbe3cfe099b83e1e',
		italicHash = 'he4e7c54e2204d10ba';

	function insertionTxList( before, itemSequence, after ) {
		var i, iLen,
			transactions = [];
		for ( i = 0, iLen = itemSequence.length; i < iLen; i++ ) {
			transactions.push( [ before + i, [ '', itemSequence[ i ] ], after ] );
		}
		return transactions;
	}

	function annotationTx( allData, start, stop, length, method, hash, spliceAt ) {
		var newData,
			oldData = Array.prototype.slice.call( allData, start, stop );

		if ( method === 'set' ) {
			newData = oldData.map( function ( item ) {
				var ch, hashList;
				if ( Array.isArray( item ) ) {
					ch = item[ 0 ];
					hashList = item[ 1 ];
				} else {
					ch = item;
					hashList = [];
				}
				hashList = [].concat(
					hashList.slice( 0, spliceAt ),
					hash,
					hashList.slice( spliceAt )
				);
				return [ ch, hashList ];
			} );
		} else {
			newData = oldData.map( function ( item ) {
				var ch = item[ 0 ],
					hashList = item[ 1 ];
				hashList = [].concat(
					hashList.slice( 0, spliceAt ),
					hashList.slice( spliceAt + 1 )
				);
				if ( hashList.length === 0 ) {
					return ch;
				} else {
					return [ ch, hashList ];
				}
			} );
		}
		return [ start, [ oldData, newData ], length - stop ];
	}

	function sequence( data ) {
		var hashList = Array.prototype.slice.call( arguments, 1 );
		return Array.prototype.map.call( data, function ( item ) {
			return hashList.length === 0 ? item : [ item, hashList ];
		} );
	}

	tests = [
		{
			message: 'Empty transaction list',
			transactions: [],
			error: true
		},
		{
			message: 'Replace part of replacement',
			transactions: [
				[ 4, [ 'Aa', 'Bb' ], 4 ],
				[ 4, [ 'B', 'D' ], 5 ]
			],
			squashed: [ 4, [ 'Aa', 'Db' ], 4 ]
		},
		{
			message: 'Replace interior retain',
			transactions: [
				[ 4, [ 'Aa', 'Bb' ], 4 ],
				[ 1, [ 'Xx', 'Yy' ], 7 ]
			],
			squashed: [ 1, [ 'Xx', 'Yy' ], 1, [ 'Aa', 'Bb' ], 4 ]
		},
		{
			message: 'Remove insertion',
			transactions: [
				[ 4, [ '', 'abc' ], 4 ],
				[ 4, [ 'abc', '' ], 4 ]
			],
			squashed: [ 8 ]
		},
		{
			message: 'Re-insert removal',
			transactions: [
				[ 4, [ 'abc', '' ], 4 ],
				[ 4, [ '', 'abc' ], 4 ]
			],
			squashed: [ 4, [ 'abc', 'abc' ], 4 ]
		},
		{
			message: 'Replace part of bolded replacement',
			transactions: [
				[ 4, [ 'ef', 'EF' ], 4 ],
				annotationTx( 'abcdEFghij', 3, 8, 10, 'set', boldHash, 0 ),
				[ 4, [
					[ [ 'E', [ boldHash ] ] ],
					[ 'E' ]
				], 5 ]
			],
			squashed: [ 3, [ 'defgh', [].concat(
				sequence( 'd', boldHash ),
				'E',
				sequence( 'Fgh', boldHash )
			) ], 2 ]
		},
		{
			message: 'insert, insert, remove',
			transactions: [
				[ 1, [ '', 'x' ], 3 ],
				[ 2, [ '', 'y' ], 3 ],
				[ 2, [ 'y', '' ], 3 ]
			],
			squashed: [ 1, [ '', 'x' ], 3 ]
		},
		{
			message: 'type, annotate, clear',
			transactions: [].concat(
				insertionTxList( 1, 'Foo bar baz qux quux', 3 ),
				[ annotationTx( 'WFoo bar baz qux quuxXYZ', 1, 21, 24, 'set', boldHash, 0 ) ],
				[ annotationTx( [].concat(
					'W',
					sequence( 'Foo bar baz qux quux', boldHash ),
					'XYZ'
				), 1, 4, 24, 'set', italicHash, 1 ) ],
				[ annotationTx( [].concat(
					'W',
					sequence( 'Foo', boldHash, italicHash ),
					sequence( ' bar baz qux quux', boldHash ),
					'XYZ'
				), 9, 12, 24, 'set', italicHash, 1 ) ],
				[ annotationTx( [].concat(
					'W',
					sequence( 'Foo', boldHash, italicHash ),
					sequence( ' bar ', boldHash ),
					sequence( 'baz', boldHash, italicHash ),
					sequence( ' qux quux', boldHash ),
					'XYZ'
				), 1, 17, 24, 'clear', boldHash, 0 ) ]
			),
			squashed: [ 1, [ '', [].concat(
				sequence( 'Foo', italicHash ),
				sequence( ' bar ' ),
				sequence( 'baz', italicHash ),
				sequence( ' qux ' ),
				sequence( 'quux', boldHash )
			) ], 3 ]
		},
		{
			message: 'insert, change attribute, change attribute again',
			transactions: [
				[ [ '', [
					{ type: 'heading', attributes: { level: 1 } },
					{ type: '/heading' }
				] ] ],
				[ { type: 'attribute', key: 'level', from: 1, to: 2 }, 2 ],
				[ { type: 'attribute', key: 'level', from: 2, to: 3 }, 2 ]
			],
			squashed: [ [ '', [
				{ type: 'heading', attributes: { level: 3 } },
				{ type: '/heading' }
			] ] ]
		},
		{
			message: 'insert, add attribute, remove attribute',
			transactions: [
				[ [ '', [
					{ type: 'heading' },
					{ type: '/heading' }
				] ] ],
				[ { type: 'attribute', key: 'level', from: undefined, to: 1 }, 2 ],
				[ { type: 'attribute', key: 'level', from: 1, to: undefined }, 2 ]
			],
			squashed: [ [ '', [
				{ type: 'heading' },
				{ type: '/heading' }
			] ] ]
		},
		{
			message: 'h1->h2->h3',
			transactions: [
				[ { type: 'attribute', key: 'level', from: 1, to: 2 }, 10 ],
				[ { type: 'attribute', key: 'level', from: 2, to: 3 }, 10 ]
			],
			squashed: [ { type: 'attribute', key: 'level', from: 1, to: 3 }, 10 ]
		},
		{
			message: 'h?->h1',
			transactions: [
				[ { type: 'attribute', key: 'level', from: 1, to: 2 }, 10 ],
				[ { type: 'attribute', key: 'level', from: 2, to: 3 }, 10 ]
			],
			squashed: [ { type: 'attribute', key: 'level', from: 1, to: 3 }, 10 ]
		},
		{
			message: 'h1->h2->p->remove contents',
			transactions: [
				[ { type: 'attribute', key: 'level', from: 1, to: 2 }, 9 ],
				[ [
					[ { type: 'heading', attributes: { level: 2 } } ],
					[ { type: 'paragraph' } ]
				], 7, [
					[ { type: '/heading' } ],
					[ { type: '/paragraph' } ]
				] ],
				[ 1, [ 'abcdefg', '' ], 1 ]
			],
			squashed: [ [ [].concat(
				{ type: 'heading', attributes: { level: 1 } },
				sequence( 'abcdefg' ),
				{ type: '/heading' }
			), [
				{ type: 'paragraph' },
				{ type: '/paragraph' }
			] ] ]
		},
		{
			message: 'Overlapping replacements',
			transactions: [
				[ [ 'AB', 'ab' ], 1 ],
				[ 1, [ 'bC', 'Bc' ] ]
			],
			squashed: [ [ 'ABC', 'aBc' ] ]
		},
		{
			message: 'Insert at an attribute change',
			transactions: [
				[ { type: 'attribute', key: 'level', from: 1, to: 2 }, 9 ],
				[ [ '', 'AB' ], 9 ]
			],
			squashed: [
				[ '', 'AB' ],
				{ type: 'attribute', key: 'level', from: 1, to: 2 },
				9
			]
		},
		{
			message: 'Attribute change at a retain',
			transactions: [
				[ 9 ],
				[ 2, { type: 'attribute', key: 'level', from: 1, to: 2 }, 7 ]
			],
			squashed: [
				2,
				{ type: 'attribute', key: 'level', from: 1, to: 2 },
				7
			]
		},
		{
			message: 'Remove from the middle of an insert',
			transactions: [
				[ 2, [ 'ABCDEF', 'abcdef' ], 10 ],
				[ 4, [ 'cd', '' ], 12 ]
			],
			squashed: [
				2,
				[ 'ABCDEF', 'abef' ],
				10
			]
		}
	];

	for ( i = 0, iLen = tests.length; i < iLen; i++ ) {
		test = tests[ i ];
		transactions = test.transactions.map( function ( txData ) {
			return ve.dm.Transaction.static.deserialize( txData );
		} );
		ve.deepFreeze( transactions );
		if ( test.error ) {
			// eslint-disable-next-line no-loop-func
			assert.throws( function () {
				ve.dm.TransactionSquasher.static.squash( transactions );
			}, Error, test.message );
			continue;
		}
		squashed = ve.dm.Transaction.static.deserialize( test.squashed );

		assert.deepEqual(
			ve.dm.TransactionSquasher.static.squash( transactions ).operations,
			squashed.operations,
			test.message + ': squash all'
		);

		for ( j = 1, jLen = transactions.length - 1; j < jLen; j++ ) {
			squashedBefore = ve.dm.TransactionSquasher.static.squash( transactions.slice( 0, j ) );
			squashedAfter = ve.dm.TransactionSquasher.static.squash( transactions.slice( j ) );
			assert.deepEqual(
				ve.dm.TransactionSquasher.static.squash( [
					squashedBefore,
					squashedAfter
				] ).operations,
				squashed.operations,
				test.message + ': squash squashed halves split at ' + j
			);
		}
	}
} );
