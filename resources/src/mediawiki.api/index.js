( function () {

	/**
	 * @class mw.Api
	 */

	/**
	 * @property {Object} defaultOptions Default options for #ajax calls. Can be overridden by passing
	 *     `options` to mw.Api constructor.
	 * @property {Object} defaultOptions.parameters Default query parameters for API requests.
	 * @property {Object} defaultOptions.ajax Default options for jQuery#ajax.
	 * @property {boolean} defaultOptions.useUS Whether to use U+001F when joining multi-valued
	 *     parameters (since 1.28). Default is true if ajax.url is not set, false otherwise for
	 *     compatibility.
	 * @private
	 */
	var defaultOptions = {
			parameters: {
				action: 'query',
				format: 'json'
			},
			ajax: {
				url: mw.util.wikiScript( 'api' ),
				timeout: 30 * 1000, // 30 seconds
				dataType: 'json'
			}
		},

		// Keyed by ajax url and symbolic name for the individual request
		promises = {};

	function mapLegacyToken( action ) {
		// Legacy types for backward-compatibility with API action=tokens.
		var csrfActions = [
			'edit',
			'delete',
			'protect',
			'move',
			'block',
			'unblock',
			'email',
			'import',
			'options'
		];
		if ( csrfActions.indexOf( action ) !== -1 ) {
			mw.track( 'mw.deprecate', 'apitoken_' + action );
			mw.log.warn( 'Use of the "' + action + '" token is deprecated. Use "csrf" instead.' );
			return 'csrf';
		}
		return action;
	}

	// Pre-populate with fake ajax promises to avoid HTTP requests for tokens that
	// we already have on the page from the embedded user.options module (T36733).
	promises[ defaultOptions.ajax.url ] = {};
	// eslint-disable-next-line no-jquery/no-each-util
	$.each( mw.user.tokens.get(), function ( key, value ) {
		// This requires #getToken to use the same key as mw.user.tokens.
		// Format: token-type + "Token" (eg. csrfToken, patrolToken, watchToken).
		promises[ defaultOptions.ajax.url ][ key ] = $.Deferred()
			.resolve( value )
			.promise( { abort: function () {} } );
	} );

	/**
	 * Constructor to create an object to interact with the API of a particular MediaWiki server.
	 * mw.Api objects represent the API of a particular MediaWiki server.
	 *
	 *     var api = new mw.Api();
	 *     api.get( {
	 *         action: 'query',
	 *         meta: 'userinfo'
	 *     } ).done( function ( data ) {
	 *         console.log( data );
	 *     } );
	 *
	 * Since MW 1.25, multiple values for a parameter can be specified using an array:
	 *
	 *     var api = new mw.Api();
	 *     api.get( {
	 *         action: 'query',
	 *         meta: [ 'userinfo', 'siteinfo' ] // same effect as 'userinfo|siteinfo'
	 *     } ).done( function ( data ) {
	 *         console.log( data );
	 *     } );
	 *
	 * Since MW 1.26, boolean values for a parameter can be specified directly. If the value is
	 * `false` or `undefined`, the parameter will be omitted from the request, as required by the API.
	 *
	 * @constructor
	 * @param {Object} [options] See #defaultOptions documentation above. Can also be overridden for
	 *  each individual request by passing them to #get or #post (or directly #ajax) later on.
	 */
	mw.Api = function ( options ) {
		var defaults = $.extend( {}, options ),
			setsUrl = options && options.ajax && options.ajax.url !== undefined;

		defaults.parameters = $.extend( {}, defaultOptions.parameters, defaults.parameters );
		defaults.ajax = $.extend( {}, defaultOptions.ajax, defaults.ajax );

		// Force a string if we got a mw.Uri object
		if ( setsUrl ) {
			defaults.ajax.url = String( defaults.ajax.url );
		}
		if ( defaults.useUS === undefined ) {
			defaults.useUS = !setsUrl;
		}

		this.defaults = defaults;
		this.requests = [];
	};

	mw.Api.prototype = {
		/**
		 * Abort all unfinished requests issued by this Api object.
		 *
		 * @method
		 */
		abort: function () {
			this.requests.forEach( function ( request ) {
				if ( request ) {
					request.abort();
				}
			} );
		},

		/**
		 * Perform API get request
		 *
		 * @param {Object} parameters
		 * @param {Object} [ajaxOptions]
		 * @return {jQuery.Promise}
		 */
		get: function ( parameters, ajaxOptions ) {
			ajaxOptions = ajaxOptions || {};
			ajaxOptions.type = 'GET';
			return this.ajax( parameters, ajaxOptions );
		},

		/**
		 * Perform API post request
		 *
		 * @param {Object} parameters
		 * @param {Object} [ajaxOptions]
		 * @return {jQuery.Promise}
		 */
		post: function ( parameters, ajaxOptions ) {
			ajaxOptions = ajaxOptions || {};
			ajaxOptions.type = 'POST';
			return this.ajax( parameters, ajaxOptions );
		},

		/**
		 * Massage parameters from the nice format we accept into a format suitable for the API.
		 *
		 * NOTE: A value of undefined/null in an array will be represented by Array#join()
		 * as the empty string. Should we filter silently? Warn? Leave as-is?
		 *
		 * @private
		 * @param {Object} parameters (modified in-place)
		 * @param {boolean} useUS Whether to use U+001F when joining multi-valued parameters.
		 */
		preprocessParameters: function ( parameters, useUS ) {
			var key;
			// Handle common MediaWiki API idioms for passing parameters
			for ( key in parameters ) {
				// Multiple values are pipe-separated
				if ( Array.isArray( parameters[ key ] ) ) {
					if ( !useUS || parameters[ key ].join( '' ).indexOf( '|' ) === -1 ) {
						parameters[ key ] = parameters[ key ].join( '|' );
					} else {
						parameters[ key ] = '\x1f' + parameters[ key ].join( '\x1f' );
					}
				} else if ( parameters[ key ] === false || parameters[ key ] === undefined ) {
					// Boolean values are only false when not given at all
					delete parameters[ key ];
				}
			}
		},

		/**
		 * Perform the API call.
		 *
		 * @param {Object} parameters
		 * @param {Object} [ajaxOptions]
		 * @return {jQuery.Promise} Done: API response data and the jqXHR object.
		 *  Fail: Error code
		 */
		ajax: function ( parameters, ajaxOptions ) {
			var token, requestIndex,
				api = this,
				apiDeferred = $.Deferred(),
				xhr, key, formData;

			parameters = $.extend( {}, this.defaults.parameters, parameters );
			ajaxOptions = $.extend( {}, this.defaults.ajax, ajaxOptions );

			// Ensure that token parameter is last (per [[mw:API:Edit#Token]]).
			if ( parameters.token ) {
				token = parameters.token;
				delete parameters.token;
			}

			this.preprocessParameters( parameters, this.defaults.useUS );

			// If multipart/form-data has been requested and emulation is possible, emulate it
			if (
				ajaxOptions.type === 'POST' &&
				window.FormData &&
				ajaxOptions.contentType === 'multipart/form-data'
			) {

				formData = new FormData();

				for ( key in parameters ) {
					formData.append( key, parameters[ key ] );
				}
				// If we extracted a token parameter, add it back in.
				if ( token ) {
					formData.append( 'token', token );
				}

				ajaxOptions.data = formData;

				// Prevent jQuery from mangling our FormData object
				ajaxOptions.processData = false;
				// Prevent jQuery from overriding the Content-Type header
				ajaxOptions.contentType = false;
			} else {
				// This works because jQuery accepts data as a query string or as an Object
				ajaxOptions.data = $.param( parameters );
				// If we extracted a token parameter, add it back in.
				if ( token ) {
					ajaxOptions.data += '&token=' + encodeURIComponent( token );
				}

				if ( ajaxOptions.contentType === 'multipart/form-data' ) {
					// We were asked to emulate but can't, so drop the Content-Type header, otherwise
					// it'll be wrong and the server will fail to decode the POST body
					delete ajaxOptions.contentType;
				}
			}

			// Make the AJAX request
			xhr = $.ajax( ajaxOptions )
				// If AJAX fails, reject API call with error code 'http'
				// and details in second argument.
				.fail( function ( jqXHR, textStatus, exception ) {
					apiDeferred.reject( 'http', {
						xhr: jqXHR,
						textStatus: textStatus,
						exception: exception
					} );
				} )
				// AJAX success just means "200 OK" response, also check API error codes
				.done( function ( result, textStatus, jqXHR ) {
					var code;
					if ( result === undefined || result === null || result === '' ) {
						apiDeferred.reject( 'ok-but-empty',
							'OK response but empty result (check HTTP headers?)',
							result,
							jqXHR
						);
					} else if ( result.error ) {
						// errorformat=bc
						code = result.error.code === undefined ? 'unknown' : result.error.code;
						apiDeferred.reject( code, result, result, jqXHR );
					} else if ( result.errors ) {
						// errorformat!=bc
						code = result.errors[ 0 ].code === undefined ? 'unknown' : result.errors[ 0 ].code;
						apiDeferred.reject( code, result, result, jqXHR );
					} else {
						apiDeferred.resolve( result, jqXHR );
					}
				} );

			requestIndex = this.requests.length;
			this.requests.push( xhr );
			xhr.always( function () {
				api.requests[ requestIndex ] = null;
			} );
			// Return the Promise
			return apiDeferred.promise( { abort: xhr.abort } ).fail( function ( code, details ) {
				if ( !( code === 'http' && details && details.textStatus === 'abort' ) ) {
					mw.log( 'mw.Api error: ', code, details );
				}
			} );
		},

		/**
		 * Post to API with specified type of token. If we have no token, get one and try to post.
		 * If we have a cached token try using that, and if it fails, blank out the
		 * cached token and start over. For example to change an user option you could do:
		 *
		 *     new mw.Api().postWithToken( 'csrf', {
		 *         action: 'options',
		 *         optionname: 'gender',
		 *         optionvalue: 'female'
		 *     } );
		 *
		 * @param {string} tokenType The name of the token, like options or edit.
		 * @param {Object} params API parameters
		 * @param {Object} [ajaxOptions]
		 * @return {jQuery.Promise} See #post
		 * @since 1.22
		 */
		postWithToken: function ( tokenType, params, ajaxOptions ) {
			var api = this,
				assertParams = {
					assert: params.assert,
					assertuser: params.assertuser
				},
				abortedPromise = $.Deferred().reject( 'http',
					{ textStatus: 'abort', exception: 'abort' } ).promise(),
				abortable,
				aborted;

			return api.getToken( tokenType, assertParams ).then( function ( token ) {
				params.token = token;
				// Request was aborted while token request was running, but we
				// don't want to unnecessarily abort token requests, so abort
				// a fake request instead
				if ( aborted ) {
					return abortedPromise;
				}

				return ( abortable = api.post( params, ajaxOptions ) ).catch(
					// Error handler
					function ( code ) {
						if ( code === 'badtoken' ) {
							api.badToken( tokenType );
							// Try again, once
							params.token = undefined;
							abortable = null;
							return api.getToken( tokenType, assertParams ).then( function ( t ) {
								params.token = t;
								if ( aborted ) {
									return abortedPromise;
								}

								return ( abortable = api.post( params, ajaxOptions ) );
							} );
						}

						// Let caller handle the error code
						return $.Deferred().rejectWith( this, arguments );
					}
				);
			} ).promise( { abort: function () {
				if ( abortable ) {
					abortable.abort();
				} else {
					aborted = true;
				}
			} } );
		},

		/**
		 * Get a token for a certain action from the API.
		 *
		 * @since 1.22
		 * @param {string} type Token type
		 * @param {Object|string} [additionalParams] Additional parameters for the API (since 1.35).
		 *   When given a string, it's treated as the 'assert' parameter (since 1.25).
		 * @return {jQuery.Promise} Received token.
		 */
		getToken: function ( type, additionalParams ) {
			var apiPromise, promiseGroup, d, reject;
			type = mapLegacyToken( type );
			promiseGroup = promises[ this.defaults.ajax.url ];
			d = promiseGroup && promiseGroup[ type + 'Token' ];

			if ( typeof additionalParams === 'string' ) {
				additionalParams = { assert: additionalParams };
			}

			if ( !promiseGroup ) {
				promiseGroup = promises[ this.defaults.ajax.url ] = {};
			}

			if ( !d ) {
				apiPromise = this.get( $.extend( {
					action: 'query',
					meta: 'tokens',
					type: type
				}, additionalParams ) );
				reject = function () {
					// Clear promise. Do not cache errors.
					delete promiseGroup[ type + 'Token' ];

					// Let caller handle the error code
					return $.Deferred().rejectWith( this, arguments );
				};
				d = apiPromise
					.then( function ( res ) {
						if ( !res.query ) {
							return reject( 'query-missing', res );
						}
						// If token type is unknown, it is omitted from the response
						if ( !res.query.tokens[ type + 'token' ] ) {
							return $.Deferred().reject( 'token-missing', res );
						}
						return res.query.tokens[ type + 'token' ];
					}, reject )
					// Attach abort handler
					.promise( { abort: apiPromise.abort } );

				// Store deferred now so that we can use it again even if it isn't ready yet
				promiseGroup[ type + 'Token' ] = d;
			}

			return d;
		},

		/**
		 * Indicate that the cached token for a certain action of the API is bad.
		 *
		 * Call this if you get a 'badtoken' error when using the token returned by #getToken.
		 * You may also want to use #postWithToken instead, which invalidates bad cached tokens
		 * automatically.
		 *
		 * @param {string} type Token type
		 * @since 1.26
		 */
		badToken: function ( type ) {
			var promiseGroup = promises[ this.defaults.ajax.url ];

			type = mapLegacyToken( type );
			if ( promiseGroup ) {
				delete promiseGroup[ type + 'Token' ];
			}
		},

		/**
		 * Given an API response indicating an error, get a jQuery object containing a human-readable
		 * error message that you can display somewhere on the page.
		 *
		 * For better quality of error messages, it's recommended to use the following options in your
		 * API queries:
		 *
		 *     errorformat: 'html',
		 *     errorlang: mw.config.get( 'wgUserLanguage' ),
		 *     errorsuselocal: true,
		 *
		 * Error messages, particularly for editing pages, may consist of multiple paragraphs of text.
		 * Your user interface should have enough space for that.
		 *
		 * Example usage:
		 *
		 *     var api = new mw.Api();
		 *     // var title = 'Test valid title';
		 *     var title = 'Test invalid title <>';
		 *     api.postWithToken( 'watch', {
		 *       action: 'watch',
		 *       title: title
		 *     } ).then( function ( data ) {
		 *       mw.notify( 'Success!' );
		 *     }, function ( code, data ) {
		 *       mw.notify( api.getErrorMessage( data ), { type: 'error' } );
		 *     } );
		 *
		 * @param {Object} data API response indicating an error
		 * @return {jQuery} Error messages, each wrapped in a `<div>`
		 */
		getErrorMessage: function ( data ) {
			if (
				data === undefined || data === null || data === '' ||
				// The #ajax method returns the data like this, it's not my fault...
				data === 'OK response but empty result (check HTTP headers?)'
			) {
				// Server failed so horribly it did not even set a HTTP error status
				return $( '<div>' ).append( mw.message( 'api-clientside-error-invalidresponse' ).parseDom() );

			} else if ( data.xhr ) {
				if ( data.textStatus === 'timeout' ) {
					// Hit the timeout (as defined above in defaultOptions)
					return $( '<div>' ).append( mw.message( 'api-clientside-error-timeout' ).parseDom() );
				} else if ( data.textStatus === 'abort' ) {
					// Request cancelled by calling the abort() method on the promise
					return $( '<div>' ).append( mw.message( 'api-clientside-error-aborted' ).parseDom() );
				} else if ( data.textStatus === 'parsererror' ) {
					// Server returned invalid JSON
					// data.exception is probably a SyntaxError exception
					return $( '<div>' ).append( mw.message( 'api-clientside-error-invalidresponse' ).parseDom() );
				} else if ( data.xhr.status ) {
					// Server HTTP error
					// data.exception is probably the HTTP "reason phrase", e.g. "Internal Server Error"
					return $( '<div>' ).append( mw.message( 'api-clientside-error-http', data.xhr.status ).parseDom() );
				} else {
					// We don't know the status of the HTTP request. Common causes include (we have no way
					// to distinguish these): user losing their network connection (request wasn't even sent),
					// misconfigured CORS for cross-wiki queries.
					return $( '<div>' ).append( mw.message( 'api-clientside-error-noconnect' ).parseDom() );
				}

			} else if ( data.error ) {
				// errorformat: 'bc' (or not specified)
				return $( '<div>' ).text( data.error.info );

			} else if ( data.errors ) {
				// errorformat: 'html'
				return $( data.errors.map( function ( err ) {
					// formatversion: 1 / 2
					var $node = $( '<div>' ).html( err[ '*' ] || err.html );
					return $node[ 0 ];
				} ) );

			} else {
				// Server returned some valid but bogus JSON that probably doesn't even come from our API,
				// or this method was called incorrectly (e.g. with a successful response)
				mw.log.warn( 'mw.Api#getErrorMessage could not handle the response:', data );
				return $( '<div>' ).append( mw.message( 'api-clientside-error-invalidresponse' ).parseDom() );
			}
		}
	};

}() );
