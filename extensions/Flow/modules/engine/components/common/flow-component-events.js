/*!
 * Contains the code which registers and handles event callbacks.
 * In addition, it contains some common callbacks (eg. apiRequest)
 * @todo Find better places for a lot of the callbacks that have been placed here
 */

/**
 * @class FlowComponent
 * TODO: Use @-external in JSDoc
 */
/**
 * @class FlowBoardComponent
 * TODO: Use @-external in JSDoc
 */

( function () {
	var _isGlobalBound;

	/**
	 * This implements functionality for being able to capture the return value from a called event.
	 * In addition, this handles Flow event triggering and binding.
	 *
	 * @class
	 * @extends OO.EventEmitter
	 * @constructor
	 * @param {jQuery} $container Container
	 */
	function FlowComponentEventsMixin( $container ) {
		var self = this;

		/**
		 * Stores event callbacks.
		 */
		this.UI = {
			events: {
				globalApiPreHandlers: {},
				apiPreHandlers: {},
				apiHandlers: {},
				interactiveHandlers: {},
				loadHandlers: {}
			}
		};

		// Init EventEmitter
		OO.EventEmitter.call( this );

		// Bind events to this instance
		this.bindComponentHandlers( FlowComponentEventsMixin.eventHandlers );

		// Bind element handlers
		this.bindNodeHandlers( FlowComponentEventsMixin.UI.events );

		// Container handlers
		// @todo move some to FlowBoardComponent events, rename the others to FlowComponent
		$container
			.off( '.FlowBoardComponent' )
			.on(
				'click.FlowBoardComponent keypress.FlowBoardComponent',
				'a, input, button, .flow-click-interactive',
				this.getDispatchCallback( 'interactiveHandler' )
			)
			.on(
				'focusin.FlowBoardComponent',
				'a, input, button, .flow-click-interactive',
				this.getDispatchCallback( 'interactiveHandlerFocus' )
			)
			.on(
				'focusin.FlowBoardComponent',
				'input.mw-ui-input, textarea',
				this.getDispatchCallback( 'focusField' )
			)
			.on(
				'click.FlowBoardComponent keypress.FlowBoardComponent',
				'[data-flow-eventlog-action]',
				this.getDispatchCallback( 'eventLogHandler' )
			);

		if ( _isGlobalBound ) {
			// Don't bind window.scroll again.
			return;
		}
		_isGlobalBound = true;

		// Handle scroll and resize events globally
		$( window )
			.on(
				// Normal scroll events on elements do not bubble.  However, if they
				// are triggered, jQuery will do so.  To avoid this affecting the
				// global scroll handler, trigger scroll events on elements only with
				// scroll.flow-something, where 'something' is not 'window-scroll'.
				'scroll.flow-window-scroll',
				$.throttle( 50, function ( evt ) {
					if ( evt.target !== window && evt.target !== document ) {
						throw new Error( 'Target is "' + evt.target.nodeName + '", not window or document.' );
					}

					self.getDispatchCallback( 'windowScroll' ).apply( self, arguments );
				} )
			)
			.on(
				'resize.flow',
				$.throttle( 50, this.getDispatchCallback( 'windowResize' ) )
			);
	}
	OO.mixinClass( FlowComponentEventsMixin, OO.EventEmitter );

	FlowComponentEventsMixin.eventHandlers = {};
	FlowComponentEventsMixin.UI = {
		events: {
			interactiveHandlers: {}
		}
	};

	//
	// Prototype methods
	//

	/**
	 * Same as OO.EventEmitter.emit, except that it returns an array of results.
	 * If something returns false, we stop processing the rest of the callbacks, if any.
	 *
	 * @param {string} event Name of the event to trigger
	 * @param {...*} [args] Arguments to pass to event callback
	 * @return {Array}
	 */
	function emitWithReturn( event, args ) {
		var i, len, binding, bindings, method, retVal,
			returns = [];

		if ( event in this.bindings ) {
			// Slicing ensures that we don't get tripped up by event handlers that add/remove bindings
			bindings = this.bindings[ event ].slice();
			args = Array.prototype.slice.call( arguments, 1 );
			for ( i = 0, len = bindings.length; i < len; i++ ) {
				binding = bindings[ i ];

				if ( typeof binding.method === 'string' ) {
					// Lookup method by name (late binding)
					method = binding.context[ binding.method ];
				} else {
					method = binding.method;
				}

				// Call function
				retVal = method.apply(
					binding.context || this,
					binding.args ? binding.args.concat( args ) : args
				);

				// Add this result to our list of return vals
				returns.push( retVal );

				if ( retVal === false ) {
					// Returned false; stop running callbacks
					break;
				}
			}
			return returns;
		}
		return [];
	}
	FlowComponentEventsMixin.prototype.emitWithReturn = emitWithReturn;

	/**
	 *
	 * @param {Object} handlers
	 */
	function bindFlowComponentHandlers( handlers ) {
		var self = this;

		// Bind class event handlers, triggered by .emit
		// eslint-disable-next-line no-jquery/no-each-util
		$.each( handlers, function ( key, fn ) {
			self.on( key, function () {
				// Trigger callback with class instance context
				try {
					return fn.apply( self, arguments );
				} catch ( e ) {
					mw.flow.debug( 'Error in component handler:', key, e, arguments );
					return false;
				}
			} );
		} );
	}
	FlowComponentEventsMixin.prototype.bindComponentHandlers = bindFlowComponentHandlers;

	/**
	 * handlers can have keys globalApiPreHandlers, apiPreHandlers, apiHandlers, interactiveHandlers, loadHandlers
	 *
	 * @param {Object} handlers
	 */
	function bindFlowNodeHandlers( handlers ) {
		var self = this;

		// eg. { interactiveHandlers: { foo: Function } }
		// eslint-disable-next-line no-jquery/no-each-util
		$.each( handlers, function ( type, callbacks ) {
			// eg. { foo: Function }
			// eslint-disable-next-line no-jquery/no-each-util
			$.each( callbacks, function ( name, fn ) {
				// First time for this callback name, instantiate the callback list
				if ( !self.UI.events[ type ][ name ] ) {
					self.UI.events[ type ][ name ] = [];
				}
				if ( Array.isArray( fn ) ) {
					// eg. UI.events.interactiveHandlers.foo concat [Function, Function];
					self.UI.events[ type ][ name ] = self.UI.events[ type ][ name ].concat( fn );
				} else {
					// eg. UI.events.interactiveHandlers.foo = [Function];
					self.UI.events[ type ][ name ].push( fn );
				}
			} );
		} );
	}
	FlowComponentEventsMixin.prototype.bindNodeHandlers = bindFlowNodeHandlers;

	/**
	 * Returns a callback function which passes off arguments to the emitter.
	 * This only exists to clean up the FlowComponentEventsMixin constructor,
	 * by preventing it from having too many anonymous functions.
	 *
	 * @param {string} name
	 * @return {Function}
	 * @private
	 */
	function flowComponentGetDispatchCallback( name ) {
		var context = this;

		return function () {
			var args = Array.prototype.slice.call( arguments, 0 );

			// Add event name as first arg of emit
			args.unshift( name );

			return context.emitWithReturn.apply( context, args );
		};
	}
	FlowComponentEventsMixin.prototype.getDispatchCallback = flowComponentGetDispatchCallback;

	//
	// Static methods
	//

	/**
	 * Utility to get error message for API result.
	 *
	 * @param {string} code
	 * @param {Object} result
	 * @return {string}
	 */
	function flowGetApiErrorMessage( code, result ) {
		if ( result.error && result.error.info ) {
			return result.error.info;
		} else {
			if ( code === 'http' ) {
				// XXX: some network errors have English info in result.exception and result.textStatus.
				return mw.msg( 'flow-error-http' );
			} else {
				return mw.msg( 'flow-error-external', code );
			}
		}
	}
	FlowComponentEventsMixin.static.getApiErrorMessage = flowGetApiErrorMessage;

	//
	// Interactive Handlers
	//

	/**
	 * Triggers an API request based on URL and form data, and triggers the callbacks based on flow-api-handler.
	 *
	 *     <a data-flow-interactive-handler="apiRequest" data-flow-api-handler="loadMore" data-flow-api-target="< .flow-component div" href="...">...</a>
	 *
	 * @param {Event} event
	 * @return {jQuery.Promise}
	 */
	function flowEventsMixinApiRequestInteractiveHandler( event ) {
		var deferred = $.Deferred(),
			deferreds = [ deferred ],
			$target,
			self = event.currentTarget || event.delegateTarget || event.target,
			$this = $( self ),
			flowComponent = mw.flow.getPrototypeMethod( 'component', 'getInstanceByElement' )( $this ),
			dataParams = $this.data(),
			handlerName = dataParams.flowApiHandler,
			info = {
				$target: null,
				status: null,
				component: flowComponent
			},
			args = Array.prototype.slice.call( arguments, 0 ),
			queryMap = flowComponent.Api.getQueryMap( self.href || self ),
			preHandlers = [];

		event.preventDefault();

		// Find the target node
		if ( dataParams.flowApiTarget ) {
			// This fn supports finding parents
			$target = $this.findWithParent( dataParams.flowApiTarget );
		}
		if ( !$target || !$target.length ) {
			// Assign a target node if none
			$target = $this;
		}

		// insert queryMap & info into args for prehandler
		info.$target = $target;
		args.splice( 1, 0, info );
		args.splice( 2, 0, queryMap );

		deferred.resolve( args );

		// chain apiPreHandler callbacks
		preHandlers = _getApiPreHandlers( self, handlerName );
		preHandlers.forEach( function ( callback ) {
			deferred = deferred.then( callback );
		} );

		// mark the element as "in progress" (we're only doing this after running
		// preHandlers since they may reject the API call)
		deferred = deferred.then( function ( args ) {
			// Protect against repeated or nested API calls for the same handler
			var inProgress = $target.data( 'inProgress' ) || [];
			if ( inProgress.indexOf( handlerName ) !== -1 ) {
				return $.Deferred().reject( 'fail-api-inprogress', { error: { info: 'apiRequest already in progress' } } );
			}
			inProgress.push( handlerName );
			$target.data( 'inProgress', inProgress );

			// Mark the target node as "in progress" to disallow any further API calls until it finishes
			$target.addClass( 'flow-api-inprogress' );
			$this.addClass( 'flow-api-inprogress' );

			// Remove existing errors from previous attempts
			flowComponent.emitWithReturn( 'removeError', $this );

			return args;
		} );

		// execute API call
		deferred = deferred.then( function ( args ) {
			var queryMap = args[ 2 ];
			return flowComponent.Api.requestFromNode( self, queryMap ).then(
				// alter API response: apiHandler expects a 1st param info (that
				// includes 'status') & `this` being the target element
				function () {
					var args = Array.prototype.slice.call( arguments, 0 );
					info.status = 'done';
					args.unshift( info );
					return $.Deferred().resolveWith( self, args );
				},
				// failure: display the error message to end-user & turn the rejected
				// deferred back into resolve: apiHandlers may want to wrap up
				function ( code, result ) {
					var errorMsg,
						args = Array.prototype.slice.call( arguments, 0 ),
						$form = $this.closest( 'form' );

					if ( code === 'http' && result.textStatus === 'abort' ) {
						// don't show error for aborted API requests & don't turn
						// into resolved: we don't want callbacks to run here!
						return $.Deferred().rejectWith( self, args );
					}

					info.status = 'fail';
					args.unshift( info );

					/*
					 * In the event of edit conflicts, store the previous
					 * revision id so we can re-submit an edit against the
					 * current id later.
					 */
					if ( result.error && result.error.prev_revision ) {
						$form.data( 'flow-prev-revision', result.error.prev_revision.revision_id );
					}

					/*
					 * Generic error handling: displays error message in the
					 * nearest error container.
					 *
					 * Errors returned by MW/Flow should always be in the
					 * same format. If the request failed without a specific
					 * error message, just fall back to some default error.
					 */
					errorMsg = flowComponent.constructor.static.getApiErrorMessage( code, result );
					flowComponent.emitWithReturn( 'showError', $this, errorMsg );

					flowComponent.Api.abortOldRequestFromNode( self, queryMap, null );

					// keep going & process those apiHandlers; based on info.status,
					// they'll know if they're dealing with successful submissions,
					// or cleaning up after error
					return $.Deferred().resolveWith( self, args );
				}
			);
		} );

		// chain apiHandler callbacks (it can distinguish in how it needs to wrap up
		// depending on info.status)
		if ( flowComponent.UI.events.apiHandlers[ handlerName ] ) {
			flowComponent.UI.events.apiHandlers[ handlerName ].forEach( function ( callback ) {
				/*
				 * apiHandlers will return promises that won't resolve until
				 * the apiHandler has completed all it needs to do.
				 * These handlers aren't chainable, though (although we only
				 * have 1 per call, AFAIK), they don't return the same data the
				 * next handler assumes.
				 * In order to suspend something until all of these apiHandlers
				 * have completed, we'll combine them in an array which we can
				 * keep tabs on until all of these promises are done ($.when)
				 */
				deferreds.push( deferred.then( callback ) );
			} );
		}

		// all-purpose error handling: whichever step in this chain rejects, we'll send it to console
		deferred.fail( function ( code, result ) {
			var errorMsg = flowComponent.constructor.static.getApiErrorMessage( code, result );
			flowComponent.debug( false, errorMsg, handlerName, args );
		} );

		// cleanup after successfully completing the request & handler(s)
		return $.when.apply( $, deferreds ).done( function () {
			var inProgress = $target.data( 'inProgress' ) || [];
			inProgress.splice( inProgress.indexOf( handlerName ), 1 );
			$target.data( 'inProgress', inProgress );

			if ( inProgress.length === 0 ) {
				$target.removeClass( 'flow-api-inprogress' );
				$this.removeClass( 'flow-api-inprogress' );
			}
		} );
	}
	FlowComponentEventsMixin.UI.events.interactiveHandlers.apiRequest = flowEventsMixinApiRequestInteractiveHandler;

	//
	// Event handler methods
	//

	/**
	 *
	 * @param {FlowComponent|jQuery} $container or entire FlowComponent
	 * @todo Perhaps use name="flow-load-handler" for performance in older browsers
	 */
	function flowMakeContentInteractiveCallback( $container ) {
		var component, $content;

		if ( !$container.jquery ) {
			$container = $container.$container;
		}

		if ( !$container.length ) {
			// Prevent erroring out with an empty node set
			return;
		}

		// Get the FlowComponent
		component = mw.flow.getPrototypeMethod( 'component', 'getInstanceByElement' )( $container );

		// Find all load-handlers and trigger them
		$container.find( '.flow-load-interactive' ).add( $container.filter( '.flow-load-interactive' ) ).each( function () {
			var $this = $( this ),
				handlerName = $this.data( 'flow-load-handler' );

			if ( $this.data( 'flow-load-handler-called' ) ) {
				return;
			}
			$this.data( 'flow-load-handler-called', true );

			// If this has a special load handler, run it.
			component.emitWithReturn( 'loadHandler', handlerName, $this );
		} );

		// Trigger for flow-actions-disabler
		// @todo move this into a flow-load-handler
		$container.find( 'input, textarea' ).trigger( 'keyup' );

		$content = $container.find( '.mw-parser-output' ).filter( function () {
			// Ignore content that has already been initialized, see flow-initialize.js
			return !$( this ).data( 'flow-wikipage-content-fired' );
		} );
		if ( $content.length ) {
			mw.hook( 'wikipage.content' ).fire( $content );
		}
	}
	FlowComponentEventsMixin.eventHandlers.makeContentInteractive = flowMakeContentInteractiveCallback;

	// Triggers load handlers
	function flowLoadHandlerCallback( handlerName, args, context ) {
		args = Array.isArray( args ) ? args : ( args ? [ args ] : [] );
		context = context || this;

		if ( this.UI.events.loadHandlers[ handlerName ] ) {
			this.UI.events.loadHandlers[ handlerName ].forEach( function ( fn ) {
				fn.apply( context, args );
			} );
		}
	}
	FlowComponentEventsMixin.eventHandlers.loadHandler = flowLoadHandlerCallback;

	/**
	 * Executes interactive handlers.
	 *
	 * @param {Array} args
	 * @param {jQuery} $context
	 * @param {string} interactiveHandlerName
	 * @param {string} apiHandlerName
	 */
	function flowExecuteInteractiveHandler( args, $context, interactiveHandlerName, apiHandlerName ) {
		var promises = [];

		// Call any matching interactive handlers
		if ( this.UI.events.interactiveHandlers[ interactiveHandlerName ] ) {
			this.UI.events.interactiveHandlers[ interactiveHandlerName ].forEach( function ( fn ) {
				promises.push( fn.apply( $context[ 0 ], args ) );
			} );
		} else if ( this.UI.events.apiHandlers[ apiHandlerName ] ) {
			// Call any matching API handlers
			this.UI.events.interactiveHandlers.apiRequest.forEach( function ( fn ) {
				promises.push( fn.apply( $context[ 0 ], args ) );
			} );
		} else if ( interactiveHandlerName ) {
			this.debug( 'Failed to find interactiveHandler', interactiveHandlerName, arguments );
		} else if ( apiHandlerName ) {
			this.debug( 'Failed to find apiHandler', apiHandlerName, arguments );
		}

		// Add aggregate deferred object as data attribute, so we can hook into
		// the element when the handlers have run
		$context.data( 'flow-interactive-handler-promise', $.when.apply( $, promises ) );
	}

	/**
	 * Triggers both API and interactive handlers.
	 * To manually trigger a handler on an element, you can use extraParameters via $el.trigger.
	 *
	 * @param {Event} event
	 * @param {Object} [extraParameters]
	 * @param {string} [extraParameters.interactiveHandler]
	 * @param {string} [extraParameters.apiHandler]
	 */
	function flowInteractiveHandlerCallback( event, extraParameters ) {
		var args, $context, interactiveHandlerName, apiHandlerName;

		// Only trigger with enter key & no modifier keys, if keypress
		if ( event.type === 'keypress' && ( event.charCode !== 13 || event.metaKey || event.shiftKey || event.ctrlKey || event.altKey ) ) {
			return;
		}

		args = Array.prototype.slice.call( arguments, 0 );
		$context = $( event.currentTarget || event.delegateTarget || event.target );
		// Have either of these been forced via trigger extraParameters?
		interactiveHandlerName = ( extraParameters || {} ).interactiveHandler || $context.data( 'flow-interactive-handler' );
		apiHandlerName = ( extraParameters || {} ).apiHandler || $context.data( 'flow-api-handler' );

		flowExecuteInteractiveHandler.call( this, args, $context, interactiveHandlerName, apiHandlerName );
	}
	FlowComponentEventsMixin.eventHandlers.interactiveHandler = flowInteractiveHandlerCallback;
	FlowComponentEventsMixin.eventHandlers.apiRequest = flowInteractiveHandlerCallback;

	/**
	 * Triggers both API and interactive handlers, on focus.
	 *
	 * @param {Event} event
	 */
	function flowInteractiveHandlerFocusCallback( event ) {
		var args = Array.prototype.slice.call( arguments, 0 ),
			$context = $( event.currentTarget || event.delegateTarget || event.target ),
			interactiveHandlerName = $context.data( 'flow-interactive-handler-focus' ),
			apiHandlerName = $context.data( 'flow-api-handler-focus' );

		flowExecuteInteractiveHandler.call( this, args, $context, interactiveHandlerName, apiHandlerName );
	}
	FlowComponentEventsMixin.eventHandlers.interactiveHandlerFocus = flowInteractiveHandlerFocusCallback;

	/**
	 * Callback function for when a [data-flow-eventlog-action] node is clicked.
	 * This will trigger a eventLog call to the given schema with the given
	 * parameters.
	 * A unique funnel ID will be created for all new EventLog calls.
	 *
	 * There may be multiple subsequent calls in the same "funnel" (and share
	 * same info) that you want to track. It is possible to forward funnel data
	 * from one attribute to another once the first has been clicked. It'll then
	 * log new calls with the same data (schema & entrypoint) & funnel ID as the
	 * initial logged event.
	 *
	 * Required parameters (as data-attributes) are:
	 * * data-flow-eventlog-schema: The schema name
	 * * data-flow-eventlog-entrypoint: The schema's entrypoint parameter
	 * * data-flow-eventlog-action: The schema's action parameter
	 *
	 * Additionally:
	 * * data-flow-eventlog-forward: Selectors to forward funnel data to
	 *
	 * @param {Event} event
	 */
	function flowEventLogCallback( event ) {
		var $context, data, component, $promise, eventInstance, key, value;

		// Only trigger with enter key & no modifier keys, if keypress
		if ( event.type === 'keypress' && ( event.charCode !== 13 || event.metaKey || event.shiftKey || event.ctrlKey || event.altKey ) ) {
			return;
		}

		$context = $( event.currentTarget );
		data = $context.data();
		component = mw.flow.getPrototypeMethod( 'component', 'getInstanceByElement' )( $context );
		$promise = data.flowInteractiveHandlerPromise || $.Deferred().resolve().promise();
		eventInstance = {};

		// Fetch loggable data: everything prefixed flowEventlog except
		// flowEventLogForward and flowEventLogSchema
		for ( key in data ) {
			if ( key.indexOf( 'flowEventlog' ) === 0 ) {
				// @todo Either the data or this config should have separate prefixes,
				// it shouldn't be shared and then handled here.
				if ( key === 'flowEventlogForward' || key === 'flowEventlogSchema' ) {
					continue;
				}

				// Strips "flowEventlog" and lowercases first char after that
				value = data[ key ];
				key = key.substr( 12, 1 ).toLowerCase() + key.substr( 13 );

				eventInstance[ key ] = value;
			}
		}

		// Log the event
		eventInstance = component.logEvent( data.flowEventlogSchema, eventInstance );

		// Promise resolves once all interactiveHandlers/apiHandlers are done,
		// so all nodes we want to forward to are bound to be there
		$promise.always( function () {
			// Now find all nodes to forward to
			var $forward = data.flowEventlogForward ? $context.findWithParent( data.flowEventlogForward ) : $();

			// Forward the funnel
			eventInstance = component.forwardEvent( $forward, data.flowEventlogSchema, eventInstance.funnelId );
		} );
	}
	FlowComponentEventsMixin.eventHandlers.eventLogHandler = flowEventLogCallback;

	/**
	 * When the whole class has been instantiated fully (after every constructor has been called).
	 *
	 * @param {FlowComponent} component
	 */
	function flowEventsMixinInstantiationComplete() {
		$( window ).trigger( 'scroll.flow-window-scroll' );
	}
	FlowComponentEventsMixin.eventHandlers.instantiationComplete = flowEventsMixinInstantiationComplete;

	/**
	 * Compress a flow form and/or its actions.
	 *
	 * @param {jQuery} $form
	 * @todo Move this to a separate file
	 */
	function flowEventsMixinHideForm( $form ) {
		// Hide its actions
		// @todo Use TemplateEngine to find and hide actions?
		$form.find( '.flow-form-collapsible' ).toggleClass( 'flow-form-collapsible-collapsed', true );
	}
	FlowComponentEventsMixin.eventHandlers.hideForm = flowEventsMixinHideForm;

	/**
	 * Show form when input is focused.
	 *
	 * @param {Event} event
	 * @todo Move this to a separate file
	 */
	function flowEventsMixinFocusField( event ) {
		var $context = $( event.currentTarget || event.delegateTarget || event.target ),
			component = mw.flow.getPrototypeMethod( 'component', 'getInstanceByElement' )( $context );

		// Show the form
		component.emitWithReturn( 'showForm', $context.closest( 'form' ) );
	}
	FlowComponentEventsMixin.eventHandlers.focusField = flowEventsMixinFocusField;

	/**
	 * Expand a flow form and/or its actions.
	 *
	 * @param {jQuery} $form
	 */
	function flowEventsMixinShowForm( $form ) {
		// Show its actions
		$form.find( '.flow-form-collapsible' ).toggleClass( 'flow-form-collapsible-collapsed', false );
	}
	FlowComponentEventsMixin.eventHandlers.showForm = flowEventsMixinShowForm;

	/**
	 * Adds a flow-cancel-callback to a given form, to be triggered on click of the "cancel" button.
	 *
	 * @param {jQuery} $form
	 * @param {Function} callback
	 */
	function flowEventsMixinAddFormCancelCallback( $form, callback ) {
		var fns = $form.data( 'flow-cancel-callback' ) || [];
		fns.push( callback );
		$form.data( 'flow-cancel-callback', fns );
	}
	FlowComponentEventsMixin.eventHandlers.addFormCancelCallback = flowEventsMixinAddFormCancelCallback;

	/**
	 * @param {FlowBoardComponent|jQuery} $node or entire FlowBoard
	 */
	function flowEventsMixinRemoveError( $node ) {
		_flowFindUpward( $node, '.flow-error-container' ).filter( ':first' ).empty();
	}
	FlowComponentEventsMixin.eventHandlers.removeError = flowEventsMixinRemoveError;

	/**
	 * @param {FlowBoardComponent|jQuery} $node or entire FlowBoard
	 * @param {string} msg The error that occurred. Currently hardcoded.
	 */
	function flowEventsMixinShowError( $node, msg ) {
		var fragment = mw.flow.TemplateEngine.processTemplate( 'flow_errors.partial', { errors: [ { message: msg } ] } );

		if ( !$node.jquery ) {
			$node = $node.$container;
		}

		_flowFindUpward( $node, '.flow-error-container' ).filter( ':first' ).replaceWith( fragment );
	}
	FlowComponentEventsMixin.eventHandlers.showError = flowEventsMixinShowError;

	/**
	 * Shows a tooltip telling the user that they have subscribed
	 * to this topic|board
	 *
	 * @param  {jQuery} $tooltipTarget Element to attach tooltip to.
	 * @param  {string} type           'topic' or 'board'
	 * @param  {string} dir            Direction to point the pointer. 'left', 'right', 'up' or 'down'
	 */
	function flowEventsMixinShowSubscribedTooltip( $tooltipTarget, type, dir ) {
		dir = dir || 'left';

		mw.tooltip.show(
			$tooltipTarget,
			// tooltipTarget will not always be part of a FlowBoardComponent
			$(
				mw.flow.TemplateEngine.processTemplateGetFragment(
					'flow_tooltip_subscribed.partial',
					{
						unsubscribe: false,
						type: type,
						direction: dir,
						user: mw.user
					}
				)
			).children(),
			{
				tooltipPointing: dir
			}
		);

		// Hide after 5s
		setTimeout( function () {
			mw.tooltip.hide( $tooltipTarget );
		}, 5000 );
	}
	FlowComponentEventsMixin.eventHandlers.showSubscribedTooltip = flowEventsMixinShowSubscribedTooltip;

	/**
	 * If a form has a cancelForm handler, we clear the form and trigger it. This allows easy cleanup
	 * and triggering of form events after successful API calls.
	 *
	 * @param {HTMLElement|jQuery} formElement
	 */
	function flowEventsMixinCancelForm( formElement ) {
		var $form = $( formElement ),
			$button = $form.find( 'button, input, a' ).filter( '[data-flow-interactive-handler="cancelForm"]' );

		if ( $button.length ) {
			// Clear contents to not trigger the "are you sure you want to
			// discard your text" warning
			$form.find( 'textarea, [type=text]' ).each( function () {
				$( this ).val( this.defaultValue );
			} );

			// Trigger a click on cancel to have it destroy the form the way it should
			$button.trigger( 'click' );
		}
	}
	FlowComponentEventsMixin.eventHandlers.cancelForm = flowEventsMixinCancelForm;

	//
	// Private functions
	//

	/**
	 * Given node & a selector, this will return the result closest to $node
	 * by first looking inside $node, then travelling up the DOM tree to
	 * locate the first result in a common ancestor.
	 *
	 * @param {jQuery} $node
	 * @param {string} selector
	 * @return {jQuery}
	 */
	function _flowFindUpward( $node, selector ) {
		// first check if result can already be found inside $node
		var $result = $node.find( selector );

		// then keep looking up the tree until a result is found
		while ( $result.length === 0 && $node.length !== 0 ) {
			$node = $node.parent();
			$result = $node.children( selector );
		}

		return $result;
	}

	/**
	 * @param {HTMLElement} target
	 * @param {string} handlerName
	 * @return {Function[]}
	 * @private
	 */
	function _getApiPreHandlers( target, handlerName ) {
		var flowComponent = mw.flow.getPrototypeMethod( 'component', 'getInstanceByElement' )( $( target ) ),
			preHandlers = [];

		// Compile a list of all preHandlers to be run
		// eslint-disable-next-line no-jquery/no-each-util
		$.each( flowComponent.UI.events.globalApiPreHandlers, function ( key, callbackArray ) {
			Array.prototype.push.apply( preHandlers, callbackArray );
		} );
		if ( flowComponent.UI.events.apiPreHandlers[ handlerName ] ) {
			Array.prototype.push.apply( preHandlers, flowComponent.UI.events.apiPreHandlers[ handlerName ] );
		}

		preHandlers = preHandlers.map( function ( callback ) {
			/*
			 * apiPreHandlers aren't properly set up to serve as chained promise
			 * callbacks (they'll return false instead of returning a rejected
			 * promise, the incoming & outgoing params don't line up)
			 * This will wrap all those callbacks into callbacks we can chain.
			 */
			return function ( args ) {
				var queryMap = callback.apply( target, args );
				if ( queryMap === false ) {
					return $.Deferred().reject( 'fail-prehandler', { error: { info: 'apiPreHandler returned false' } } );
				}

				if ( $.isPlainObject( queryMap ) ) {
					args[ 2 ] = queryMap;
				}

				return args;
			};
		} );

		return preHandlers;
	}

	// Copy static and prototype from mixin to main class
	mw.flow.mixinComponent( 'component', FlowComponentEventsMixin );
}() );
