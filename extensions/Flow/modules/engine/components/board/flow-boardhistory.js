( function () {
	/**
	 *
	 *    <div class="flow-component" data-flow-component="boardHistory" data-flow-id="rqx495tvz888x5ur">...</div>
	 *
	 * @class
	 * @extends FlowBoardAndHistoryComponentBase
	 * @constructor
	 * @param {jQuery} $container
	 */
	function FlowBoardHistoryComponent() {
		this.bindNodeHandlers( FlowBoardHistoryComponent.UI.events );
	}
	OO.initClass( FlowBoardHistoryComponent );

	FlowBoardHistoryComponent.UI = {
		events: {
			apiHandlers: {}
		}
	};

	mw.flow.registerComponent( 'boardHistory', FlowBoardHistoryComponent, 'boardAndHistoryBase' );

	//
	// API handlers
	//

	/**
	 * After submit of a moderation form, process the response.
	 *
	 * @param {Object} info
	 * @param {string} info.status "done" or "fail"
	 * @param {jQuery} info.$target
	 * @param {Object} data
	 * @param {jQuery.jqXHR} jqxhr
	 * @return {jQuery.Promise}
	 */
	function flowBoardHistoryModerationCallback( info ) {
		var flowBoardHistory;

		if ( info.status !== 'done' ) {
			// Error will be displayed by default, nothing else to wrap up
			return $.Deferred().resolve().promise();
		}

		flowBoardHistory = mw.flow.getPrototypeMethod( 'boardHistory', 'getInstanceByElement' )( $( this ) );

		// Clear the form so we can refresh without the confirmation dialog
		flowBoardHistory.emitWithReturn( 'cancelForm', $( this ).closest( 'form' ) );

		// @todo implement dynamic updating of the history page instead of this
		location.reload();

		return $.Deferred().resolve().promise();
	}

	FlowBoardHistoryComponent.UI.events.apiHandlers.moderateTopic = flowBoardHistoryModerationCallback;
	FlowBoardHistoryComponent.UI.events.apiHandlers.moderatePost = flowBoardHistoryModerationCallback;

	/**
	 * Reloads the history page after resolving or reopening a topic.
	 *
	 * @param {Object} info
	 * @param {Object} data
	 * @return {jQuery.Promise}
	 */
	FlowBoardHistoryComponent.UI.events.apiHandlers.lockTopic = function ( info ) {
		if ( info.status !== 'done' ) {
			// Error will be displayed by default & edit conflict handled, nothing else to wrap up
			return $.Deferred().resolve().promise();
		}

		location.reload();

		return $.Deferred().resolve().promise();
	};

}() );
