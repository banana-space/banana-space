( function () {
	/**
	 * Flow Topic
	 *
	 * @class
	 * @extends mw.flow.dm.ModeratedRevisionedContent
	 * @mixins mw.flow.dm.List
	 *
	 * @constructor
	 * @param {string} id Topic Id
	 * @param {Object} revisionData API data to build topic with
	 * @param {Object} [config] Configuration options
	 */
	mw.flow.dm.Topic = function mwFlowDmTopic( id, revisionData, config ) {
		config = config || {};

		// Parent constructor
		mw.flow.dm.Topic.super.call( this, config );

		// Mixin constructor
		mw.flow.dm.List.call( this );

		this.setId( id );
		this.populate( revisionData );

		// Configuration
		this.highlighted = !!config.highlighted;
		this.stub = true;

		// Store comparable hash
		this.storeComparableHash();
	};

	/* Initialization */

	OO.inheritClass( mw.flow.dm.Topic, mw.flow.dm.ModeratedRevisionedContent );
	OO.mixinClass( mw.flow.dm.Topic, mw.flow.dm.List );

	/* Events */

	/**
	 * Change of topic summary
	 *
	 * @event summaryChange
	 * @param {string} summary New summary
	 */

	/* Static methods */

	/**
	 * Get the topic revision connected to the topic id from the
	 * topiclist api response. This connects the topic id to the
	 * post id and then returns the specific available revision.
	 *
	 * @param {Object} topiclist API data for topiclist
	 * @param {string} topicId Topic id
	 * @return {Object} Revision data
	 */
	mw.flow.dm.Topic.static.getTopicRevisionFromApi = function ( topiclist, topicId ) {
		var revisionId = topiclist.posts[ topicId ] && topiclist.posts[ topicId ][ 0 ];

		return topiclist.revisions[ revisionId ];
	};

	/**
	 * Get an array of topic objects from a topiclist api response.
	 *
	 * @param {Object} topiclist API data for topiclist
	 * @param {string} topicId Topic id
	 * @return {mw.flow.dm.Topic[]} Array of topic models
	 */
	mw.flow.dm.Topic.static.extractTopicsFromAPI = function ( topiclist ) {
		var i, len, topicId,
			topics = [];

		for ( i = 0, len = topiclist.roots.length; i < len; i++ ) {
			topicId = topiclist.roots[ i ];
			topics.push(
				new mw.flow.dm.Topic(
					topicId,
					this.getTopicRevisionFromApi( topiclist, topicId )
				)
			);
		}

		return topics;
	};

	/* Methods */

	/**
	 * Get a hash object representing the current state
	 * of the Topic
	 *
	 * @return {Object} Hash object
	 */
	mw.flow.dm.Topic.prototype.getHashObject = function () {
		return $.extend(
			{
				stub: this.isStub(),
				summary: this.getSummary()
			},
			// Parent
			mw.flow.dm.Topic.super.prototype.getHashObject.apply( this, arguments )
		);
	};

	/**
	 * Populate the topic information from API data.
	 *
	 * @param {Object} data API data
	 */
	mw.flow.dm.Topic.prototype.populate = function ( data ) {
		this.summary = OO.getProp( data, 'summary', 'revision', 'content' );

		// Store reply Ids
		this.replyIds = data.replies || [];

		// Parent method
		mw.flow.dm.Topic.super.prototype.populate.apply( this, arguments );

		if ( data.replies !== undefined ) {
			this.unStub();
		}
	};

	/**
	 * Get an array of post ids attached to this topic
	 *
	 * @return {string[]} Post reply ids
	 */
	mw.flow.dm.Topic.prototype.getReplyIds = function () {
		return this.replyIds;
	};

	/**
	 * Check if a topic is a stub
	 *
	 * @return {boolean} Topic is a stub
	 */
	mw.flow.dm.Topic.prototype.isStub = function () {
		return this.stub;
	};

	/**
	 * Unstub a topic when all available information exists on it
	 *
	 * @private
	 */
	mw.flow.dm.Topic.prototype.unStub = function () {
		this.stub = false;
	};

	/**
	 * Get the topic summary
	 *
	 * @return {string} Topic summary
	 */
	mw.flow.dm.Topic.prototype.getSummary = function () {
		return this.summary;
	};

	/**
	 * Set the topic summary
	 *
	 * @param {string} summary Topic summary
	 * @fires summary
	 */
	mw.flow.dm.Topic.prototype.setSummary = function ( summary ) {
		this.summary = summary;
		this.emit( 'summaryChange', this.summary );
	};

}() );
