/*!
 * @todo break this down into mixins for each callback section (eg. post actions, read topics)
 */

( function () {
	/**
	 * Binds API events to FlowBoardComponent
	 *
	 * @class
	 * @extends FlowComponent
	 * @constructor
	 * @param {jQuery} $container
	 */
	function FlowBoardComponentApiEventsMixin() {
		// Bind event callbacks
		this.bindNodeHandlers( FlowBoardComponentApiEventsMixin.UI.events );
	}
	OO.initClass( FlowBoardComponentApiEventsMixin );

	/** Event handlers are stored here, but are registered in the constructor */
	FlowBoardComponentApiEventsMixin.UI = {
		events: {
			globalApiPreHandlers: {},
			apiPreHandlers: {},
			apiHandlers: {}
		}
	};

	//
	// pre-api callback handlers, to do things before the API call
	//

	/** @class FlowBoardComponentApiEventsMixin.UI.events.globalApiPreHandlers */

	/**
	 * When presented with an error conflict, the conflicting content can
	 * subsequently be re-submitted (to overwrite the conflicting content)
	 * This will prepare the data-to-be-submitted so that the override is
	 * submitted against the most current revision ID.
	 *
	 * @param {Event} event
	 * @param {Object} info
	 * @param {Object} queryMap
	 * @return {Object}
	 */
	FlowBoardComponentApiEventsMixin.UI.events.globalApiPreHandlers.prepareEditConflict = function ( event, info, queryMap ) {
		var $form = $( this ).closest( 'form' ),
			prevRevisionId = $form.data( 'flow-prev-revision' );

		if ( !prevRevisionId ) {
			return queryMap;
		}

		// Get rid of the temp-saved new revision ID
		$form.removeData( 'flow-prev-revision' );

		/*
		 * This is prev_revision in "generic" form. Each Flow API has its
		 * own unique prefix, which (in FlowApi.prototype.getQueryMap) will
		 * be properly applied for the respective API call; e.g.
		 * epprev_revision (for edit post)
		 */
		return $.extend( {}, queryMap, {
			flow_prev_revision: prevRevisionId
		} );
	};

	/**
	 * Adjusts query params to use global watch action, and specifies it should use a watch token.
	 *
	 * @param {Event} event
	 * @param {Object} info
	 * @param {Object} queryMap
	 * @return {Object}
	 */
	FlowBoardComponentApiEventsMixin.UI.events.apiPreHandlers.watchItem = function ( event, info, queryMap ) {
		var params = {
			action: 'watch',
			titles: queryMap.page,
			_internal: {
				tokenType: 'watch'
			}
		};
		if ( queryMap.submodule === 'unwatch' ) {
			params.unwatch = 1;
		}

		return params;
	};

	//
	// api callback handlers
	//

	/** @class FlowBoardComponentApiEventsMixin.UI.events.apiHandlers */

	/**
	 * On complete board reprocessing through view-topiclist (eg. change topic sort order), re-render any given blocks.
	 *
	 * @param {Object} info
	 * @param {string} info.status "done" or "fail"
	 * @param {jQuery} info.$target
	 * @param {Object} data
	 * @param {jQuery.jqXHR} jqxhr
	 * @return {jQuery.Promise}
	 */
	FlowBoardComponentApiEventsMixin.UI.events.apiHandlers.board = function ( info, data ) {
		var $rendered,
			flowBoard = info.component,
			dfd = $.Deferred();

		if ( info.status !== 'done' ) {
			// Error will be displayed by default, nothing else to wrap up
			return dfd.resolve().promise();
		}

		$rendered = $(
			flowBoard.constructor.static.TemplateEngine.processTemplateGetFragment(
				'flow_block_loop',
				{ blocks: data.flow[ 'view-topiclist' ].result }
			)
		).children();

		// Run this on a short timeout so that the other board handler in FlowBoardComponentLoadMoreFeatureMixin can run
		// TODO: Using a timeout doesn't seem like the right way to do this.
		setTimeout( function () {
			// Reinitialize the whole board with these nodes
			flowBoard.reinitializeContainer( $rendered );
			dfd.resolve();
		}, 50 );

		return dfd.promise();
	};

	/**
	 * @param {Object} info
	 * @param {string} info.status "done" or "fail"
	 * @param {jQuery} info.$target
	 * @param {Object} data
	 * @param {jQuery.jqXHR} jqxhr
	 * @return {jQuery.Promise}
	 */
	FlowBoardComponentApiEventsMixin.UI.events.apiHandlers.submitTopicTitle = function ( info, data ) {
		if ( info.status !== 'done' ) {
			// Error will be displayed by default & edit conflict handled, nothing else to wrap up
			return $.Deferred().resolve().promise();
		}

		return _flowBoardComponentRefreshTopic(
			info.$target,
			data.flow[ 'edit-title' ].workflow,
			'.flow-topic-titlebar'
		);
	};

	/**
	 * @param {Object} info
	 * @param {string} info.status "done" or "fail"
	 * @param {jQuery} info.$target
	 * @param {Object} data
	 * @param {jQuery.jqXHR} jqxhr
	 * @return {jQuery.Promise}
	 */
	FlowBoardComponentApiEventsMixin.UI.events.apiHandlers.watchItem = function ( info, data ) {
		var watchUrl, unwatchUrl,
			watchType, watchLinkTemplate, $newLink,
			$target = $( this ),
			$tooltipTarget = $target.closest( '.flow-watch-link' ),
			flowBoard = mw.flow.getPrototypeMethod( 'board', 'getInstanceByElement' )( $tooltipTarget ),
			isWatched = false,
			url = $( this ).prop( 'href' ),
			links = {};

		if ( info.status !== 'done' ) {
			// Error will be displayed by default, nothing else to wrap up
			return $.Deferred().resolve().promise();
		}

		if ( $tooltipTarget.is( '.flow-topic-watchlist' ) ) {
			watchType = 'topic';
			watchLinkTemplate = 'flow_topic_titlebar_watch.partial';
		}

		if ( data.watch[ 0 ].watched !== undefined ) {
			unwatchUrl = url.replace( 'watch', 'unwatch' );
			watchUrl = url;
			isWatched = true;
		} else {
			watchUrl = url.replace( 'unwatch', 'watch' );
			unwatchUrl = url;
		}
		links[ 'unwatch-' + watchType ] = { url: unwatchUrl };
		links[ 'watch-' + watchType ] = { url: watchUrl };

		// Render new icon
		// This will hide any tooltips if present
		$newLink = $(
			flowBoard.constructor.static.TemplateEngine.processTemplateGetFragment(
				watchLinkTemplate,
				{
					isWatched: isWatched,
					links: links,
					watchable: true
				}
			)
		).children();
		$tooltipTarget.replaceWith( $newLink );

		if ( data.watch[ 0 ].watched !== undefined ) {
			// Successful watch: show tooltip
			flowBoard.emitWithReturn(
				'showSubscribedTooltip',
				$newLink.find( '.mw-ui-anchor' ),
				watchType,
				$newLink.css( 'direction' ) === 'ltr' ? 'left' : 'right'
			);
		}

		return $.Deferred().resolve().promise();
	};

	/**
	 * Callback from the topic moderation dialog.
	 */
	FlowBoardComponentApiEventsMixin.UI.events.apiHandlers.moderateTopic = _genModerateHandler(
		'moderate-topic',
		function ( flowBoard, revision ) {
			var $replacement, $target;

			if ( !revision.isModerated ) {
				return;
			}

			$target = flowBoard.$container.find( '#flow-topic-' + revision.postId );
			if ( flowBoard.constructor.static.inTopicNamespace( $target ) ) {
				return;
			}

			$replacement = $( $.parseHTML( mw.flow.TemplateEngine.processTemplate(
				'flow_moderate_topic_confirmation.partial',
				revision
			) ) );

			$target.replaceWith( $replacement );
			flowBoard.emitWithReturn( 'makeContentInteractive', $replacement );
		}
	);

	/**
	 * Callback from the post moderation dialog.
	 */
	FlowBoardComponentApiEventsMixin.UI.events.apiHandlers.moderatePost = _genModerateHandler(
		'moderate-post',
		function ( flowBoard, revision ) {
			var $replacement, $target;

			if ( !revision.isModerated ) {
				return;
			}

			$replacement = $( $.parseHTML( flowBoard.constructor.static.TemplateEngine.processTemplate(
				'flow_moderate_post_confirmation.partial',
				revision
			) ) );

			$target = flowBoard.$container.find( '#flow-post-' + revision.postId + ' > .flow-post-main' );
			$target.replaceWith( $replacement );

			flowBoard.emitWithReturn( 'makeContentInteractive', $replacement );
		}
	);

	//
	// Private functions
	//

	/** @class FlowBoardComponentApiEventsMixin */

	/**
	 * Generate a moderation handler callback
	 *
	 * @private
	 * @param {string} action Action to expect in api response
	 * @param {Function} successCallback Method to call on api success
	 * @return {Function} Callback processing the response after submit of a moderation form
	 * @return {Object} return.info `{status: done|fail, $target: jQuery}`
	 * @return {Object} return.data
	 * @return {jQuery.jqXHR} return.jqxhr
	 * @return {jQuery.Promise} return.return
	 */
	function _genModerateHandler( action, successCallback ) {
		return function ( info, data ) {
			var $form, revisionId, $target, flowBoard,
				$this = $( this );

			if ( info.status !== 'done' ) {
				// Error will be displayed by default, nothing else to wrap up
				return $.Deferred().resolve().promise();
			}

			$form = $this.closest( 'form' );
			revisionId = data.flow[ action ].committed.topic[ 'post-revision-id' ];
			$target = $form.data( 'flow-dialog-owner' ) || $form;
			flowBoard = mw.flow.getPrototypeMethod( 'board', 'getInstanceByElement' )( $this );

			// @todo: add 3rd argument (target selector); there's no need to refresh entire topic if only post was moderated
			return _flowBoardComponentRefreshTopic( $target, data.flow[ action ].workflow )
				.done( function ( result ) {
					successCallback( flowBoard, result.flow[ 'view-topic' ].result.topic.revisions[ revisionId ] );
				} )
				.done( function () {
					// we're done here, close moderation pop-up
					flowBoard.emitWithReturn( 'cancelForm', $form );
				} );
		};
	}

	/**
	 * Refreshes (part of) a topic.
	 *
	 * @private
	 * @param  {jQuery} $targetElement An element in the topic.
	 * @param  {string} workflowId     Plain object containing the API response to build from.
	 * @param  {string} [selector]     Select specific element to replace
	 * @return {jQuery.Promise}
	 */
	function _flowBoardComponentRefreshTopic( $targetElement, workflowId, selector ) {
		var $target = $targetElement.closest( '.flow-topic' ),
			flowBoard = mw.flow.getPrototypeMethod( 'board', 'getInstanceByElement' )( $targetElement );

		$targetElement.addClass( 'flow-api-inprogress' );
		return flowBoard.Api.apiCall( {
			action: 'flow',
			submodule: 'view-topic',
			// Flow topic title, in Topic:<topicId> format (2600 is topic namespace id)
			page: ( new mw.Title( workflowId, 2600 ) ).getPrefixedDb()
		} ).done( function ( result ) {
			// Update view of the full topic
			var $replacement = $( flowBoard.constructor.static.TemplateEngine.processTemplateGetFragment(
				'flow_topiclist_loop.partial',
				result.flow[ 'view-topic' ].result.topic
			) ).children();

			if ( selector ) {
				$replacement = $replacement.find( selector );
				$target = $target.find( selector );
			}

			$target.replaceWith( $replacement );
			// Run loadHandlers
			flowBoard.emitWithReturn( 'makeContentInteractive', $replacement );

			// make new topic and $element accessible to downstream handlers
			result.$topic = $replacement;
			result.topic = result.flow[ 'view-topic' ].result.topic;

			// HACK: Emit an event here so that the flow data model can update
			// itself based on the API response
			flowBoard.emit( 'refreshTopic', workflowId, result );
		} ).fail( function ( code, result ) {
			var errorMsg = flowBoard.constructor.static.getApiErrorMessage( code, result );
			errorMsg = mw.msg( 'flow-error-external', errorMsg );

			flowBoard.emitWithReturn( 'removeError', $target );
			flowBoard.emitWithReturn( 'showError', $target, errorMsg );
		} ).always( function () {
			$targetElement.removeClass( 'flow-api-inprogress' );
		} );
	}

	// HACK expose this so flow-initialize.js can rerender topics when it needs to
	FlowBoardComponentApiEventsMixin.prototype.flowBoardComponentRefreshTopic = _flowBoardComponentRefreshTopic;

	// Mixin to FlowBoardComponent
	mw.flow.mixinComponent( 'board', FlowBoardComponentApiEventsMixin );
}() );
