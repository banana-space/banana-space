( function () {
	var apiTransformMap = {
		// Map of API submodule name, block name, and prefix name
		'moderate-post': [ 'topic_', 'mp' ],
		'new-topic': [ 'topiclist_', 'nt' ],
		'edit-header': [ 'header_', 'eh' ],
		'edit-post': [ 'topic_', 'ep' ],
		reply: [ 'topic_', 'rep' ],
		'moderate-topic': [ 'topic_', 'mt' ],
		'edit-title': [ 'topic_', 'et' ],
		'lock-topic': [ 'topic_', 'cot' ],
		'view-topiclist': [ 'topiclist_', 'vtl' ],
		'view-post': [ 'topic', 'vp' ],
		'view-topic': [ 'topic', 'vt' ],
		'view-header': [ 'header_', 'vh' ],
		'view-topic-summary': [ 'topicsummary_', 'vts' ],
		'edit-topic-summary': [ 'topicsummary_', 'ets' ]
	};

	mw.flow = mw.flow || {}; // create mw.flow globally

	/**
	 * Handles Flow API calls. Each FlowComponent has its own instance of FlowApi as component.Api,
	 * so that it can store a workflowId and pageName permanently for simplicity.
	 *
	 * @constructor
	 * @param {string} [workflowId]
	 * @param {string} [pageName]
	 */
	function FlowApi( workflowId, pageName ) {
		this.workflowId = workflowId;
		this.pageName = pageName;

		/**
		 * Makes the actual API call and returns
		 *
		 * @param {Object|string} [params] May be a JSON object string
		 * @param {string} [method]
		 * @return {jQuery.Promise}
		 */
		function flowApiCall( params, method ) {
			var mwApi, tokenType,
				$deferred = $.Deferred(),
				ajaxTimeoutSec = mw.config.get( 'wgFlowAjaxTimeout' ),
				apiConstructorParams = { ajax: {} };

			if ( ajaxTimeoutSec !== null && ajaxTimeoutSec > 0 ) {
				apiConstructorParams.ajax.timeout = ajaxTimeoutSec * 1000;
			}

			mwApi = new mw.Api( apiConstructorParams );

			params = params || {};
			// Server is using page instead of title
			// @todo this should not be necessary
			params.page = params.page || this.pageName || mw.config.get( 'wgPageName' );
			method = method ? method.toUpperCase() : 'GET';

			if ( !params.action ) {
				mw.flow.debug( '[FlowApi] apiCall error: missing action string', arguments );
				return $deferred.reject( 'fail-apirequest', { error: { info: 'Invalid action' } } ).promise();
			}
			if ( !params.page ) {
				mw.flow.debug( '[FlowApi] apiCall error: missing page string', [ mw.config.get( 'wgPageName' ) ], arguments );
				return $deferred.reject( 'fail-apirequest', { error: { info: 'Invalid title' } } ).promise();
			}

			if ( method === 'POST' ) {
				if ( params._internal && params._internal.tokenType ) {
					tokenType = params._internal.tokenType;
				} else {
					tokenType = 'csrf';
				}

				delete params._internal;

				return mwApi.postWithToken( tokenType, params );
			} else if ( method !== 'GET' ) {
				return $deferred.reject( 'fail-apirequest', { error: { info: 'Unknown submission method: ' + method } } ).promise();
			} else {
				return mwApi.get( params );
			}
		}

		this.apiCall = flowApiCall;
	}

	/** @type {string} */
	FlowApi.prototype.pageName = null;
	/** @type {string} */
	FlowApi.prototype.workflowId = null;
	/** @type {string} */
	FlowApi.prototype.defaultSubmodule = null;

	/**
	 * Sets the fixed pageName for this API instance.
	 *
	 * @param {string} pageName
	 */
	function flowApiSetPageName( pageName ) {
		this.pageName = pageName;
	}

	FlowApi.prototype.setPageName = flowApiSetPageName;

	/**
	 * Sets the fixed workflowId for this API instance.
	 *
	 * @param {string} workflowId
	 */
	function flowApiSetWorkflowId( workflowId ) {
		this.workflowId = workflowId;
	}

	FlowApi.prototype.setWorkflowId = flowApiSetWorkflowId;

	/**
	 * Transforms URL request parameters into API params
	 *
	 * @todo fix it server-side so we don't need this client-side
	 * @param {Object} queryMap
	 * @return {Object}
	 */
	function flowApiTransformMap( queryMap ) {
		var key,
			map = apiTransformMap[ queryMap.submodule ];
		if ( !map ) {
			return queryMap;
		}
		for ( key in queryMap ) {
			if ( Object.prototype.hasOwnProperty.call( queryMap, key ) ) {
				if ( key.indexOf( map[ 0 ] ) === 0 ) {
					queryMap[ key.replace( map[ 0 ], map[ 1 ] ) ] = queryMap[ key ];
					delete queryMap[ key ];
				}
				if ( key.indexOf( 'flow_' ) === 0 ) {
					queryMap[ key.replace( 'flow_', map[ 1 ] ) ] = queryMap[ key ];
					delete queryMap[ key ];
				}
			}
		}

		return queryMap;
	}

	/**
	 * Sets the fixed defaultSubmodule for this API instance.
	 *
	 * @param {string} defaultSubmodule
	 */
	function flowApiSetDefaultSubmodule( defaultSubmodule ) {
		this.defaultSubmodule = defaultSubmodule;
	}

	FlowApi.prototype.setDefaultSubmodule = flowApiSetDefaultSubmodule;

	/**
	 * With a url (a://b.c/d?e=f&g#h) will return an object of key-value pairs ({e:'f', g:''}).
	 *
	 * @param {string|HTMLElement} url
	 * @param {Object} [queryMap]
	 * @return {Object}
	 */
	function flowApiGetQueryMap( url, queryMap ) {
		var uri,
			queryKey,
			queryValue,
			i = 0,
			$node, $form, formData;

		queryMap = queryMap || {};

		// If URL is an Element...
		if ( typeof url !== 'string' ) {
			$node = $( url );

			// Get the data-flow-api-action override from the node itself
			queryMap.submodule = $node.data( 'flow-api-action' );

			if ( $node.is( 'form, input, button, textarea, select, option' ) ) {
				// We are processing a form
				$form = $node.closest( 'form' );
				formData = $form.serializeArray();

				// Get the data-flow-api-action override from the form
				queryMap.submodule = queryMap.submodule || $form.data( 'flow-api-action' );

				// Build the queryMap manually from a serialized form
				for ( i = 0; i < formData.length; i++ ) {
					// skip wpEditToken, its handle independently
					if ( formData[ i ].name !== 'wpEditToken' ) {
						queryMap[ formData[ i ].name ] = formData[ i ].value;
					}
				}

				// Add the given button to the queryMap as well
				if ( $node.is( 'button, input' ) && $node.prop( 'name' ) ) {
					queryMap[ $node.prop( 'name' ) ] = $node.val();
				}

				// Now process the form action as the URL
				url = $form.attr( 'action' );
			} else if ( $node.is( 'a' ) ) {
				// It's an anchor, process the href as the URL
				url = $node.prop( 'href' );
			} else {
				// Somebody set up us the bomb
				url = '';
			}
		}

		// Parse the URL query params
		uri = new mw.Uri( url );

		for ( queryKey in uri.query ) {
			queryValue = uri.query[ queryKey ];
			if ( queryKey === 'action' ) {
				// Submodule is the action
				queryKey = 'submodule';
			}
			if ( queryKey === 'title' ) {
				// Server is using page
				queryKey = 'page';
			}

			// Only add this to the query map if it didn't already exist, eg. in a form input
			if ( !queryMap[ queryKey ] ) {
				queryMap[ queryKey ] = queryValue;
			}
		}

		// Use the default submodule if no action in URL
		queryMap.submodule = queryMap.submodule || this.defaultSubmodule;

		// Default action is flow
		queryMap.action = queryMap.action || 'flow';

		// Use the API map to transform this data if necessary, eg.
		queryMap = flowApiTransformMap( queryMap );

		return queryMap;
	}

	FlowApi.prototype.getQueryMap = flowApiGetQueryMap;

	/**
	 * Using a given form, parses its action, serializes the data, and sends it as GET or POST depending on form method.
	 * With button, its name=value is serialized in. If button is an Event, it will attempt to find the clicked button.
	 * Additional params can be set with data-flow-api-params on both the clicked button or the form.
	 *
	 * @param {Event|HTMLElement} button
	 * @param {Object} queryMap
	 * @return {jQuery.Promise}
	 */
	function flowApiRequestFromForm( button, queryMap ) {
		var $button = $( button ),
			method = $button.closest( 'form' ).attr( 'method' ) || 'GET';

		// Cancel any old form request, and also trigger a new one
		return this.abortOldRequestFromNode( $button, queryMap, method );
	}

	FlowApi.prototype.requestFromForm = flowApiRequestFromForm;

	/**
	 * Using a given anchor, parses its URL and sends it as a GET (default) or POST depending on data-flow-api-method.
	 * Additional params can be set with data-flow-api-params.
	 *
	 * @param {HTMLElement} anchor
	 * @param {Object} queryMap
	 * @return {jQuery.Promise}
	 */
	function flowApiRequestFromAnchor( anchor, queryMap ) {
		var $anchor = $( anchor ),
			method = $anchor.data( 'flow-api-method' ) || 'GET';

		// Abort any old requests, and have it issue a new one via GET or POST
		return this.abortOldRequestFromNode( $anchor, queryMap, method );
	}

	FlowApi.prototype.requestFromAnchor = flowApiRequestFromAnchor;

	/**
	 * Automatically calls requestFromAnchor or requestFromForm depending on the type of node given.
	 *
	 * @param {HTMLElement} node
	 * @param {Object} queryMap
	 * @return {jQuery.Promise}
	 */
	function flowApiRequestFromNode( node ) {
		var $node = $( node );

		if ( $node.is( 'a' ) ) {
			return this.requestFromAnchor.apply( this, arguments );
		} else if ( $node.is( 'form, input, button, textarea, select, option' ) ) {
			return this.requestFromForm.apply( this, arguments );
		} else {
			return $.Deferred().reject( 'fail-apirequest', { error: { info: 'apiRequest element is not anchor or form element' } } );
		}
	}

	FlowApi.prototype.requestFromNode = flowApiRequestFromNode;

	/**
	 * Handles aborting an old in-flight API request.
	 * If startNewMethod is given, this method also STARTS a new API call and stores it for later abortion if needed.
	 *
	 * @param {jQuery|HTMLElement} $node
	 * @param {Object} queryMap
	 * @param {string} [startNewMethod] If given: starts, stores, and returns a new API call
	 * @return {undefined|jQuery.Promise}
	 */
	function flowApiAbortOldRequestFromNode( $node, queryMap, startNewMethod ) {
		var str, prevApiCall, newApiCall;

		$node = $( $node );

		// transform flow_* params into (nt_*, rep_*, ...)
		queryMap = flowApiTransformMap( queryMap );

		// If this anchor already has a request in flight, abort it
		str = 'flow-api-query-temp-' + queryMap.action + '-' + queryMap.submodule;
		prevApiCall = $node.data( str );

		// If a previous API call was found, let's abort it
		if ( prevApiCall ) {
			$node.removeData( str );

			if ( prevApiCall.abort ) {
				prevApiCall.abort();
			}

			mw.flow.debug( '[FlowApi] apiCall abort request in flight: ' + str, arguments );
		}

		// If a method was given, we want to also issue a new API request now
		if ( startNewMethod ) {
			// Make a new request with this info
			newApiCall = this.apiCall( queryMap, startNewMethod );

			// Store this request on the node if it needs to be aborted
			$node.data(
				'flow-api-query-temp-' + queryMap.action + '-' + queryMap.submodule,
				newApiCall
			);

			// Remove the request on success
			newApiCall.always( function () {
				$node.removeData( 'flow-api-query-temp-' + queryMap.action + '-' + queryMap.submodule );
			} );

			return newApiCall;
		}
	}

	FlowApi.prototype.abortOldRequestFromNode = flowApiAbortOldRequestFromNode;

	// Export
	mw.flow.FlowApi = FlowApi;
}() );
