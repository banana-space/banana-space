/*!
 * Contains loadMore, jumpToTopic, and topic titles list functionality.
 */

/**
 * @class FlowBoardComponent
 * TODO: Use @-external in JSDoc
 */

( function () {
	/**
	 * Bind UI events and infinite scroll handler for load more and titles list functionality.
	 *
	 * @param {jQuery} $container
	 * @this FlowBoardComponent
	 * @constructor
	 */
	function FlowBoardComponentLoadMoreFeatureMixin() {
		/** Stores a reference to each topic element currently on the page */
		this.renderedTopics = {};
		/** Stores a list of all topics titles by ID */
		this.topicTitlesById = {};
		/** Stores a list of all topic IDs in order */
		this.orderedTopicIds = [];

		this.bindNodeHandlers( FlowBoardComponentLoadMoreFeatureMixin.UI.events );
	}
	OO.initClass( FlowBoardComponentLoadMoreFeatureMixin );

	FlowBoardComponentLoadMoreFeatureMixin.UI = {
		events: {
			apiPreHandlers: {},
			apiHandlers: {},
			loadHandlers: {}
		}
	};

	//
	// Prototype methods
	//

	/**
	 * Scrolls up or down to a specific topic, and loads any topics it needs to.
	 * 1. If topic is rendered, scrolls to it.
	 * 2. Otherwise, we load the topic itself
	 * 3b. When the user scrolls up, we begin loading the topics in between.
	 *
	 * @param {string} topicId
	 */
	function flowBoardComponentLoadMoreFeatureJumpTo( topicId ) {
		var apiParameters,
			flowBoard = this,
			// Scrolls to the given topic, but disables infinite scroll loading while doing so
			_scrollWithoutInfinite = function () {
				var $renderedTopic = flowBoard.renderedTopics[ topicId ];

				if ( $renderedTopic && $renderedTopic.length ) {
					flowBoard.infiniteScrollDisabled = true;

					// Get out of the way of the affixed navigation
					// Not going the full $( '.flow-board-navigation' ).height()
					// because then the load more button (above the new topic)
					// would get in sight and any scroll would fire it
					// eslint-disable-next-line no-jquery/no-global-selector
					$( 'html, body' ).scrollTop( $renderedTopic.offset().top - 20 );

					// Focus on given topic
					$renderedTopic.trigger( 'click' ).trigger( 'focus' );

					/*
					 * Re-enable infinite scroll. Only doing that after a couple
					 * of milliseconds because we've just executed some
					 * scrolling (to the selected topic) and the very last
					 * scroll event may only just still be getting fired.
					 * To prevent an immediate scroll (above the new topic),
					 * let's only re-enable infinite scroll until we're sure
					 * that event has been fired.
					 */
					setTimeout( function () {
						delete flowBoard.infiniteScrollDisabled;
					}, 1 );
				} else {
					flowBoard.debug( 'Rendered topic not found when attempting to scroll!' );
				}
			};

		// 1. Topic is already on the page; just scroll to it
		if ( flowBoard.renderedTopics[ topicId ] ) {
			_scrollWithoutInfinite();
			return;
		}

		// 2a. Topic is not rendered; do we know about this topic ID?
		if ( flowBoard.topicTitlesById[ topicId ] === undefined ) {
			// We don't. Abort!
			flowBoard.debug( 'Unknown topicId', arguments );
			return;
		}

		// 2b. Load that topic and jump to it
		apiParameters = {
			action: 'flow',
			submodule: 'view-topiclist',
			'vtloffset-dir': 'fwd', // @todo support "middle" dir
			'vtlinclude-offset': true,
			vtlsortby: this.topicIdSort
		};

		if ( this.topicIdSort === 'newest' ) {
			apiParameters[ 'vtloffset-id' ] = topicId;
		} else {
			// TODO: It would seem to be safer to pass 'offset-id' for both (what happens
			// if there are two posts at the same timestamp?).  (Also, that would avoid needing
			// the timestamp in the TOC-only API response).  However, currently
			// we must pass 'offset' for 'updated' order to get valid results.

			apiParameters.vtloffset = moment.utc( this.updateTimestampsByTopicId[ topicId ] ).format( 'YYYYMMDDHHmmss' );
		}

		flowBoard.Api.apiCall( apiParameters )
			// TODO: Finish this error handling or remove the empty functions.
			// Remove the load indicator
			.always( function () {
				// @todo support for multiple indicators on same target
				// $target.removeClass( 'flow-api-inprogress' );
				// $this.removeClass( 'flow-api-inprogress' );
			} )
			// On success, render the topic
			.done( function ( data ) {
				_flowBoardComponentLoadMoreFeatureRenderTopics(
					flowBoard,
					data.flow[ 'view-topiclist' ].result.topiclist,
					false,
					null,
					'',
					'',
					'flow_topiclist_loop.partial' // @todo clean up the way we pass these 3 params ^
				);

				_scrollWithoutInfinite();
			} )
			// On fail, render an error
			.fail( function ( code ) {
				flowBoard.debug( true, 'Failed to load topics: ' + code );
				// Failed fetching the new data to be displayed.
				// @todo render the error at topic position and scroll to it
				// @todo how do we render this?
				// $target = ????
				// flowBoard.emitWithReturn( 'removeError', $target );
				// var errorMsg = flowBoard.constructor.static.getApiErrorMessage( code, result );
				// errorMsg = mw.msg( '????', errorMsg );
				// flowBoard.emitWithReturn( 'showError', $target, errorMsg );
			} );
	}
	FlowBoardComponentLoadMoreFeatureMixin.prototype.jumpToTopic = flowBoardComponentLoadMoreFeatureJumpTo;

	//
	// API pre-handlers
	//

	/**
	 * On before board reloading (eg. change sort).
	 * This method only clears the storage in preparation for it to be reloaded.
	 *
	 * @param {Event} event
	 * @param {Object} info
	 * @param {jQuery} info.$target
	 * @param {Object} info.queryMap
	 * @param {FlowBoardComponent} info.component
	 */
	function flowBoardComponentLoadMoreFeatureBoardApiPreHandler( event, info ) {
		// Backup the topic data
		info.component.renderedTopicsBackup = info.component.renderedTopics;
		info.component.topicTitlesByIdBackup = info.component.topicTitlesById;
		// Reset the topic data
		info.component.renderedTopics = {};
		info.component.topicTitlesById = {};
	}
	FlowBoardComponentLoadMoreFeatureMixin.UI.events.apiPreHandlers.board = flowBoardComponentLoadMoreFeatureBoardApiPreHandler;

	//
	// API callback handlers
	//

	/**
	 * On failed board reloading (eg. change sort), restore old data.
	 *
	 * @param {Object} info
	 * @param {string} info.status "done" or "fail"
	 * @param {jQuery} info.$target
	 * @param {FlowBoardComponent} info.component
	 * @param {Object} data
	 * @param {jQuery.jqXHR} jqxhr
	 */
	function flowBoardComponentLoadMoreFeatureBoardApiCallback( info ) {
		if ( info.status !== 'done' ) {
			// Failed; restore the topic data
			info.component.renderedTopics = info.component.renderedTopicsBackup;
			info.component.topicTitlesById = info.component.topicTitlesByIdBackup;
		}

		// Delete the backups
		delete info.component.renderedTopicsBackup;
		delete info.component.topicTitlesByIdBackup;
	}
	FlowBoardComponentLoadMoreFeatureMixin.UI.events.apiHandlers.board = flowBoardComponentLoadMoreFeatureBoardApiCallback;

	/**
	 * Loads more content
	 *
	 * @param {Object} info
	 * @param {string} info.status "done" or "fail"
	 * @param {jQuery} info.$target
	 * @param {FlowBoardComponent} info.component
	 * @param {Object} data
	 * @param {jQuery.jqXHR} jqxhr
	 * @return {jQuery.Promise}
	 */
	function flowBoardComponentLoadMoreFeatureTopicsApiCallback( info, data ) {
		var scrollTarget,
			$scrollTarget,
			$scrollContainer,
			topicsData,
			readingTopicPosition,
			$this = $( this ),
			$target = info.$target,
			flowBoard = info.component;

		if ( info.status !== 'done' ) {
			// Error will be displayed by default, nothing else to wrap up
			return $.Deferred().resolve().promise();
		}

		scrollTarget = $this.data( 'flow-scroll-target' );
		$scrollContainer = $.findWithParent( $this, $this.data( 'flow-scroll-container' ) );
		topicsData = data.flow[ 'view-topiclist' ].result.topiclist;

		if ( scrollTarget === 'window' && flowBoard.readingTopicId ) {
			// Store the current position of the topic you are reading
			readingTopicPosition = { id: flowBoard.readingTopicId };
			// Where does the topic start?
			readingTopicPosition.topicStart = flowBoard.renderedTopics[ readingTopicPosition.id ].offset().top;
			// Where am I within the topic?
			readingTopicPosition.topicPlace = $( window ).scrollTop() - readingTopicPosition.topicStart;
		}

		// Render topics
		_flowBoardComponentLoadMoreFeatureRenderTopics(
			flowBoard,
			topicsData,
			flowBoard.$container.find( flowBoard.$loadMoreNodes ).last()[ 0 ] === this, // if this is the last load more button
			$target,
			scrollTarget,
			$this.data( 'flow-scroll-container' ),
			$this.data( 'flow-template' )
		);

		// Remove the old load button (necessary if the above load_more template returns nothing)
		$target.remove();

		if ( scrollTarget === 'window' ) {
			$scrollTarget = $( window );

			if ( readingTopicPosition ) {
				readingTopicPosition.anuStart = flowBoard.renderedTopics[ readingTopicPosition.id ].offset().top;
				if ( readingTopicPosition.anuStart > readingTopicPosition.topicStart ) {
					// Looks like the topic we are reading got pushed down. Let's jump to where we were before
					$scrollTarget.scrollTop( readingTopicPosition.anuStart + readingTopicPosition.topicPlace );
				}
			}
		} else {
			$scrollTarget = $.findWithParent( this, scrollTarget );
		}

		/*
		 * Fire infinite scroll check again - if no (or few) topics were
		 * added (e.g. because they're moderated), we should immediately
		 * fetch more instead of waiting for the user to scroll again (when
		 * there's no reason to scroll)
		 */
		_flowBoardComponentLoadMoreFeatureInfiniteScrollCheck.call( flowBoard, $scrollContainer, $scrollTarget );
		return $.Deferred().resolve().promise();
	}
	FlowBoardComponentLoadMoreFeatureMixin.UI.events.apiHandlers.loadMoreTopics = flowBoardComponentLoadMoreFeatureTopicsApiCallback;

	//
	// On element-load handlers
	//

	/**
	 * Stores the load more button for use with infinite scroll.
	 *
	 *     <button data-flow-scroll-target="< ul"></button>
	 *
	 * @param {jQuery} $button
	 */
	function flowBoardComponentLoadMoreFeatureElementLoadCallback( $button ) {
		var scrollTargetSelector = $button.data( 'flow-scroll-target' ),
			$target,
			scrollContainerSelector = $button.data( 'flow-scroll-container' ),
			$scrollContainer = $.findWithParent( $button, scrollContainerSelector ),
			board = this;

		if ( !this.$loadMoreNodes ) {
			// Create a new $loadMoreNodes list
			this.$loadMoreNodes = $();
		} else {
			// Remove any loadMore nodes that are no longer in the body
			this.$loadMoreNodes = this.$loadMoreNodes.filter( function () {
				var $this = $( this );

				// @todo unbind scroll handlers
				if ( !$this.closest( 'body' ).length ) {
					// Get rid of this and its handlers
					$this.remove();
					// Delete from list
					return false;
				}

				return true;
			} );
		}

		// Store this new loadMore node
		this.$loadMoreNodes = this.$loadMoreNodes.add( $button );

		// Make sure we didn't already bind to this element's scroll previously
		if ( $scrollContainer.data( 'scrollIsBound' ) ) {
			return;
		}
		$scrollContainer.data( 'scrollIsBound', true );

		// Bind the event for this
		if ( scrollTargetSelector === 'window' ) {
			this.on( 'windowScroll', function () {
				_flowBoardComponentLoadMoreFeatureInfiniteScrollCheck.call( board, $scrollContainer, $( window ) );
			} );
		} else {
			$target = $.findWithParent( $button, scrollTargetSelector );
			$target.on( 'scroll.flow-load-more', $.throttle( 50, function () {
				_flowBoardComponentLoadMoreFeatureInfiniteScrollCheck.call( board, $scrollContainer, $target );
			} ) );
		}
	}
	FlowBoardComponentLoadMoreFeatureMixin.UI.events.loadHandlers.loadMore = flowBoardComponentLoadMoreFeatureElementLoadCallback;

	/**
	 * Stores a list of all topics currently visible on the page.
	 *
	 * @param {jQuery} $topic
	 */
	function flowBoardComponentLoadMoreFeatureElementLoadTopic( $topic ) {
		var self = this,
			currentTopicId = $topic.data( 'flow-id' );

		// Store this topic by ID
		this.renderedTopics[ currentTopicId ] = $topic;

		// Remove any topics that are no longer on the page, just in case
		// eslint-disable-next-line no-jquery/no-each-util
		$.each( this.renderedTopics, function ( topicId, $topic ) {
			if ( !$topic.closest( self.$board ).length ) {
				delete self.renderedTopics[ topicId ];
			}
		} );
	}
	FlowBoardComponentLoadMoreFeatureMixin.UI.events.loadHandlers.topic = flowBoardComponentLoadMoreFeatureElementLoadTopic;

	//
	// Private functions
	//

	/**
	 * Generates Array#sort callback for sorting a list of topic ids
	 * by the 'recently active' sort order. This is a numerical
	 * comparison of related timestamps held within the board object.
	 * Also note that this is a reverse sort from newest to oldest.
	 *
	 * @private
	 *
	 * @param {Object} board Object from which to source
	 *  timestamps which map from topicId to its last updated timestamp
	 * @return {Function} Sort callback
	 * @return {string} return.a
	 * @return {string} return.b
	 * @return {number} return.return Per Array#sort callback rules
	 */
	function _flowBoardTopicIdGenerateSortRecentlyActive( board ) {
		return function ( a, b ) {
			var aTimestamp = board.updateTimestampsByTopicId[ a ],
				bTimestamp = board.updateTimestampsByTopicId[ b ];

			if ( aTimestamp === undefined && bTimestamp === undefined ) {
				return 0;
			} else if ( aTimestamp === undefined ) {
				return 1;
			} else if ( bTimestamp === undefined ) {
				return -1;
			} else {
				return bTimestamp - aTimestamp;
			}
		};
	}

	/**
	 * Re-sorts the orderedTopicIds after insert
	 *
	 * @param {Object} flowBoard
	 */
	function _flowBoardSortTopicIds( flowBoard ) {
		var topicIdSortCallback;

		if ( flowBoard.topicIdSort === 'updated' ) {
			topicIdSortCallback = _flowBoardTopicIdGenerateSortRecentlyActive( flowBoard );

			// Custom sorts
			flowBoard.orderedTopicIds.sort( topicIdSortCallback );
		} else {
			// Default sort, takes advantage of topic ids monotonically increasing
			// which allows for the newest sort to be the default utf-8 string sort
			// in reverse.
			// TODO: This can be optimized (to avoid two in-place operations that affect
			// the whole array by doing a descending sort (with a custom comparator)
			// rather than sorting then reversing.
			flowBoard.orderedTopicIds.sort().reverse();
		}
	}
	FlowBoardComponentLoadMoreFeatureMixin.prototype.sortTopicIds = _flowBoardSortTopicIds;

	/**
	 * Called on scroll. Checks to see if a FlowBoard needs to have more content loaded.
	 *
	 * @param {jQuery} $searchContainer Container to find 'load more' buttons in
	 * @param {jQuery} $calculationContainer Container to do scroll calculations on (height, scrollTop, offset, etc.)
	 */
	function _flowBoardComponentLoadMoreFeatureInfiniteScrollCheck( $searchContainer, $calculationContainer ) {
		var calculationContainerHeight, calculationContainerScroll;
		if ( this.infiniteScrollDisabled ) {
			// This happens when the topic navigation is used to jump to a topic
			// We should not infinite-load anything when we are scrolling to a topic
			return;
		}

		calculationContainerHeight = $calculationContainer.height();
		calculationContainerScroll = $calculationContainer.scrollTop();

		// Find load more buttons within our search container, and they must be visible
		// eslint-disable-next-line no-jquery/no-sizzle
		$searchContainer.find( this.$loadMoreNodes ).filter( ':visible' ).each( function () {
			var $this = $( this ),
				nodeOffset = $this.offset().top,
				nodeHeight = $this.outerHeight( true );

			// First, is this element above or below us?
			if ( nodeOffset <= calculationContainerScroll ) {
				// Top of element is above the viewport; don't use it.
				return;
			}

			// @todo: this ignores that TOC also obscures the button: load more
			// also shouldn't be triggered if it's still behind TOC!

			// Is this element in the viewport?
			if ( nodeOffset - nodeHeight <= calculationContainerScroll + calculationContainerHeight ) {
				// Element is almost in viewport, click it.
				$( this ).trigger( 'click' );
			}
		} );
	}

	/**
	 * Renders and inserts a list of new topics.
	 *
	 * @param {FlowBoardComponent} flowBoard
	 * @param {Object} topicsData
	 * @param {boolean} [forceShowLoadMore]
	 * @param {jQuery} [$insertAt]
	 * @param {string} [scrollTarget]
	 * @param {string} [scrollContainer]
	 * @param {string} [scrollTemplate]
	 * @private
	 */
	function _flowBoardComponentLoadMoreFeatureRenderTopics( flowBoard, topicsData, forceShowLoadMore, $insertAt, scrollTarget, scrollContainer, scrollTemplate ) {
		var i, j, $topic, topicId,
			$allRendered = $( [] ),
			toInsert = [];

		if ( !topicsData.roots.length ) {
			flowBoard.debug( 'No topics returned from API', arguments );
			return;
		}

		function _createRevPagination( $target ) {
			// FIXME reverse pagination is broken in the backend, don't use it
			return;

			// eslint-disable-next-line no-unreachable
			if ( !topicsData.links.pagination.fwd && !topicsData.links.pagination.rev ) {
				return;
			}

			if ( !topicsData.links.pagination.rev && topicsData.links.pagination.fwd ) {
				// This is a fix for the fact that a "rev" is not available here (TODO: Why not?)
				// We can create one by overriding dir=rev
				topicsData.links.pagination.rev = $.extend( true, {}, topicsData.links.pagination.fwd, { title: 'rev' } );
				topicsData.links.pagination.rev.url = topicsData.links.pagination.rev.url.replace( '_offset-dir=fwd', '_offset-dir=rev' );
			}

			$allRendered = $allRendered.add(
				$( flowBoard.constructor.static.TemplateEngine.processTemplateGetFragment(
					'flow_load_more.partial',
					{
						loadMoreObject: topicsData.links.pagination.rev,
						loadMoreApiHandler: 'loadMoreTopics',
						loadMoreTarget: scrollTarget,
						loadMoreContainer: scrollContainer,
						loadMoreTemplate: scrollTemplate
					}
				) ).children()
					.insertBefore( $target.first() )
			);
		}

		function _createFwdPagination( $target ) {
			if ( forceShowLoadMore || topicsData.links.pagination.fwd ) {
				// Add the load more to the end of the stack
				$allRendered = $allRendered.add(
					$( flowBoard.constructor.static.TemplateEngine.processTemplateGetFragment(
						'flow_load_more.partial',
						{
							loadMoreObject: topicsData.links.pagination.fwd,
							loadMoreApiHandler: 'loadMoreTopics',
							loadMoreTarget: scrollTarget,
							loadMoreContainer: scrollContainer,
							loadMoreTemplate: scrollTemplate
						}
					) ).children()
						.insertAfter( $target.last() )
				);
			}
		}

		/**
		 * Renders topics by IDs from topicsData, and returns the elements.
		 *
		 * @param {Array} toRender List of topic IDs in topicsData
		 * @return {jQuery}
		 * @private
		 */
		function _render( toRender ) {
			var rootsBackup = topicsData.roots,
				$newTopics;

			// Temporarily set roots to our subset to be rendered
			topicsData.roots = toRender;

			try {
				$newTopics = $( flowBoard.constructor.static.TemplateEngine.processTemplateGetFragment(
					scrollTemplate,
					topicsData
				) ).children();
			} catch ( e ) {
				flowBoard.debug( true, 'Failed to render new topic' );
				$newTopics = $();
			}

			topicsData.roots = rootsBackup;

			return $newTopics;
		}

		for ( i = 0; i < topicsData.roots.length; i++ ) {
			topicId = topicsData.roots[ i ];

			if ( !flowBoard.renderedTopics[ topicId ] ) {
				flowBoard.renderedTopics[ topicId ] = _render( [ topicId ] );
				$allRendered.push( flowBoard.renderedTopics[ topicId ][ 0 ] );
				toInsert.push( topicId );
				if ( flowBoard.orderedTopicIds.indexOf( topicId ) === -1 ) {
					flowBoard.orderedTopicIds.push( topicId );
				}
				// @todo this is already done elsewhere, but it runs after insert
				// to the DOM instead of before.  Not sure how to fix ordering.
				if ( !flowBoard.updateTimestampsByTopicId[ topicId ] ) {
					flowBoard.updateTimestampsByTopicId[ topicId ] = topicsData.revisions[ topicsData.posts[ topicId ][ 0 ] ].last_updated;
				}
			}
		}

		if ( toInsert.length ) {
			_flowBoardSortTopicIds( flowBoard );

			// This uses the assumption that there will be at least one pre-existing
			// topic above the topics to be inserted.  This should hold true as the
			// initial page load starts at the begining.
			for ( i = 1; i < flowBoard.orderedTopicIds.length; i++ ) {
				// topic is not to be inserted yet.
				if ( toInsert.indexOf( flowBoard.orderedTopicIds[ i ] ) === -1 ) {
					continue;
				}

				// find the most recent topic in the list that exists and insert after it.
				for ( j = i - 1; j >= 0; j-- ) {
					$topic = flowBoard.renderedTopics[ flowBoard.orderedTopicIds[ j ] ];
					if ( $topic && $topic.length && $.contains( document.body, $topic[ 0 ] ) ) {
						break;
					}
				}

				// Put the new topic after the found topic above it
				if ( j >= 0 ) {
					// If there is a load-more here, insert after that as well
					// eslint-disable-next-line no-jquery/no-class-state
					if ( $topic.next().hasClass( 'flow-load-more' ) ) {
						$topic = $topic.next();
					}
					$topic.after( flowBoard.renderedTopics[ flowBoard.orderedTopicIds[ i ] ] );
				}
			}

			// This works because orderedTopicIds includes not only the topics on
			// page but also the ones loaded by the toc.  If these topics are due
			// to a jump rather than forward auto-pagination the prior topic will
			// not be rendered.
			i = flowBoard.orderedTopicIds.indexOf( topicsData.roots[ 0 ] );
			if ( i > 0 && flowBoard.renderedTopics[ flowBoard.orderedTopicIds[ i - 1 ] ] === undefined ) {
				_createRevPagination( flowBoard.renderedTopics[ topicsData.roots[ 0 ] ] );
			}
			// Same for forward pagination, if we jumped and then scrolled backwards the
			// topic after the last will already be rendered, and forward pagination
			// will not be necessary.
			i = flowBoard.orderedTopicIds.indexOf( topicsData.roots[ topicsData.roots.length - 1 ] );
			if ( i === flowBoard.orderedTopicIds.length - 1 || flowBoard.renderedTopics[ flowBoard.orderedTopicIds[ i + 1 ] ] === undefined ) {
				_createFwdPagination( flowBoard.renderedTopics[ topicsData.roots[ topicsData.roots.length - 1 ] ] );
			}

		}

		// Run loadHandlers
		flowBoard.emitWithReturn( 'makeContentInteractive', $allRendered );

		// HACK: Emit an event here so that the flow data model can populate
		// itself based on the API response
		flowBoard.emit( 'loadmore', topicsData );
	}

	// Mixin to FlowBoardComponent
	mw.flow.mixinComponent( 'board', FlowBoardComponentLoadMoreFeatureMixin );
}() );
