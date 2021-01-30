( function () {
	/**
	 * Resource Provider object.
	 *
	 * @class
	 *
	 * @constructor
	 * @param {string} page Full page name with its namespace;
	 *  for example: "User_talk:Foo"
	 * @param {Object} [config] Configuration options
	 * @cfg {Object} [currentRevision] Current revision Id. Mostly used
	 *  for edit conflict check.
	 * @cfg {Object} [apiConstructorParams] Parameters for mw.Api()
	 * @cfg {Object} [requestParams] Parameters for the request
	 */
	mw.flow.dm.APIHandler = function FlowDmAPIHandler( page, config ) {
		config = config || {};

		this.apiConstructorParams = $.extend( {
			ajax: {
				timeout: 5 * 1000, // 5 seconds
				cache: false
			}
		}, config.apiConstructorParams );

		this.page = page;
		this.setCurrentRevision( config.currentRevision );

		this.requestParams = $.extend( {
			action: 'flow',
			uselang: mw.config.get( 'wgUserLanguage' )
		}, config.requestParams );
	};

	OO.initClass( mw.flow.dm.APIHandler );

	/**
	 * Set the current revision Id. This is mostly used for edit actions, to check
	 * for edit conflicts.
	 *
	 * @param {string} revisionId Current revision id
	 */
	mw.flow.dm.APIHandler.prototype.setCurrentRevision = function ( revisionId ) {
		this.currentRevision = revisionId;
	};

	/**
	 * General get request
	 *
	 * @param {string} submodule The requested submodule
	 * @param {Object} requestParams API request parameters
	 * @return {jQuery.Promise} Promise that is resolved when the API request
	 *  is done, with the API result.
	 */
	mw.flow.dm.APIHandler.prototype.get = function ( submodule, requestParams ) {
		var xhr,
			params = $.extend( { submodule: submodule }, this.requestParams, requestParams );

		xhr = ( new mw.Api() ).get( params );
		return xhr
			.then( function ( data ) {
				return data.flow[ submodule ].result;
			} )
			.promise( { abort: xhr.abort } );
	};

	/**
	 * Post with edit token request
	 *
	 * @param {string} submodule The requested submodule
	 * @param {Object} requestParams API request parameters
	 * @return {jQuery.Promise} Promise that is resolved when the API request
	 *  is done, with the API result.
	 */
	mw.flow.dm.APIHandler.prototype.postEdit = function ( submodule, requestParams ) {
		var api = new mw.Api(),
			params = $.extend( { submodule: submodule }, this.requestParams, requestParams );

		return api.postWithToken( 'csrf', api.assertCurrentUser( params ) );
	};

	/**
	 * Get page categories. This will recursively continue to fetch results
	 * until all page categories are fetched.
	 *
	 * @return {jQuery.Promise} Promise that is resolved when the API request
	 *  is done, with the API result.
	 */
	mw.flow.dm.APIHandler.prototype.getCategories = function () {
		var params = {
			action: 'query',
			titles: this.page,
			generator: 'categories',
			gcllimit: 'max'
		};

		return ( new mw.Api() ).get( $.extend( {}, this.requestParams, params ) )
			.then( function ( response ) {
				return OO.getProp( response, 'query', 'pages' );
			} );
	};

	/**
	 * Gets the reason the page was protected
	 *
	 * @return {jQuery.Promise}
	 * @return {Function} return.done
	 * @return {string} return.done.reason Reason, as HTML
	 */
	mw.flow.dm.APIHandler.prototype.getProtectionReason = function () {
		var params = {
			action: 'query',
			list: 'logevents',
			leprop: 'parsedcomment',
			leaction: 'protect/protect',
			letitle: this.page,
			lelimit: 1
		};

		return ( new mw.Api() ).get( $.extend( {}, this.requestParams, params ) )
			.then( function ( response ) {
				return OO.getProp( response, 'query', 'logevents', 0, 'parsedcomment' );
			} );
	};

	/**
	 * Send a request to get topic list
	 *
	 * @param {string} orderType Sort order type, 'newest' or 'updated'
	 * @param {Object} config Configuration
	 * @cfg {string} [offset] Topic offset id or timestamp offset
	 *  if given, the topic list will be returned with topics that
	 *  are after (and including) the topic with the given uuid or
	 *  after the given timestamp.
	 * @cfg {string} [toconly] Receive a stripped reply that fits the ToC. For more information
	 *  see 'toconly' in the API documentation.
	 * @return {jQuery.Promise} Promise that is resolved with the topiclist response
	 */
	mw.flow.dm.APIHandler.prototype.getTopicList = function ( orderType, config ) {
		var params = {
			page: this.page
		};

		config = config || {};

		params.vtltoconly = !!config.toconly;
		params.vtllimit = config.toconly ? 50 : 10;
		params.vtlsortby = orderType;

		if ( orderType === 'newest' ) {
			params[ 'vtloffset-id' ] = config.offset;
		} else if ( orderType === 'updated' ) {
			// Translate api/object-given offset to MW offset for the API request
			params.vtloffset = moment.utc( config.offset ).format( 'YYYYMMDDHHmmss' );
		}

		return this.get( 'view-topiclist', params )
			.then( function ( data ) {
				return data.topiclist;
			} );
	};

	/**
	 * Adds CAPTCHA to parameters if applicable
	 *
	 * @param {Object} params API Parameters to add CAPTCHA information to
	 * @param {Object|null} captcha CAPTCHA object
	 * @param {string} captcha.id CAPTCHA ID
	 * @param {string} captcha.answer CAPTCHA answer (user-provided)
	 */
	mw.flow.dm.APIHandler.prototype.addCaptcha = function ( params, captcha ) {
		// TODO: Find a better way to plug this in.
		if ( captcha ) {
			params.wpCaptchaId = captcha.id;
			params.wpCaptchaWord = captcha.answer;
		}
	};

	/**
	 * Get topic title from topic id
	 *
	 * @param {string} topicId Topic id
	 * @return {string} Topic title
	 */
	mw.flow.dm.APIHandler.prototype.getTopicTitle = function ( topicId ) {
		return ( new mw.Title( topicId, 2600 ) ).getPrefixedDb();
	};

	/**
	 * Send an edit request to the API to save a reply.
	 *
	 * @param {string} topicId Topic Id
	 * @param {string} replyTo The parent of this reply
	 * @param {string} content Reply content
	 * @param {string} format Reply content format
	 * @param {Object} [captcha] CAPTCHA information
	 * @return {jQuery.Promise} Promise that is resolved with the id of the workflow
	 *  that this reply belongs to
	 */
	mw.flow.dm.APIHandler.prototype.saveReply = function ( topicId, replyTo, content, format, captcha ) {
		var api = new mw.Api(),
			params = {
				action: 'flow',
				submodule: 'reply',
				page: 'Topic:' + topicId,
				repreplyTo: replyTo,
				repcontent: content,
				repformat: format
			};

		params = api.assertCurrentUser( params );
		this.addCaptcha( params, captcha );

		return api.postWithToken( 'csrf', $.extend( {}, this.requestParams, params ) )
			.then( function ( data ) {
				return data.flow.reply.workflow;
			} );
	};

	/**
	 * Save new topic in the board
	 *
	 * @param {string} title Topic title
	 * @param {string} content Topic content
	 * @param {string} format Content format
	 * @param {Object} [captcha] CAPTCHA information
	 * @return {jQuery.Promise} Promise that is resolved with the new topic id
	 */
	mw.flow.dm.APIHandler.prototype.saveNewTopic = function ( title, content, format, captcha ) {
		var api = new mw.Api(),
			params = {
				submodule: 'new-topic',
				page: this.page,
				nttopic: title,
				ntcontent: content,
				ntformat: format
			};

		params = api.assertCurrentUser( params );
		this.addCaptcha( params, captcha );

		return api.postWithToken( 'csrf', $.extend( {}, this.requestParams, params ) )
			.then( function ( response ) {
				return OO.getProp( response.flow, 'new-topic', 'committed', 'topiclist', 'topic-id' );
			} );
	};

	/**
	 * Get the board description from the API.
	 *
	 * @param {string} [contentFormat='fixed-html'] Content format for board description
	 * @return {jQuery.Promise} Promise that is resolved with the header revision data
	 */
	mw.flow.dm.APIHandler.prototype.getDescription = function ( contentFormat ) {
		var params = {
			page: this.page,
			vhformat: contentFormat || 'fixed-html'
		};

		return this.get( 'view-header', params )
			.then( function ( data ) {
				return data.header.revision;
			} );
	};

	/**
	 * Save header information.
	 *
	 * @param {string} content Header content
	 * @param {string} format Content format for board description
	 * @param {Object} [captcha] CAPTCHA information
	 * @return {jQuery.Promise} Promise that is resolved with the saved header revision id
	 */
	mw.flow.dm.APIHandler.prototype.saveDescription = function ( content, format, captcha ) {
		var xhr,
			params = {
				page: this.page,
				ehcontent: content,
				ehformat: format,
				ehprev_revision: this.currentRevision
			};

		this.addCaptcha( params, captcha );

		xhr = this.postEdit( 'edit-header', params )
			.then( function ( data ) {
				return OO.getProp( data.flow, 'edit-header', 'committed', 'header', 'header-revision-id' );
			} );

		return xhr.promise( { abort: xhr.abort } );
	};

	/**
	 * Get a post.
	 *
	 * @param {string} topicId
	 * @param {string} postId
	 * @param {string} format
	 * @return {jQuery.Promise} Promise that is resolved with the post revision data
	 */
	mw.flow.dm.APIHandler.prototype.getPost = function ( topicId, postId, format ) {
		var params = {
			page: this.getTopicTitle( topicId ),
			vppostId: postId,
			vpformat: format || 'html'
		};

		return this.get( 'view-post', params )
			.then( function ( data ) {
				return data.topic.revisions[ data.topic.posts[ postId ] ];
			} );
	};

	/**
	 * Save a post.
	 *
	 * @param {string} topicId
	 * @param {string} postId
	 * @param {string} content
	 * @param {string} format
	 * @param {string} [captcha] CAPTCHA information
	 * @return {jQuery.Promise} Promise that is resolved with the saved post revision id
	 */
	mw.flow.dm.APIHandler.prototype.savePost = function ( topicId, postId, content, format, captcha ) {
		var params = {
			page: this.getTopicTitle( topicId ),
			epcontent: content,
			epformat: format,
			epprev_revision: this.currentRevision,
			eppostId: postId
		};

		this.addCaptcha( params, captcha );

		return this.postEdit( 'edit-post', params )
			.then( function ( data ) {
				return OO.getProp( data.flow, 'edit-post', 'workflow' );
			} );
	};

	/**
	 * Get a topic summary.
	 *
	 * @param {string} topicId
	 * @param {string} format
	 * @return {jQuery.Promise} Promise that is resolved with the topic summary revision
	 */
	mw.flow.dm.APIHandler.prototype.getTopicSummary = function ( topicId, format ) {
		var params = {
			page: this.getTopicTitle( topicId ),
			vtsformat: format || 'html'
		};

		return this.get( 'view-topic-summary', params )
			.then( function ( data ) {
				return data.topicsummary.revision;
			} );
	};

	/**
	 * Save a topic summary.
	 *
	 * @param {string} topicId
	 * @param {string} content
	 * @param {string} format
	 * @param {Object} captcha
	 * @return {jQuery.Promise} Promise that is resolved with workflow id
	 */
	mw.flow.dm.APIHandler.prototype.saveTopicSummary = function ( topicId, content, format, captcha ) {
		var params = {
			page: this.getTopicTitle( topicId ),
			etssummary: content,
			etsformat: format,
			etsprev_revision: this.currentRevision
		};

		this.addCaptcha( params, captcha );

		return this.postEdit( 'edit-topic-summary', params )
			.then( function ( data ) {
				return OO.getProp( data.flow, 'edit-topic-summary', 'workflow' );
			} );
	};

	/**
	 * Save a topic title.
	 *
	 * @param {string} topicId
	 * @param {string} content
	 * @param {Object} captcha
	 * @return {jQuery.Promise} Promise that is resolved with workflow id
	 */
	mw.flow.dm.APIHandler.prototype.saveTopicTitle = function ( topicId, content, captcha ) {
		var params = {
			page: this.getTopicTitle( topicId ),
			etcontent: content,
			etprev_revision: this.currentRevision
		};

		this.addCaptcha( params, captcha );

		return this.postEdit( 'edit-title', params )
			.then( function ( data ) {
				return OO.getProp( data.flow, 'edit-title', 'workflow' );
			} );
	};

	/**
	 * Execute the 'lock-topic' moderation action against a topic. Can be used to resolve or reopen a topic.
	 *
	 * @param {string} topicId Id of the topic to moderate
	 * @param {string} moderationState Can be 'lock' or 'unlock'
	 * @param {string} reasonMsgKey Message key for the moderation reason
	 * @return {jQuery.Promise} Promise that is resolved with workflow id
	 */
	mw.flow.dm.APIHandler.prototype.lockTopic = function ( topicId, moderationState, reasonMsgKey ) {
		var params = {
			page: this.getTopicTitle( topicId ),
			cotmoderationState: moderationState,
			// The following messages are used here:
			// * flow-rev-message-lock-topic-reason
			// * flow-rev-message-restore-topic-reason
			cotreason: mw.msg( reasonMsgKey )
		};

		return this.postEdit( 'lock-topic', params )
			.then( function ( data ) {
				return OO.getProp( data.flow, 'lock-topic', 'workflow' );
			} );
	};

	/**
	 * Resolve a topic.
	 *
	 * @param {string} topicId
	 * @return {jQuery.Promise} Promise that is resolved with workflow id
	 */
	mw.flow.dm.APIHandler.prototype.resolveTopic = function ( topicId ) {
		return this.lockTopic( topicId, 'lock', 'flow-rev-message-lock-topic-reason' );
	};

	/**
	 * Reopen a topic.
	 *
	 * @param {string} topicId
	 * @return {jQuery.Promise} Promise that is resolved with workflow id
	 */
	mw.flow.dm.APIHandler.prototype.reopenTopic = function ( topicId ) {
		return this.lockTopic( topicId, 'unlock', 'flow-rev-message-restore-topic-reason' );
	};

}() );
