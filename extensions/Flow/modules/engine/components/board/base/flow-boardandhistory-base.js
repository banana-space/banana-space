/*!
 * Contains the base class for both FlowBoardComponent and FlowBoardHistoryComponent.
 * This is functionality that is used by both types of page, but not any other components.
 */

( function () {
	var inTopicNamespace = mw.config.get( 'wgNamespaceNumber' ) === mw.config.get( 'wgNamespaceIds' ).topic;

	/**
	 *
	 * @param {jQuery} $container
	 * @constructor
	 */
	function FlowBoardAndHistoryComponentBase() {
		this.bindNodeHandlers( FlowBoardAndHistoryComponentBase.UI.events );
	}
	OO.initClass( FlowBoardAndHistoryComponentBase );

	FlowBoardAndHistoryComponentBase.UI = {
		events: {
			apiPreHandlers: {},
			apiHandlers: {},
			interactiveHandlers: {}
		}
	};

	// Register
	mw.flow.registerComponent( 'boardAndHistoryBase', FlowBoardAndHistoryComponentBase );

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
	FlowBoardAndHistoryComponentBase.prototype.reinitializeContainer = function ( $container ) {
		if ( $container === false ) {
			return false;
		}

		// Progressively enhance the board and its forms
		// @todo Needs a ~"liveUpdateComponents" method, since the functionality in makeContentInteractive needs to also run when we receive new content or update old content.
		// @todo move form stuff
		if ( $container.data( 'flow-component' ) !== 'board' ) {
			// Don't do this for FlowBoardComponent, because that runs makeContentInteractive in its own reinit
			this.emitWithReturn( 'makeContentInteractive', this );
		}

		// We don't replace anything with this method (we do with flowBoardComponentReinitializeContainer)
		return $();
	};

	/**
	 * This will trigger an eventLog call to the given schema with the given
	 * parameters (along with other info about the user & page.)
	 * A unique funnel ID will be created for all new EventLog calls.
	 *
	 * There may be multiple subsequent calls in the same "funnel" (and share
	 * same info) that you want to track. It is possible to forward funnel data
	 * from one node to another once the first has been clicked. It'll then
	 * log new calls with the same data (schema & entrypoint) & funnel ID as the
	 * initial logged event.
	 *
	 * @param {string} schemaName
	 * @param {Object} data Data to be logged
	 * @param {string} data.action Schema's action parameter. Always required!
	 * @param {string} [data.entrypoint] Schema's entrypoint parameter (can be
	 *   omitted if already logged in funnel - will inherit)
	 * @param {string} [data.funnelId] Schema's funnelId parameter (can be
	 *   omitted if starting new funnel - will be generated)
	 * @param {jQuery} [$forward] Nodes to forward funnel to
	 * @return {Object} Logged data
	 */
	FlowBoardAndHistoryComponentBase.prototype.logEvent = function ( schemaName, data, $forward ) {
		var // Get existing (forwarded) funnel id, or generate a new one if it does not yet exist
			funnelId = data.funnelId || mw.flow.EventLogRegistry.generateFunnelId(),
			// Fetch existing EventLog object for this funnel (if any)
			eventLog = mw.flow.EventLogRegistry.funnels[ funnelId ];

		// Optional argument, may not want/need to forward funnel to other nodes
		$forward = $forward || $();

		if ( !eventLog ) {
			// Add some more data to log!
			data = $.extend( data, {
				isAnon: mw.user.isAnon(),
				sessionId: mw.user.sessionId(),
				funnelId: funnelId,
				pageNs: mw.config.get( 'wgNamespaceNumber' ),
				pageTitle: ( new mw.Title( mw.config.get( 'wgPageName' ) ) ).getMain()
			} );

			// A funnel with this id does not yet exist, create it!
			eventLog = new mw.flow.EventLog( schemaName, data );

			// Store this particular eventLog - we may want to log more things
			// in this funnel
			mw.flow.EventLogRegistry.funnels[ funnelId ] = eventLog;
		}

		// Log this action
		eventLog.logEvent( { action: data.action } );

		// Forward the event
		this.forwardEvent( $forward, schemaName, funnelId );

		return data;
	};

	/**
	 * Forward funnel data to other places.
	 *
	 * @param {jQuery} $forward Nodes to forward funnel to
	 * @param {string} schemaName
	 * @param {string} funnelId Schema's funnelId parameter
	 */
	FlowBoardAndHistoryComponentBase.prototype.forwardEvent = function ( $forward, schemaName, funnelId ) {
		// Not using data() - it somehow gets lost on some nodes
		$forward.attr( {
			'data-flow-eventlog-schema': schemaName,
			'data-flow-eventlog-funnel-id': funnelId
		} );
	};

	//
	// Interactive handlers
	//

	/**
	 * @param {Event} event
	 * @return {jQuery.Promise}
	 */
	FlowBoardAndHistoryComponentBase.UI.events.interactiveHandlers.moderationDialog = function ( event ) {
		var $form,
			$this = $( this ),
			flowComponent = mw.flow.getPrototypeMethod( 'boardAndHistoryBase', 'getInstanceByElement' )( $this ),
			// hide, delete, suppress
			// @todo this could just be detected from the url
			role = $this.data( 'role' ),
			template = $this.data( 'flow-template' ),
			params = {
				editToken: mw.user.tokens.get( 'csrfToken' ), // might be unnecessary
				submitted: {
					moderationState: role
				},
				actions: {}
			},
			$deferred = $.Deferred(),
			modal;

		event.preventDefault();

		params.actions[ role ] = { url: $this.attr( 'href' ), title: $this.attr( 'title' ) };

		// Render the modal itself with mw-ui-modal
		modal = mw.Modal( {
			open: $( mw.flow.TemplateEngine.processTemplateGetFragment( template, params ) ).children(),
			disableCloseOnOutsideClick: true
		} );

		// @todo remove this data-flow handler forwarder when data-mwui handlers are implemented
		// Have the events begin bubbling up from $board
		flowComponent.assignSpawnedNode( modal.getNode(), flowComponent.$board );

		// Run loadHandlers
		flowComponent.emitWithReturn( 'makeContentInteractive', modal.getContentNode() );

		// Set flowDialogOwner for API callback @todo find a better way of doing this with mw.Modal
		$form = modal.getContentNode().find( 'form' ).data( 'flow-dialog-owner', $this );
		// Bind the cancel callback on the form
		flowComponent.emitWithReturn( 'addFormCancelCallback', $form, function () {
			mw.Modal.close( this );
		} );

		modal = null; // avoid permanent reference

		return $deferred.resolve().promise();
	};

	/**
	 * Cancels and closes a form. If text has been entered, issues a warning first.
	 *
	 * @param {Event} event
	 * @return {jQuery.Promise}
	 */
	FlowBoardAndHistoryComponentBase.UI.events.interactiveHandlers.cancelForm = function ( event ) {
		var target = this,
			$form = $( this ).closest( 'form' ),
			flowComponent = mw.flow.getPrototypeMethod( 'boardAndHistoryBase', 'getInstanceByElement' )( $form ),
			$fields = $form.find( 'textarea, [type=text]' ),
			changedFieldCount = 0,
			$deferred = $.Deferred(),
			callbacks = $form.data( 'flow-cancel-callback' ) || [],
			schemaName = $( this ).data( 'flow-eventlog-schema' ),
			funnelId = $( this ).data( 'flow-eventlog-funnel-id' );

		event.preventDefault();

		// Only log cancel attempt if it was user-initiated, not when the cancel
		// was triggered by code (as part of a post-submit form destroy)
		if ( event.which && schemaName ) {
			flowComponent.logEvent( schemaName, { action: 'cancel-attempt', funnelId: funnelId } );
		}

		// Check for non-empty fields of text
		$fields.each( function () {
			if ( $( this ).val() !== this.defaultValue ) {
				changedFieldCount++;
				return false;
			}
		} );

		// Only log if user had already entered text (= confirmation was requested)
		if ( changedFieldCount ) {
			// TODO: Use an OOUI dialog
			// eslint-disable-next-line no-alert
			if ( confirm( flowComponent.constructor.static.TemplateEngine.l10n( 'flow-cancel-warning' ) ) ) {
				if ( schemaName ) {
					flowComponent.logEvent( schemaName, { action: 'cancel-success', funnelId: funnelId } );
				}
			} else {
				if ( schemaName ) {
					flowComponent.logEvent( schemaName, { action: 'cancel-abort', funnelId: funnelId } );
				}

				// User aborted cancel, quit this function & don't destruct the form!
				return $deferred.reject().promise();
			}
		}

		// Reset the form content
		$form[ 0 ].reset();

		// Trigger for flow-actions-disabler
		$form.find( 'textarea, [type=text]' ).trigger( 'keyup' );

		// Hide the form
		flowComponent.emitWithReturn( 'hideForm', $form );

		// Get rid of existing error messages
		flowComponent.emitWithReturn( 'removeError', $form );

		// Trigger the cancel callback
		callbacks.forEach( function ( fn ) {
			fn.call( target, event );
		} );

		return $deferred.resolve().promise();
	};

	//
	// Static methods
	//

	/**
	 * Return true page is in topic namespace,
	 * and if $el is given, that if $el is also within .flow-post.
	 *
	 * @param {jQuery} [$el]
	 * @return {boolean}
	 */
	function flowBoardInTopicNamespace( $el ) {
		return inTopicNamespace && ( !$el || $el.closest( '.flow-post' ).length === 0 );
	}
	FlowBoardAndHistoryComponentBase.static.inTopicNamespace = flowBoardInTopicNamespace;
}() );
