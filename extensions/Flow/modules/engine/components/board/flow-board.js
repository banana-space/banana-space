/*!
 * Contains the base constructor for FlowBoardComponent.
 * @todo Clean up the remaining code that may not need to be here.
 */

( function () {
	/**
	 * Constructor class for instantiating a new Flow board.
	 *
	 *    <div class="flow-component" data-flow-component="board" data-flow-id="rqx495tvz888x5ur">...</div>
	 *
	 * @param {jQuery} $container
	 * @extends FlowBoardAndHistoryComponentBase
	 * @mixins FlowComponentEventsMixin
	 * @mixins FlowComponentEnginesMixin
	 * @mixins FlowBoardComponentApiEventsMixin
	 * @mixins FlowBoardComponentInteractiveEventsMixin
	 * @mixins FlowBoardComponentLoadEventsMixin
	 * @mixins FlowBoardComponentLoadMoreFeatureMixin
	 * @mixins FlowBoardComponentVisualEditorFeatureMixin
	 *
	 * @constructor
	 */
	function FlowBoardComponent( $container ) {
		var uri = new mw.Uri( location.href ),
			anchorUid = String( location.hash.match( /[0-9a-z]{16,19}$/i ) || '' ),
			highlightUid;

		// Default API submodule for FlowBoard URLs is to fetch a topiclist
		this.Api.setDefaultSubmodule( 'view-topiclist' );

		// Set up the board
		if ( this.reinitializeContainer( $container ) === false ) {
			// Failed to init for some reason
			return false;
		}

		// Handle URL parameters.  If topic_showPostId is used, there should also be an
		// anchor.
		if ( anchorUid ) {
			if ( uri.query.fromnotif ) {
				highlightUid = uri.query.topic_showPostId;
				_flowHighlightPost( $container, highlightUid, 'newer' );
			} else {
				highlightUid = anchorUid;
				_flowHighlightPost( $container, highlightUid );
			}
		} else {
			// There is a weird bug with url ending with #flow-post-xxxx
			// and full height side rail.
			// We only enable the full height when we don't have such url.
			$container.addClass( 'flow-full-height-side-rail' );
		}

		_overrideWatchlistNotification();
	}
	OO.initClass( FlowBoardComponent );

	// Register
	mw.flow.registerComponent( 'board', FlowBoardComponent, 'boardAndHistoryBase' );

	//
	// Methods
	//

	/**
	 * Sets up the board and base properties on this class.
	 * Returns either FALSE for failure, or jQuery object of old nodes that were replaced.
	 *
	 * @param {jQuery|boolean} $container
	 * @return {boolean|jQuery}
	 */
	function flowBoardComponentReinitializeContainer( $container ) {
		var $retObj, $header, $boardNavigation, $board;

		if ( $container === false ) {
			return false;
		}

		// Trigger this on FlowBoardAndHistoryComponentBase
		// @todo use EventEmitter to do this?
		$retObj = FlowBoardComponent.super.prototype.reinitializeContainer.call( this, $container );
		// Find any new (or previous) elements
		// eslint-disable-next-line no-jquery/no-sizzle
		$header = $container.find( '.flow-board-header' ).addBack().filter( '.flow-board-header:first' );
		// eslint-disable-next-line no-jquery/no-sizzle
		$boardNavigation = $container.find( '.flow-board-navigation' ).addBack().filter( '.flow-board-navigation:first' );
		// eslint-disable-next-line no-jquery/no-sizzle
		$board = $container.find( '.flow-board' ).addBack().filter( '.flow-board:first' );

		if ( $retObj === false ) {
			return false;
		}

		// Remove any of the old elements that are still in use
		if ( $header.length ) {
			if ( this.$header ) {
				$retObj = $retObj.add( this.$header.replaceWith( $header ) );
				this.$header.remove();
			}

			this.$header = $header;
		}
		if ( $boardNavigation.length ) {
			if ( this.$boardNavigation ) {
				$retObj = $retObj.add( this.$boardNavigation.replaceWith( $boardNavigation ) );
				this.$boardNavigation.remove();
			}

			this.$boardNavigation = $boardNavigation;
		}
		if ( $board.length ) {
			if ( this.$board ) {
				$retObj = $retObj.add( this.$board.replaceWith( $board ) );
				this.$board.remove();
			}

			this.$board = $board;
		}

		// Second, verify that this board in fact exists
		if ( !this.$board || !this.$board.length ) {
			// You need a board, dammit!
			this.debug( 'Could not find .flow-board', arguments );
			return false;
		}

		this.emitWithReturn( 'makeContentInteractive', this );

		return $retObj;
	}
	FlowBoardComponent.prototype.reinitializeContainer = flowBoardComponentReinitializeContainer;

	//
	// Private functions
	//

	/**
	 * Helper receives
	 *
	 * @param {jQuery} $container
	 * @param {string} uid Anchor to scroll to
	 * @param {string} [option] 'newer' if all posts equal to or newer than uid should be
	 *  highlighted.  Otherwise, it will only highlight that post itself.
	 * @return {jQuery}
	 */
	function _flowHighlightPost( $container, uid, option ) {
		var $target = $container.find( '#flow-post-' + uid );

		// reset existing highlights
		$container.find( '.flow-post-highlighted' ).removeClass( 'flow-post-highlighted' );

		if ( option === 'newer' ) {
			$target.addClass( 'flow-post-highlight-newer' );
			if ( uid ) {
				$container.find( '.flow-post' ).each( function ( idx, el ) {
					var $el = $( el ),
						id = $el.data( 'flow-id' );
					if ( id && id > uid ) {
						$el.addClass( 'flow-post-highlight-newer' );
					}
				} );
			}
		} else {
			$target.addClass( 'flow-post-highlighted' );
		}

		return $target;
	}

	/**
	 * We want the default behavior of watch/unwatch for page. However, we
	 * do want to show our own tooltip after this has happened.
	 * We'll override mw.notify, which is fired after successfully
	 * (un)watchlisting, to stop the notification from being displayed.
	 * If the action we just intercepted was after succesful watching, we'll
	 * want to show our own tooltip instead.
	 */
	function _overrideWatchlistNotification() {
		var _notify = mw.notify;
		mw.notify = function ( $message, options ) {
			// override message when we've just watched the board
			// eslint-disable-next-line no-jquery/no-global-selector
			if ( options && options.tag === 'watch-self' && $( '#ca-watch' ).length ) {
				// Render a div telling the user that they have subscribed
				$message = $( mw.flow.TemplateEngine.processTemplateGetFragment(
					'flow_subscribed.partial',
					{
						type: 'board',
						user: mw.user
					}
				) ).children();
			}

			return _notify.call( this, $message, options );
		};
	}
}() );
