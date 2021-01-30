( function () {
	/**
	 * Flow Post
	 *
	 * @class
	 * @extends mw.flow.dm.ModeratedRevisionedContent
	 * @mixins mw.flow.dm.List
	 *
	 * @constructor
	 * @param {string} id Post Id
	 * @param {Object} revisionData API data to build post with
	 * @param {Object} [config] Configuration options
	 */
	mw.flow.dm.Post = function mwFlowDmPost( id, revisionData, config ) {
		config = config || {};

		// Parent constructor
		mw.flow.dm.Post.super.call( this, config );

		// Mixin constructor
		mw.flow.dm.List.call( this );

		this.setId( id );
		this.populate( revisionData );

		// Configuration
		this.highlighted = !!config.highlighted;

		// Store comparable hash
		this.storeComparableHash();
	};

	/* Initialization */

	OO.inheritClass( mw.flow.dm.Post, mw.flow.dm.ModeratedRevisionedContent );
	OO.mixinClass( mw.flow.dm.Post, mw.flow.dm.List );

	/* Static methods */

	/**
	 * Get the post revision by its topic Id from the topiclist
	 * api response.
	 *
	 * @param {Object} topiclist API data for topiclist
	 * @param {string} postId Post id
	 * @return {Object} Revision data
	 */
	mw.flow.dm.Post.static.getPostRevision = function ( topiclist, postId ) {
		var pid = OO.getProp( topiclist, 'posts', postId );

		if ( pid[ 0 ] ) {
			return topiclist.revisions[ pid[ 0 ] ];
		}
		return {};
	};

	/**
	 * Create a hierarchical construct of replies based on the parent reply list.
	 *
	 * @param {Object} topiclist API response for topic list
	 * @param {string[]} parentReplyIds Ids of the parent posts
	 * @return {mw.flow.dm.Post[]} Array of posts
	 */
	mw.flow.dm.Post.static.createTopicReplyTree = function ( topiclist, parentReplyIds ) {
		var i, len, post, postRevision, replies,
			result = [];

		for ( i = 0, len = parentReplyIds.length; i < len; i++ ) {
			postRevision = mw.flow.dm.Post.static.getPostRevision( topiclist, parentReplyIds[ i ] );
			post = new mw.flow.dm.Post( parentReplyIds[ i ], postRevision );
			// Populate sub-posts
			replies = this.createTopicReplyTree( topiclist, post.getReplyIds() );
			post.addItems( replies );
			result.push( post );
		}
		return result;
	};

	/* Methods */

	/**
	 * @inheritdoc
	 */
	mw.flow.dm.Post.prototype.populate = function ( data ) {
		// Store reply Ids
		this.replyIds = data.replies || [];

		// Parent method
		mw.flow.dm.Post.super.prototype.populate.apply( this, arguments );
	};

	/**
	 * Get an array of post ids attached to this post
	 *
	 * @return {string[]} Post reply ids
	 */
	mw.flow.dm.Post.prototype.getReplyIds = function () {
		return this.replyIds;
	};

}() );
