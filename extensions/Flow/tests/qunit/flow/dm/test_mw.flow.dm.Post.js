QUnit.module( 'ext.flow.dm mw.flow.dm.Post' );

/* Tests */

QUnit.test( 'Hierarchical post structure', function ( assert ) {
	var topic, posts, replies, subreplies, subsubreplies, topicRevisionData,
		truncatedApiData = {
			type: 'topic',
			roots: [
				'slmursrfvx65co7d'
			],
			posts: {
				slmursrfvx65co7d: [ 'slmursrhg1lsoux5' ],
				slmursxodnri3jbd: [ 'slmursxodnri3jbd' ],
				slmusa0v7mr51rih: [ 'slmusa0v7mr51rih' ],
				slmusuwtamg314zt: [ 'slmusuwtamg314zt' ],
				slmut77ka2k80hvt: [ 'slmut77ka2k80hvt' ],
				slmuthyr3gzloi2x: [ 'slmuthyr3gzloi2x' ],
				slmutvg78doyfszt: [ 'slmutvg78doyfszt' ],
				slmuufr0j99j851l: [ 'slmuufr0j99j851l' ]
			},
			revisions: {
				slmursrhg1lsoux5: {
					workflowId: 'slmursrfvx65co7d',
					articleTitle: 'Topic:Slmursrfvx65co7d',
					revisionId: 'slmursrhg1lsoux5',
					timestamp: '20150723160332',
					changeType: 'new-post',
					dateFormats: [],
					properties: {
						'topic-of-post': {
							plaintext: 'Topic with hierarchical posts'
						}
					},
					isOriginalContent: true,
					isModerated: false,
					author: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
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
					isLocked: false,
					isModeratedNotLocked: false,
					content: {
						content: 'Topic with hierarchical posts',
						format: 'topic-title-wikitext'
					},
					isWatched: true,
					watchable: true,
					replyToId: null,
					postId: 'slmursrfvx65co7d',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
					},
					replies: [
						'slmursxodnri3jbd',
						'slmusa0v7mr51rih'
					],
					reply_count: 7,
					last_updated: 1437667491000
				},
				slmursxodnri3jbd: {
					_BC_bools: [
						'isOriginalContent',
						'isModerated',
						'isLocked',
						'isModeratedNotLocked',
						'isWatched',
						'watchable',
						'isMaxThreadingDepth'
					],
					workflowId: 'slmursrfvx65co7d',
					articleTitle: 'Topic:Slmursrfvx65co7d',
					revisionId: 'slmursxodnri3jbd',
					timestamp: '20150723160332',
					changeType: 'reply',
					dateFormats: [],
					properties: [],
					isOriginalContent: true,
					isModerated: false,
					author: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
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
					isLocked: false,
					isModeratedNotLocked: false,
					content: {
						content: 'Post #1',
						format: 'fixed-html'
					},
					isWatched: false,
					watchable: true,
					replyToId: 'slmursrfvx65co7d',
					postId: 'slmursxodnri3jbd',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
					},
					replies: [
						'slmusuwtamg314zt',
						'slmut77ka2k80hvt'
					]
				},
				slmusa0v7mr51rih: {
					_BC_bools: [
						'isOriginalContent',
						'isModerated',
						'isLocked',
						'isModeratedNotLocked',
						'isWatched',
						'watchable',
						'isMaxThreadingDepth'
					],
					workflowId: 'slmursrfvx65co7d',
					articleTitle: 'Topic:Slmursrfvx65co7d',
					revisionId: 'slmusa0v7mr51rih',
					timestamp: '20150723160346',
					changeType: 'reply',
					dateFormats: [],
					properties: [],
					isOriginalContent: true,
					isModerated: false,
					author: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
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
					isLocked: false,
					isModeratedNotLocked: false,
					content: {
						content: 'Post #2',
						format: 'fixed-html'
					},
					isWatched: false,
					watchable: true,
					replyToId: 'slmursrfvx65co7d',
					postId: 'slmusa0v7mr51rih',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
					},
					replies: []
				},
				slmusuwtamg314zt: {
					_BC_bools: [
						'isOriginalContent',
						'isModerated',
						'isLocked',
						'isModeratedNotLocked',
						'isWatched',
						'watchable',
						'isMaxThreadingDepth'
					],
					workflowId: 'slmursrfvx65co7d',
					articleTitle: 'Topic:Slmursrfvx65co7d',
					revisionId: 'slmusuwtamg314zt',
					timestamp: '20150723160404',
					changeType: 'reply',
					dateFormats: [],
					properties: [],
					isOriginalContent: true,
					isModerated: false,
					author: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
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
					isLocked: false,
					isModeratedNotLocked: false,
					content: {
						content: 'Reply #1',
						format: 'fixed-html'
					},
					isWatched: false,
					watchable: true,
					replyToId: 'slmursxodnri3jbd',
					postId: 'slmusuwtamg314zt',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
					},
					replies: [
						'slmuthyr3gzloi2x',
						'slmutvg78doyfszt'
					]
				},
				slmut77ka2k80hvt: {
					_BC_bools: [
						'isOriginalContent',
						'isModerated',
						'isLocked',
						'isModeratedNotLocked',
						'isWatched',
						'watchable',
						'isMaxThreadingDepth'
					],
					workflowId: 'slmursrfvx65co7d',
					articleTitle: 'Topic:Slmursrfvx65co7d',
					revisionId: 'slmut77ka2k80hvt',
					timestamp: '20150723160414',
					changeType: 'reply',
					dateFormats: [],
					properties: [],
					isOriginalContent: true,
					isModerated: false,
					author: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
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
					isLocked: false,
					isModeratedNotLocked: false,
					content: {
						content: 'Reply #2',
						format: 'fixed-html'
					},
					isWatched: false,
					watchable: true,
					replyToId: 'slmursxodnri3jbd',
					postId: 'slmut77ka2k80hvt',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
					},
					replies: []
				},
				slmuthyr3gzloi2x: {
					_BC_bools: [
						'isOriginalContent',
						'isModerated',
						'isLocked',
						'isModeratedNotLocked',
						'isWatched',
						'watchable',
						'isMaxThreadingDepth'
					],
					workflowId: 'slmursrfvx65co7d',
					articleTitle: 'Topic:Slmursrfvx65co7d',
					revisionId: 'slmuthyr3gzloi2x',
					timestamp: '20150723160423',
					changeType: 'reply',
					dateFormats: [],
					properties: [],
					isOriginalContent: true,
					isModerated: false,
					author: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
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
					isLocked: false,
					isModeratedNotLocked: false,
					content: {
						content: 'Sub reply #1'
					},
					isWatched: false,
					watchable: true,
					replyToId: 'slmusuwtamg314zt',
					postId: 'slmuthyr3gzloi2x',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
					},
					replies: [
						'slmuufr0j99j851l'
					]
				},
				slmutvg78doyfszt: {
					_BC_bools: [
						'isOriginalContent',
						'isModerated',
						'isLocked',
						'isModeratedNotLocked',
						'isWatched',
						'watchable',
						'isMaxThreadingDepth'
					],
					workflowId: 'slmursrfvx65co7d',
					articleTitle: 'Topic:Slmursrfvx65co7d',
					revisionId: 'slmutvg78doyfszt',
					timestamp: '20150723160434',
					changeType: 'reply',
					dateFormats: [],
					properties: [],
					isOriginalContent: true,
					isModerated: false,
					author: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
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
					isLocked: false,
					isModeratedNotLocked: false,
					content: {
						content: 'Sub reply #2',
						format: 'fixed-html'
					},
					isWatched: false,
					watchable: true,
					replyToId: 'slmusuwtamg314zt',
					postId: 'slmutvg78doyfszt',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
					},
					replies: []
				},
				slmuufr0j99j851l: {
					_BC_bools: [
						'isOriginalContent',
						'isModerated',
						'isLocked',
						'isModeratedNotLocked',
						'isWatched',
						'watchable',
						'isMaxThreadingDepth'
					],
					workflowId: 'slmursrfvx65co7d',
					articleTitle: 'Topic:Slmursrfvx65co7d',
					revisionId: 'slmuufr0j99j851l',
					timestamp: '20150723160451',
					changeType: 'reply',
					dateFormats: [],
					properties: [],
					isOriginalContent: true,
					isModerated: false,
					author: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
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
					isLocked: false,
					isModeratedNotLocked: false,
					content: {
						content: 'Sub sub reply #1',
						format: 'fixed-html'
					},
					isWatched: false,
					watchable: true,
					replyToId: 'slmuthyr3gzloi2x',
					postId: 'slmuufr0j99j851l',
					isMaxThreadingDepth: false,
					creator: {
						name: 'Admin',
						wiki: 'wiki',
						gender: 'unknown',
						id: 1
					},
					replies: []
				}
			},
			workflowId: 'slmursrfvx65co7d',
			links: [],
			submitted: {
				action: 'view'
			},
			errors: [],
			title: 'Topic:Slmursrfvx65co7d',
			'block-action-template': ''
		};

	/*!
	 * The structure of this test topic:
	 *
	 * Topic with hierarchical posts
	 * - Post #1
	 *   - Reply #1
	 *     - Sub reply #1
	 *       - Sub sub reply #1
	 *     - Sub reply #2
	 *   - Reply #2
	 * - Post #2
	 */

	// Set the stage: Create a topic and add the posts to it
	topicRevisionData = mw.flow.dm.Topic.static.getTopicRevisionFromApi( truncatedApiData, 'slmursrfvx65co7d' );
	topic = new mw.flow.dm.Topic( 'slmursrfvx65co7d', topicRevisionData );
	posts = mw.flow.dm.Post.static.createTopicReplyTree( truncatedApiData, topic.getReplyIds() );
	topic.addItems( posts );

	posts = topic.getItems();
	replies = posts[ 0 ].getItems();
	subreplies = replies[ 0 ].getItems();
	subsubreplies = subreplies[ 0 ].getItems();

	// Posts
	assert.strictEqual( posts.length, 2, 'Two base posts.' );

	// Replies
	assert.strictEqual( replies.length, 2, 'Two replies.' );
	assert.strictEqual( replies[ 0 ].getContent(), 'Reply #1', 'Reply #1 has correct content' );
	assert.strictEqual( replies[ 1 ].getContent(), 'Reply #2', 'Reply #2 has correct content' );

	// Sub replies
	assert.strictEqual( subreplies.length, 2, 'Two sub replies.' );
	assert.strictEqual( subreplies[ 0 ].getContent(), 'Sub reply #1', 'Sub reply #1 has correct content' );
	assert.strictEqual( subreplies[ 1 ].getContent(), 'Sub reply #2', 'Sub reply #2 has correct content' );

	// Sub sub reply
	assert.strictEqual( subsubreplies.length, 1, 'One sub-sub replies.' );
	assert.strictEqual( subsubreplies[ 0 ].getContent(), 'Sub sub reply #1', 'Sub-sub reply #1 has correct content' );

	// Workflow Ids
	assert.strictEqual( posts[ 0 ].getWorkflowId(), topic.getId(), 'Posts: WorkflowId is topic Id' );
	assert.strictEqual( replies[ 0 ].getWorkflowId(), topic.getId(), 'Replies: WorkflowId is topic Id' );
	assert.strictEqual( subreplies[ 0 ].getWorkflowId(), topic.getId(), 'Sub replies: WorkflowId is topic Id' );
} );
