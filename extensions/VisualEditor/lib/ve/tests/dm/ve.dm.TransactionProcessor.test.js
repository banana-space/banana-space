/*!
 * VisualEditor DataModel TransactionProcessor tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.dm.TransactionProcessor' );

/* Tests */

QUnit.test( 'commit', function ( assert ) {
	var i, j, originalData, originalDoc, node,
		msg, testDoc, txBuilder, tx, expectedData, expectedDoc,
		store = ve.dm.example.createExampleDocument().getStore(),
		cases = {
			'no operations': {
				calls: [],
				expected: function () {}
			},
			retaining: {
				calls: [ [ 'pushRetain', 38 ] ],
				expected: function () {}
			},
			'changing, removing and adding attributes': {
				calls: [
					[ 'pushReplaceElementAttribute', 'level', 1, 2 ],
					[ 'pushRetain', 12 ],
					[ 'pushReplaceElementAttribute', 'style', 'bullet', 'number' ],
					[ 'pushReplaceElementAttribute', 'test', undefined, 'abcd' ],
					[ 'pushRetain', 27 ],
					[ 'pushReplaceElementAttribute', 'src', ve.dm.example.imgSrc, undefined ]
				],
				expected: function ( data ) {
					data[ 0 ].attributes.level = 2;
					data[ 12 ].attributes.style = 'number';
					data[ 12 ].attributes.test = 'abcd';
					delete data[ 39 ].attributes.src;
				}
			},
			'changing attributes on non-element data throws an exception': {
				calls: [
					[ 'pushRetain', 1 ],
					[ 'pushReplaceElementAttribute', 'foo', 23, 42 ]
				],
				exception: /Invalid element error, cannot set attributes on non-element data/
			},
			'inserting text': {
				calls: [
					[ 'pushRetain', 1 ],
					[ 'pushReplacement', 1, 0, [ 'F', 'O', 'O' ] ]
				],
				expected: function ( data ) {
					data.splice( 1, 0, 'F', 'O', 'O' );
				}
			},
			'removing text': {
				calls: [
					[ 'pushRetain', 1 ],
					[ 'pushReplacement', 1, 1, [] ]
				],
				expected: function ( data ) {
					data.splice( 1, 1 );
				}
			},
			'replacing text': {
				calls: [
					[ 'pushRetain', 1 ],
					[ 'pushReplacement', 1, 1, [ 'F', 'O', 'O' ] ]
				],
				expected: function ( data ) {
					data.splice( 1, 1, 'F', 'O', 'O' );
				}
			},
			'emptying text': {
				calls: [
					[ 'pushRetain', 10 ],
					[ 'pushReplacement', 10, 1, [] ]
				],
				expected: function ( data ) {
					data.splice( 10, 1 );
				}
			},
			'inserting mixed content': {
				calls: [
					[ 'pushRetain', 1 ],
					[ 'pushReplacement', 1, 1, [ 'F', 'O', 'O', { type: 'inlineImage' }, { type: '/inlineImage' }, 'B', 'A', 'R' ] ]
				],
				expected: function ( data ) {
					data.splice( 1, 1, 'F', 'O', 'O', { type: 'inlineImage' }, { type: '/inlineImage' }, 'B', 'A', 'R' );
				}
			},
			'inserting unbalanced data': {
				calls: [
					[ 'pushReplacement', 0, 0, [ { type: 'table' } ] ]
				],
				exception: /Unbalanced set of replace operations found/
			},
			'inserting unclosed inline node': {
				calls: [
					[ 'pushRetain', 1 ],
					[ 'pushReplacement', 1, 1, [ 'F', { type: 'inlineImage' }, 'O', 'O' ] ]
				],
				exception: /Unbalanced set of replace operations found/
			},
			'inserting an inline node in a structure position': {
				calls: [
					[ 'pushReplacement', 0, 0, [
						{ type: 'inlineImage' },
						{ type: '/inlineNode' }
					] ]
				],
				exception: /Cannot add content node \(inlineImage\) to a document node/
			},
			'wrapping a heading in an inline node': {
				calls: [
					[ 'pushRetain', 39 ],
					[ 'pushReplacement', 39, 0, [ { type: 'inlineImage' } ] ],
					[ 'pushRetain', 2 ],
					[ 'pushReplacement', 42, 0, [ { type: '/inlineImage' } ] ]
				],
				exception: /Cannot add a child to inlineImage node/
			},
			'converting an element': {
				calls: [
					[ 'pushReplacement', 0, 1, [ { type: 'paragraph' } ] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 4, 1, [ { type: '/paragraph' } ] ]
				],
				expected: function ( data ) {
					data[ 0 ].type = 'paragraph';
					delete data[ 0 ].attributes;
					data[ 4 ].type = '/paragraph';
				}
			},
			'conversion with wrong closing': {
				calls: [
					[ 'pushReplacement', 0, 1, [ { type: 'paragraph' } ] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 4, 1, [ { type: '/paragraph' }, { type: 'paragraph' } ] ]
				],
				exception: /Unbalanced set of replace operations found/
			},
			'splitting an element': {
				calls: [
					[ 'pushRetain', 2 ],
					[
						'pushReplacement', 2, 0,
						[ { type: '/heading' }, { type: 'heading', attributes: { level: 1 } } ]
					]
				],
				expected: function ( data ) {
					data.splice(
						2,
						0,
						{ type: '/heading' },
						{ type: 'heading', attributes: { level: 1 } }
					);
				}
			},
			'merging an element': {
				calls: [
					[ 'pushRetain', 57 ],
					[ 'pushReplacement', 57, 2, [] ]
				],
				expected: function ( data ) {
					data.splice( 57, 2 );
				}
			},
			'stripping elements': {
				calls: [
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 3, 1, [] ],
					[ 'pushRetain', 6 ],
					[ 'pushReplacement', 10, 1, [] ]
				],
				expected: function ( data ) {
					data.splice( 10, 1 );
					data.splice( 3, 1 );
				}
			},
			'wrapping elements': {
				calls: [
					[ 'pushRetain', 55 ],
					[ 'pushReplacement', 55, 0, [ { type: 'list', attributes: { style: 'number' } }, { type: 'listItem' } ] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 58, 0, [ { type: '/listItem' }, { type: 'listItem' } ] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 61, 0, [ { type: '/listItem' }, { type: '/list' } ] ]
				],
				expected: function ( data ) {
					data.splice( 61, 0, { type: '/listItem' }, { type: '/list' } );
					data.splice( 58, 0, { type: '/listItem' }, { type: 'listItem' } );
					data.splice( 55, 0, { type: 'list', attributes: { style: 'number' } }, { type: 'listItem' } );
				}
			},
			'unwrapping elements': {
				calls: [
					[ 'pushRetain', 43 ],
					[ 'pushReplacement', 43, 2, [] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 48, 2, [] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 53, 2, [] ]
				],
				expected: function ( data ) {
					data.splice( 53, 2 );
					data.splice( 48, 2 );
					data.splice( 43, 2 );
				}
			},
			'rewrapping elements': {
				calls: [
					[ 'pushRetain', 43 ],
					[ 'pushReplacement', 43, 2, [ { type: 'list', attributes: { style: 'number' } }, { type: 'listItem' } ] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 48, 2, [ { type: '/listItem' }, { type: 'listItem' } ] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 53, 2, [ { type: '/listItem' }, { type: '/list' } ] ]
				],
				expected: function ( data ) {
					data.splice( 53, 2, { type: '/listItem' }, { type: '/list' } );
					data.splice( 48, 2, { type: '/listItem' }, { type: 'listItem' } );
					data.splice( 43, 2, { type: 'list', attributes: { style: 'number' } }, { type: 'listItem' } );
				}
			},
			'merging a nested element': {
				calls: [
					[ 'pushRetain', 47 ],
					[ 'pushReplacement', 47, 4, [] ]
				],
				expected: function ( data ) {
					data.splice( 47, 4 );
				}
			},
			'merging an element that also has a content insertion': {
				calls: [
					[ 'pushRetain', 56 ],
					[ 'pushReplacement', 56, 0, [ 'x' ] ],
					[ 'pushRetain', 1 ],
					[ 'pushReplacement', 57, 2, [] ]
				],
				expected: function ( data ) {
					data.splice( 57, 2 );
					data.splice( 56, 0, 'x' );
				}
			},
			'merging a nested element that also has a structural insertion': {
				calls: [
					[ 'pushRetain', 45 ],
					[ 'pushReplacement', 45, 0, [ { type: 'paragraph' }, 'x', { type: '/paragraph' } ] ],
					[ 'pushRetain', 2 ],
					[ 'pushReplacement', 47, 4, [] ]
				],
				expected: function ( data ) {
					data.splice( 47, 4 );
					data.splice( 45, 0, { type: 'paragraph' }, 'x', { type: '/paragraph' } );
				}
			},
			'merging the same element from both sides at once': {
				data: [
					{ type: 'paragraph' },
					'a',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					'b',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					'c',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					'd',
					{ type: '/paragraph' }
				],
				calls: [
					[ 'pushRetain', 2 ],
					[ 'pushReplacement', 2, 2, [] ],
					[ 'pushRetain', 1 ],
					[ 'pushReplacement', 5, 2, [] ]
				],
				expected: function ( data ) {
					data.splice( 5, 2 );
					data.splice( 2, 2 );
				}
			},
			'deleting images on both sides of a text node at once': {
				data: [
					{ type: 'paragraph' },
					'a',
					{ type: 'inlineImage' },
					{ type: '/inlineImage' },
					'b',
					{ type: 'inlineImage' },
					{ type: '/inlineImage' },
					'c',
					{ type: '/paragraph' }
				],
				calls: [
					[ 'pushRetain', 2 ],
					[ 'pushReplacement', 2, 2, [] ],
					[ 'pushRetain', 1 ],
					[ 'pushReplacement', 5, 2, [] ]
				],
				expected: function ( data ) {
					data.splice( 5, 2 );
					data.splice( 2, 2 );
				}
			},
			'unwrap inside of a split inside of a wrap': {
				data: [
					{ type: 'list', attributes: { style: 'bullet' } },
					{ type: 'listItem' },
					{ type: 'div' },
					{ type: 'paragraph' },
					'a',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					'b',
					{ type: '/paragraph' },
					{ type: 'paragraph' },
					'c',
					{ type: '/paragraph' },
					{ type: '/div' },
					{ type: '/listItem' },
					{ type: '/list' },
					{ type: 'paragraph' },
					'd',
					{ type: '/paragraph' }
				],
				calls: [
					[ 'pushReplacement', 0, 0, [ { type: 'div' } ] ],
					[ 'pushRetain', 6 ],
					[ 'pushReplacement', 6, 0, [ { type: '/div' } ] ],
					[ 'pushRetain', 3 ],
					[ 'pushReplacement', 9, 0, [ { type: 'div' } ] ],
					[ 'pushRetain', 6 ],
					[ 'pushReplacement', 15, 0, [ { type: '/div' } ] ]
				],
				expected: function ( data ) {
					data.splice( 15, 0, { type: '/div' } );
					data.splice( 9, 0, { type: 'div' } );
					data.splice( 6, 0, { type: '/div' } );
					data.splice( 0, 0, { type: 'div' } );
				}
			},
			'inserting text after alien node at the end': {
				data: [
					{ type: 'paragraph' },
					'a',
					{ type: 'alienInline' },
					{ type: '/alienInline' },
					{ type: '/paragraph' }
				],
				calls: [
					[ 'pushRetain', 4 ],
					[ 'pushReplacement', 4, 0, [ 'b' ] ]
				],
				expected: function ( data ) {
					data.splice( 4, 0, 'b' );
				}
			},
			'structural replacement starting at an offset without metadata': {
				data: [
					{ type: 'paragraph' },
					'F',
					{ type: '/paragraph' },
					{
						type: 'alienMeta',
						originalDomElements: $( '<!-- foo -->' ).toArray()
					},
					{ type: '/alienMeta' },
					{ type: 'paragraph' },
					'o', 'o',
					{ type: '/paragraph' }
				],
				calls: [
					[ 'pushReplacement', 0, 9, [ { type: 'table' }, { type: '/table' } ] ]
				],
				expected: function ( data ) {
					data.splice( 0, 3 );
					data.splice( 2, 4, { type: 'table' }, { type: '/table' } );
				}
			},
			'structural replacement starting at an offset with metadata': {
				data: [
					{
						type: 'alienMeta',
						originalDomElements: $( '<!-- foo -->' ).toArray()
					},
					{ type: '/alienMeta' },
					{ type: 'paragraph' },
					'F',
					{
						type: 'alienMeta',
						attributes: {
							style: 'comment',
							text: ' inline '
						}
					},
					{ type: '/alienMeta' },
					'o', 'o',
					{ type: '/paragraph' }
				],
				calls: [
					[ 'pushReplacement', 0, 9, [ { type: 'table' }, { type: '/table' } ] ]
				],
				expected: function ( data ) {
					// Metadata  is merged
					data.splice( 2, 2 );
					data.splice( 4, 3, { type: 'table' }, { type: '/table' } );
				}
			},
			'structural replacement ending at an offset with metadata': {
				data: [
					{
						type: 'alienMeta',
						originalDomElements: $( '<!-- foo -->' ).toArray()
					},
					{ type: '/alienMeta' },
					{ type: 'paragraph' },
					'F',
					{
						type: 'alienMeta',
						attributes: {
							style: 'comment',
							text: ' inline '
						}
					},
					{ type: '/alienMeta' },
					'o', 'o',
					{ type: '/paragraph' },
					{
						type: 'alienMeta',
						originalDomElements: $( '<!-- bar -->' ).toArray()
					},
					{ type: '/alienMeta' },
					{ type: 'paragraph' },
					'B', 'a', 'r',
					{ type: '/paragraph' }
				],
				calls: [
					[ 'pushReplacement', 0, 9, [ { type: 'table' }, { type: '/table' } ] ],
					[ 'pushRetain', 5 ]
				],
				expected: function ( data ) {
					// Metadata  is merged
					data.splice( 2, 2 );
					data.splice( 4, 3, { type: 'table' }, { type: '/table' } );
				}
			},
			'structural deletion ending at an offset with metadata': {
				data: [
					{
						type: 'alienMeta',
						originalDomElements: $( '<!-- foo -->' ).toArray()
					},
					{ type: '/alienMeta' },
					{ type: 'paragraph' },
					'F',
					{
						type: 'alienMeta',
						attributes: {
							style: 'comment',
							text: ' inline '
						}
					},
					{ type: '/alienMeta' },
					'o', 'o',
					{ type: '/paragraph' },
					{
						type: 'alienMeta',
						originalDomElements: $( '<!-- bar -->' ).toArray()
					},
					{ type: '/alienMeta' },
					{ type: 'paragraph' },
					'B', 'a', 'r',
					{ type: '/paragraph' }
				],
				calls: [
					[ 'pushReplacement', 0, 11, [] ],
					[ 'pushRetain', 5 ]
				],
				expected: function ( data ) {
					// Metadata  is merged
					data.splice( 2, 2 );
					data.splice( 4, 3 );
				}
			},
			'preserves surrounding metadata on unwrap': {
				data: ve.dm.example.listWithMeta,
				calls: [
					[ 'newFromWrap', new ve.Range( 5, 33 ),
						[ { type: 'list' } ], [],
						[ { type: 'listItem', attributes: { styles: [ 'bullet' ] } } ], []
					]
				],
				expected: function ( data ) {
					data.splice( 35, 1 ); // Remove '/list'
					data.splice( 32, 1 ); // Remove '/listItem'
					data.splice( 20, 1 ); // Remove 'listItem'
					data.splice( 17, 1 ); // Remove '/listItem'
					data.splice( 5, 1 ); // Remove 'listItem'
					data.splice( 2, 1 ); // Remove 'list'
				}
			},
			'preserves interleaved metadata on unwrap': {
				data: ve.dm.example.listWithMeta,
				calls: [
					[ 'newFromWrap', new ve.Range( 5, 35 ),
						[ { type: 'list' } ], [],
						[ { type: 'listItem', attributes: { styles: [ 'bullet' ] } } ], []
					]
				],
				expected: function ( data ) {
					data.splice( 35, 1 ); // Remove '/list'
					data.splice( 32, 1 ); // Remove '/listItem'
					data.splice( 20, 1 ); // Remove 'listItem'
					data.splice( 17, 1 ); // Remove '/listItem'
					data.splice( 5, 1 ); // Remove 'listItem'
					data.splice( 2, 1 ); // Remove 'list'
				}
			},
			'preserves trailing metadata': {
				data: ve.dm.example.listWithMeta,
				calls: [
					[ 'newFromInsertion', 12, [ 'b' ] ]
				],
				expected: function ( data ) {
					ve.batchSplice( data, 12, 0, [ 'b' ] );
				}
			}
		};

	// Run tests
	for ( msg in cases ) {
		// Generate original document
		originalData = cases[ msg ].data || ve.dm.example.data;
		originalDoc = new ve.dm.Document(
			ve.dm.example.preprocessAnnotations( ve.copy( originalData ), store )
		);
		originalDoc.buildNodeTree();
		testDoc = new ve.dm.Document(
			ve.dm.example.preprocessAnnotations( ve.copy( originalData ), store )
		);
		testDoc.buildNodeTree();

		txBuilder = new ve.dm.TransactionBuilder();
		tx = null;
		for ( i = 0; i < cases[ msg ].calls.length; i++ ) {
			// Some calls need the document as its first argument
			if ( /^(pushReplacement$|new)/.test( cases[ msg ].calls[ i ][ 0 ] ) ) {
				cases[ msg ].calls[ i ].splice( 1, 0, testDoc );
			}
			// Special case static methods of TransactionBuilder
			if ( /^new/.test( cases[ msg ].calls[ i ][ 0 ] ) ) {
				tx = ve.dm.TransactionBuilder.static[ cases[ msg ].calls[ i ][ 0 ] ].apply( null, cases[ msg ].calls[ i ].slice( 1 ) );
				break;
			}
			txBuilder[ cases[ msg ].calls[ i ][ 0 ] ].apply( txBuilder, cases[ msg ].calls[ i ].slice( 1 ) );
		}
		if ( tx === null ) {
			tx = txBuilder.getTransaction();
		}

		if ( 'expected' in cases[ msg ] ) {
			// Generate expected document
			expectedData = ve.copy( originalData );
			cases[ msg ].expected( expectedData );
			expectedDoc = new ve.dm.Document(
				ve.dm.example.preprocessAnnotations( expectedData, store )
			);
			expectedDoc.buildNodeTree();

			if ( 'events' in cases[ msg ] ) {
				// Set up event handlers
				for ( i = 0; i < cases[ msg ].events.length; i++ ) {
					node = testDoc.getDocumentNode();
					for ( j = 1; j < cases[ msg ].events[ i ].length; j++ ) {
						node = node.getChildren()[ cases[ msg ].events[ i ][ j ] ];
					}
					node.on( cases[ msg ].events[ i ][ 0 ], ( function ( obj ) {
						return function () {
							obj.fired = ( obj.fired || 0 ) + 1;
						};
					}( cases[ msg ].events[ i ] ) ) );
				}
			}

			// Commit
			testDoc.commit( tx );
			assert.equalLinearDataWithDom( testDoc.getStore(), testDoc.getFullData(), expectedDoc.getFullData(), 'commit (data): ' + msg );
			assert.equalNodeTree(
				testDoc.getDocumentNode(),
				expectedDoc.getDocumentNode(),
				'commit (tree): ' + msg
			);
			if ( 'events' in cases[ msg ] ) {
				for ( i = 0; i < cases[ msg ].events.length; i++ ) {
					assert.strictEqual(
						cases[ msg ].events[ i ].fired,
						1,
						'event ' + cases[ msg ].events[ i ][ 0 ] + ' on ' +
							cases[ msg ].events[ i ].slice( 1 ).join( ',' ) + ': ' + msg
					);
				}
			}
			// Rollback
			testDoc.commit( tx.reversed() );
			assert.equalLinearDataWithDom( testDoc.getStore(), testDoc.getFullData(), originalDoc.getFullData(), 'rollback (data): ' + msg );
			assert.equalNodeTree(
				testDoc.getDocumentNode(),
				originalDoc.getDocumentNode(),
				'rollback (tree): ' + msg
			);
		} else if ( 'exception' in cases[ msg ] ) {
			assert.throws(
				// eslint-disable-next-line no-loop-func
				function () {
					testDoc.commit( tx );
				},
				cases[ msg ].exception,
				'exception thrown: ' + msg
			);
			assert.equalLinearDataWithDom( testDoc.getStore(), testDoc.getFullData(), originalDoc.getFullData(), 'data unmodified: ' + msg );
			assert.equalNodeTree(
				testDoc.getDocumentNode(),
				originalDoc.getDocumentNode(),
				'tree unmodified: ' + msg
			);
		}
	}
} );

// TODO: Fix the code so undoing unbold roundtrips properly, then fix this test to reflect that
QUnit.test( 'undo clear annotation', function ( assert ) {
	var doc, tx,
		origData = [
			{ type: 'paragraph' },
			[ 'x', [ ve.dm.example.boldHash, ve.dm.example.italicHash ] ],
			{ type: '/paragraph' }
		];
	doc = ve.dm.example.createExampleDocumentFromData( origData );
	doc.store.hash( ve.dm.example.italic );
	doc.store.hash( ve.dm.example.bold );
	tx = ve.dm.TransactionBuilder.static.newFromAnnotation(
		doc,
		new ve.Range( 1, 2 ),
		'clear',
		ve.dm.example.bold
	);
	doc.commit( tx );
	doc.commit( tx.reversed() );
	assert.deepEqual( doc.data.data, origData, 'Roundtrip difference undoing unbold under italic' );
} );
