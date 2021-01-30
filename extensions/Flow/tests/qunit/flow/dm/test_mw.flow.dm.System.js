QUnit.module( 'ext.flow.dm mw.flow.dm.System' );

/* Tests */

QUnit.test( 'Initialize flow system', function ( assert ) {
	var i, len, j, jlen, system, op, result, ops,
		executeOperation = function ( obj, operation, params ) {
			return obj[ operation ].apply( obj, params );
		},
		truncatedApiData = {
			submitted: {
				'offset-dir': 'fwd',
				sortby: 'user',
				'offset-id': null,
				offset: '0',
				limit: 10
			},
			errors: [],
			sortby: 'newest',
			workflowId: 'sfykaxy3moyu18iz',
			roots: [
				'sfykaxy9v6pfdze3'
			],
			posts: {
				sfykaxy9v6pfdze3: [
					'sfykaxybfb52q63v'
				],
				sfykaxybfd43u82z: [
					'sfykaxybfd43u82z'
				],
				sfykbdkszd4qpcvf: [
					'sfykbdkszd4qpcvf'
				]
			},
			revisions: {
				sfykaxybfb52q63v: {
					workflowId: 'sfykaxy9v6pfdze3',
					articleTitle: 'Topic:Sfykaxy9v6pfdze3',
					revisionId: 'sfykaxybfb52q63v',
					timestamp: '20150422230352',
					changeType: 'new-post',
					dateFormats: [],
					properties: {
						'topic-of-post': {
							plaintext: 'This is a test content in topic-title-wikitext.'
						}
					},
					isOriginalContent: true,
					isModerated: false,
					author: {
						name: 'Tester',
						wiki: 'mediawiki',
						gender: 'female',
						links: {
							contribs: {
								url: '/wiki/index.php/Special:Contributions/Tester',
								title: 'Contributions/Tester',
								exists: true
							},
							userpage: {
								url: '/wiki/index.php/User:Tester',
								title: 'Tester',
								exists: false
							},
							talk: {
								url: '/wiki/index.php/User_talk:Tester',
								title: 'User talk:Tester',
								exists: true
							}
						},
						id: 3
					},
					lastEditUser: {
						name: null,
						wiki: null,
						gender: 'unknown',
						links: [],
						id: null
					},
					lastEditId: null,
					previousRevisionId: null,
					content: {
						content: 'This is a test content in topic-title-wikitext.',
						format: 'topic-title-wikitext'
					},
					watchable: false,
					replyToId: null,
					postId: 'sfykaxy9v6pfdze3',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Tester',
						wiki: 'mediawiki',
						gender: 'female',
						links: {
							contribs: {
								url: '/wiki/index.php/Special:Contributions/Tester',
								title: 'Contributions/Tester',
								exists: true
							},
							userpage: {
								url: '/wiki/index.php/User:Tester',
								title: 'Tester',
								exists: false
							},
							talk: {
								url: '/wiki/index.php/User_talk:Tester',
								title: 'User talk:Tester',
								exists: true
							}
						},
						id: 3
					},
					replies: [
						'sfykaxybfd43u82z'
					],
					reply_count: 2,
					last_updated_readable: '16:54, 6 May 2015',
					last_updated: 1430956497000
				},
				sfykaxybfd43u82z: {
					workflowId: 'sfykaxy9v6pfdze3',
					articleTitle: 'Topic:Sfykaxy9v6pfdze3',
					revisionId: 'sfykaxybfd43u82z',
					timestamp: '20150422230352',
					changeType: 'reply',
					dateFormats: [],
					properties: [],
					isOriginalContent: true,
					isModerated: false,
					size: {
						old: '0',
						new: '12'
					},
					author: {
						name: 'Tester',
						wiki: 'mediawiki',
						gender: 'female',
						links: {
							contribs: {
								url: '/wiki/index.php/Special:Contributions/Tester',
								title: 'Contributions/Tester',
								exists: true
							},
							userpage: {
								url: '/wiki/index.php/User:Tester',
								title: 'Tester',
								exists: false
							},
							talk: {
								url: '/wiki/index.php/User_talk:Tester',
								title: 'User talk:Tester',
								exists: true
							}
						},
						id: 3
					},
					lastEditUser: {
						name: null,
						wiki: null,
						gender: 'unknown',
						links: [],
						id: null
					},
					lastEditId: null,
					previousRevisionId: null,
					content: {
						content: '<p data-parsoid=\'{"dsr":[0,12,0,0]}\'>Testing a post</p>',
						format: 'fixed-html'
					},
					watchable: false,
					replyToId: 'sfykaxy9v6pfdze3',
					postId: 'sfykaxybfd43u82z',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Tester',
						wiki: 'mediawiki',
						gender: 'female',
						links: {
							contribs: {
								url: '/wiki/index.php/Special:Contributions/Tester',
								title: 'Contributions/Tester',
								exists: true
							},
							userpage: {
								url: '/wiki/index.php/User:Tester',
								title: 'Tester',
								exists: false
							},
							talk: {
								url: '/wiki/index.php/User_talk:Tester',
								title: 'User talk:Tester',
								exists: true
							}
						},
						id: 3
					},
					replies: [
						'sfykbdkszd4qpcvf'
					]
				},
				sfykbdkszd4qpcvf: {
					workflowId: 'sfykaxy9v6pfdze3',
					articleTitle: 'Topic:Sfykaxy9v6pfdze3',
					revisionId: 'sfykbdkszd4qpcvf',
					timestamp: '20150422230404',
					changeType: 'reply',
					dateFormats: [],
					properties: [],
					isOriginalContent: true,
					isModerated: false,
					size: {
						old: '0',
						new: '29'
					},
					author: {
						name: 'Tester',
						wiki: 'mediawiki',
						gender: 'female',
						links: {
							contribs: {
								url: '/wiki/index.php/Special:Contributions/Tester',
								title: 'Contributions/Tester',
								exists: true
							},
							userpage: {
								url: '/wiki/index.php/User:Tester',
								title: 'Tester',
								exists: false
							},
							talk: {
								url: '/wiki/index.php/User_talk:Tester',
								title: 'User talk:Tester',
								exists: true
							}
						},
						id: 3
					},
					lastEditUser: {
						name: null,
						wiki: null,
						gender: 'unknown',
						links: [],
						id: null
					},
					lastEditId: null,
					previousRevisionId: null,
					content: {
						content: '<p data-parsoid=\'{"dsr":[0,29,0,0]}\'>asdf asdf sdf asd sd fds fsdf</p>',
						format: 'fixed-html'
					},
					watchable: false,
					replyToId: 'sfykaxybfd43u82z',
					postId: 'sfykbdkszd4qpcvf',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Tester',
						wiki: 'mediawiki',
						gender: 'female',
						links: {
							contribs: {
								url: '/wiki/index.php/Special:Contributions/Tester',
								title: 'Contributions/Tester',
								exists: true
							},
							userpage: {
								url: '/wiki/index.php/User:Tester',
								title: 'Tester',
								exists: false
							},
							talk: {
								url: '/wiki/index.php/User_talk:Tester',
								title: 'User talk:Tester',
								exists: true
							}
						},
						id: 3
					},
					replies: []
				}
			},
			title: 'User talk:Tester',
			type: 'topiclist'
		},
		cases = [
			{
				method: 'getPageTitle',
				expected: mw.Title.newFromText( 'Main_Page' ),
				msg: 'Get page title'
			},
			{
				method: 'getToCPostsLimit',
				expected: 20,
				msg: 'ToC post limit'
			},
			{
				method: 'getBoard.getId',
				expected: 'sfykaxy3moyu18iz',
				msg: 'Check board id'
			},
			{
				method: 'populateBoardTopicsFromJson',
				args: [ truncatedApiData ],
				skipTest: true
			},
			{
				method: 'getBoard.getItemCount',
				expected: 1,
				msg: 'Check topic count in board'
			}
		];

	system = new mw.flow.dm.System( {
		pageTitle: mw.Title.newFromText( 'Main_Page' ),
		tocPostsLimit: 20,
		boardId: 'sfykaxy3moyu18iz'
	} );

	for ( i = 0, len = cases.length; i < len; i++ ) {
		op = cases[ i ];

		if ( op.method.indexOf( '.' ) > -1 ) {
			// Nested operations
			ops = op.method.split( '.' );
			result = system;
			for ( j = 0, jlen = ops.length; j < jlen; j++ ) {
				result = executeOperation( result, ops[ j ], [] );
			}
		} else {
			// Regular operations
			result = executeOperation( system, op.method, op.args || [] );
		}

		if ( op.skipTest ) {
			continue;
		}

		// Types of comparisons
		if ( op.operation === 'instanceof' ) {
			assert.ok( result instanceof op.expected, op.msg );
		} else {
			assert.deepEqual( result, op.expected, op.msg );
		}
	}
} );
