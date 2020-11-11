/**
 * This plugin provides a way to build a wiki-text editing user interface around a textarea.
 *
 * @example To initialize without any modules:
 *     $( 'div#edittoolbar' ).wikiEditor();
 *
 * @example To initialize with one or more modules, or to add modules after it's already been initialized:
 *     $( 'textarea#wpTextbox1' ).wikiEditor( 'addModule', 'toolbar', { ... config ... } );
 *
 */
( function ( $, mw ) {

	var hasOwn = Object.prototype.hasOwnProperty,

		/**
		 * Array of language codes.
		 */
		fallbackChain = ( function () {
			var isRTL = $( 'body' ).hasClass( 'rtl' ),
				chain = mw.language.getFallbackLanguageChain();

			// Do not fallback to 'en'
			if ( chain.length >= 2 && !/^en-/.test( chain[ chain.length - 2 ] ) ) {
				chain.pop();
			}
			if ( isRTL ) {
				chain.push( 'default-rtl' );
			}
			chain.push( 'default' );
			return chain;
		}() );

	/**
	 * Global static object for wikiEditor that provides generally useful functionality to all modules and contexts.
	 */
	$.wikiEditor = {
		/**
		 * For each module that is loaded, static code shared by all instances is loaded into this object organized by
		 * module name. The existence of a module in this object only indicates the module is available. To check if a
		 * module is in use by a specific context check the context.modules object.
		 */
		modules: {},

		/**
		 * A context can be extended, such as adding iframe support, on a per-wikiEditor instance basis.
		 */
		extensions: {},

		/**
		 * In some cases like with the iframe's HTML file, it's convenient to have a lookup table of all instances of the
		 * WikiEditor. Each context contains an instance field which contains a key that corresponds to a reference to the
		 * textarea which the WikiEditor was build around. This way, by passing a simple integer you can provide a way back
		 * to a specific context.
		 */
		instances: [],

		/**
		 * Path to images - this is a bit messy, and it would need to change if this code (and images) gets moved into the
		 * core - or anywhere for that matter...
		 */
		imgPath: mw.config.get( 'wgExtensionAssetsPath' ) + '/WikiEditor/modules/images/',

		/**
		 * Checks if the client supports WikiEditor.
		 *
		 * Since 1.31 this check is deprecated and can be skipped as all browsers
		 * which are served JS by MediaWiki support WikiEditor.
		 *
		 * @deprecated since 1.31
		 * @return {boolean}
		 */
		isSupported: function () {
			mw.log.warn( '$.wikiEditor.isSupported is deprecated.' );
			return true;
		},

		/**
		 * Checks if a module has a specific requirement
		 *
		 * @param {Object} module Module object
		 * @param {string} requirement String identifying requirement
		 * @return {boolean}
		 */
		isRequired: function ( module, requirement ) {
			var req;
			if ( typeof module.req !== 'undefined' ) {
				for ( req in module.req ) {
					if ( module.req[ req ] === requirement ) {
						return true;
					}
				}
			}
			return false;
		},

		/**
		 * Provides a way to extract messages from objects. Wraps a mw.message( ... ).text() call.
		 *
		 * @param {Object} object Object to extract messages from
		 * @param {string} property String of name of property which contains the message. This should be the base name of the
		 * property, which means that in the case of the object { this: 'that', fooMsg: 'bar' }, passing property as 'this'
		 * would return the raw text 'that', while passing property as 'foo' would return the internationalized message
		 * with the key 'bar'.
		 * @return {string}
		 */
		autoMsg: function ( object, property ) {
			var i, p;
			// Accept array of possible properties, of which the first one found will be used
			if ( typeof property === 'object' ) {
				for ( i in property ) {
					if ( property[ i ] in object || property[ i ] + 'Msg' in object ) {
						property = property[ i ];
						break;
					}
				}
			}
			if ( property in object ) {
				return object[ property ];
			} else if ( property + 'Msg' in object ) {
				p = object[ property + 'Msg' ];
				if ( Array.isArray( p ) && p.length >= 2 ) {
					return mw.message.apply( mw.message, p ).text();
				} else {
					return mw.message( p ).text();
				}
			} else {
				return '';
			}
		},

		/**
		 * Provides a way to extract a property of an object in a certain language, falling back on the property keyed as
		 * 'default' or 'default-rtl'. If such key doesn't exist, the object itself is considered the actual value, which
		 * should ideally be the case so that you may use a string or object of any number of strings keyed by language
		 * with a default.
		 *
		 * @param {Object} object Object to extract property from
		 * @return {Object}
		 */
		autoLang: function ( object ) {
			var i, key;

			for ( i = 0; i < fallbackChain.length; i++ ) {
				key = fallbackChain[ i ];
				if ( hasOwn.call( object, key ) ) {
					return object[ key ];
				}
			}
			return object;
		},

		/**
		 * Provides a way to extract the path of an icon in a certain language, automatically appending a version number for
		 * caching purposes and prepending an image path when icon paths are relative.
		 *
		 * @param {Object} icon Icon object from e.g. toolbar config
		 * @param {string} path Default icon path, defaults to $.wikiEditor.imgPath
		 * @return {Object}
		 */
		autoIcon: function ( icon, path ) {
			var i, key, src;

			path = path || $.wikiEditor.imgPath;

			for ( i = 0; i < fallbackChain.length; i++ ) {
				key = fallbackChain[ i ];
				if ( icon && hasOwn.call( icon, key ) ) {
					src = icon[ key ];
					// Prepend path if src is not absolute
					if ( src.substr( 0, 7 ) !== 'http://' && src.substr( 0, 8 ) !== 'https://' && src[ 0 ] !== '/' ) {
						src = path + src;
					}
					return src + '?' + mw.loader.getVersion( 'jquery.wikiEditor' );
				}
			}
			return icon;
		}
	};

	/**
	 * jQuery plugin that provides a way to initialize a wikiEditor instance on a textarea.
	 *
	 * @return {jQuery}
	 */
	$.fn.wikiEditor = function () {
		var context, hasFocus, cursorPos,
			args, modules, module, e, call;

		/* Initialization */

		// The wikiEditor context is stored in the element's data, so when this function gets called again we can pick up right
		// where we left off
		context = $( this ).data( 'wikiEditor-context' );
		// On first call, we need to set things up, but on all following calls we can skip right to the API handling
		if ( !context || typeof context === 'undefined' ) {

			// Star filling the context with useful data - any jQuery selections, as usual should be named with a preceding $
			context = {
				// Reference to the textarea element which the wikiEditor is being built around
				$textarea: $( this ),
				// Container for any number of mutually exclusive views that are accessible by tabs
				views: {},
				// Container for any number of module-specific data - only including data for modules in use on this context
				modules: {},
				// General place to shove bits of data into
				data: {},
				// Unique numeric ID of this instance used both for looking up and differentiating instances of wikiEditor
				instance: $.wikiEditor.instances.push( $( this ) ) - 1,
				// Saved selection state for old IE (<=10)
				savedSelection: null,
				// List of extensions active on this context
				extensions: []
			};

			/**
			 * Externally Accessible API
			 *
			 * These are available using calls to $( selection ).wikiEditor( call, data ) where selection is a jQuery selection
			 * of the textarea that the wikiEditor instance was built around.
			 */

			context.api = {
				/*!
				 * Activates a module on a specific context with optional configuration data.
				 *
				 * @param data Either a string of the name of a module to add without any additional configuration parameters,
				 * or an object with members keyed with module names and valued with configuration objects.
				 */
				addModule: function ( context, data ) {
					var module, call,
						modules = {};
					if ( typeof data === 'string' ) {
						modules[ data ] = {};
					} else if ( typeof data === 'object' ) {
						modules = data;
					}
					for ( module in modules ) {
						// Check for the existence of an available module with a matching name and a create function
						if ( typeof module === 'string' && typeof $.wikiEditor.modules[ module ] !== 'undefined' ) {
							// Extend the context's core API with this module's own API calls
							if ( 'api' in $.wikiEditor.modules[ module ] ) {
								for ( call in $.wikiEditor.modules[ module ].api ) {
									// Modules may not overwrite existing API functions - first come, first serve
									if ( !( call in context.api ) ) {
										context.api[ call ] = $.wikiEditor.modules[ module ].api[ call ];
									}
								}
							}
							// Activate the module on this context
							if ( 'fn' in $.wikiEditor.modules[ module ] &&
								'create' in $.wikiEditor.modules[ module ].fn &&
								typeof context.modules[ module ] === 'undefined'
							) {
								// Add a place for the module to put it's own stuff
								context.modules[ module ] = {};
								// Tell the module to create itself on the context
								$.wikiEditor.modules[ module ].fn.create( context, modules[ module ] );
							}
						}
					}
				}
			};

			/**
			 * Event Handlers
			 *
			 * These act as filters returning false if the event should be ignored or returning true if it should be passed
			 * on to all modules. This is also where we can attach some extra information to the events.
			 */

			context.evt = {
				/* Empty until extensions add some; see jquery.wikiEditor.iframe.js for examples. */
			};

			/* Internal Functions */

			context.fn = {
				/**
				 * Executes core event filters as well as event handlers provided by modules.
				 *
				 * @param {string} name
				 * @param {Object} event
				 * @return {boolean}
				 */
				trigger: function ( name, event ) {
					var returnFromModules, module, ret;

					// Event is an optional argument, but from here on out, at least the type field should be dependable
					if ( typeof event === 'undefined' ) {
						event = { type: 'custom' };
					}
					// Ensure there's a place for extra information to live
					if ( typeof event.data === 'undefined' ) {
						event.data = {};
					}

					// Allow filtering to occur
					if ( name in context.evt ) {
						if ( !context.evt[ name ]( event ) ) {
							return false;
						}
					}
					returnFromModules = null; // they return null by default
					// Pass the event around to all modules activated on this context

					for ( module in context.modules ) {
						if (
							module in $.wikiEditor.modules &&
							'evt' in $.wikiEditor.modules[ module ] &&
							name in $.wikiEditor.modules[ module ].evt
						) {
							ret = $.wikiEditor.modules[ module ].evt[ name ]( context, event );
							if ( ret !== null ) {
								// if 1 returns false, the end result is false
								if ( returnFromModules === null ) {
									returnFromModules = ret;
								} else {
									returnFromModules = returnFromModules && ret;
								}
							}
						}
					}
					if ( returnFromModules !== null ) {
						return returnFromModules;
					} else {
						return true;
					}
				},

				/**
				 * Adds a button to the UI
				 *
				 * @param {Object} options
				 * @return {jQuery}
				 */
				addButton: function ( options ) {
					// Ensure that buttons and tabs are visible
					context.$controls.show();
					context.$buttons.show();
					return $( '<button>' )
						.text( $.wikiEditor.autoMsg( options, 'caption' ) )
						.click( options.action )
						.appendTo( context.$buttons );
				},

				/**
				 * Adds a view to the UI, which is accessed using a set of tabs. Views are mutually exclusive and by default a
				 * wikitext view will be present. Only when more than one view exists will the tabs will be visible.
				 *
				 * @param {Object} options
				 * @return {jQuery}
				 */
				addView: function ( options ) {
					// Adds a tab
					function addTab( options ) {
						// Ensure that buttons and tabs are visible
						context.$controls.show();
						context.$tabs.show();
						// Return the newly appended tab
						return $( '<div>' )
							.attr( 'rel', 'wikiEditor-ui-view-' + options.name )
							.addClass( context.view === options.name ? 'current' : null )
							.append( $( '<a>' )
								.attr( 'href', '#' )
								.mousedown( function () {
									// No dragging!
									return false;
								} )
								.click( function ( event ) {
									context.$ui.find( '.wikiEditor-ui-view' ).hide();
									context.$ui.find( '.' + $( this ).parent().attr( 'rel' ) ).show();
									context.$tabs.find( 'div' ).removeClass( 'current' );
									$( this ).parent().addClass( 'current' );
									$( this ).blur();
									if ( 'init' in options && typeof options.init === 'function' ) {
										options.init( context );
									}
									event.preventDefault();
									return false;
								} )
								.text( $.wikiEditor.autoMsg( options, 'title' ) )
							)
							.appendTo( context.$tabs );
					}
					// Automatically add the previously not-needed wikitext tab
					if ( !context.$tabs.children().length ) {
						addTab( { name: 'wikitext', titleMsg: 'wikieditor-wikitext-tab' } );
					}
					// Add the tab for the view we were actually asked to add
					addTab( options );
					// Return newly appended view
					return $( '<div>' )
						.addClass( 'wikiEditor-ui-view wikiEditor-ui-view-' + options.name )
						.hide()
						.appendTo( context.$ui );
				},

				/**
				 * Save text selection
				 */
				saveSelection: function () {
					context.$textarea.focus();
					context.savedSelection = {
						selectionStart: context.$textarea[ 0 ].selectionStart,
						selectionEnd: context.$textarea[ 0 ].selectionEnd
					};
				},

				/**
				 * Restore text selection
				 */
				restoreSelection: function () {
					if ( context.savedSelection ) {
						context.$textarea.focus();
						context.$textarea[ 0 ].setSelectionRange( context.savedSelection.selectionStart, context.savedSelection.selectionEnd );
						context.savedSelection = null;
					}
				}
			};

			/**
			* Base UI Construction
			*
			* The UI is built from several containers, the outer-most being a div classed as "wikiEditor-ui". These containers
			* provide a certain amount of "free" layout, but in some situations procedural layout is needed, which is performed
			* as a response to the "resize" event.
			*/

			// Assemble a temporary div to place over the wikiEditor while it's being constructed
			/* Disabling our loading div for now
			var $loader = $( '<div>' )
				.addClass( 'wikiEditor-ui-loading' )
				.append( $( '<span>' + mw.msg( 'wikieditor-loading' ) + '</span>' )
					.css( 'marginTop', context.$textarea.height() / 2 ) );
			*/
			/* Preserving cursor and focus state, which will get lost due to wrapAll */
			hasFocus = context.$textarea.is( ':focus' );
			cursorPos = context.$textarea.textSelection( 'getCaretPosition', { startAndEnd: true } );
			// Encapsulate the textarea with some containers for layout
			context.$textarea
			/* Disabling our loading div for now
				.after( $loader )
				.add( $loader )
			*/
				.wrapAll( $( '<div>' ).addClass( 'wikiEditor-ui' ) )
				.wrapAll( $( '<div>' ).addClass( 'wikiEditor-ui-view wikiEditor-ui-view-wikitext' ) )
				.wrapAll( $( '<div>' ).addClass( 'wikiEditor-ui-left' ) )
				.wrapAll( $( '<div>' ).addClass( 'wikiEditor-ui-bottom' ) )
				.wrapAll( $( '<div>' ).addClass( 'wikiEditor-ui-text' ) );
			// Restore scroll position after this wrapAll (tracked by mediawiki.action.edit)
			context.$textarea.prop( 'scrollTop', $( '#wpScrolltop' ).val() );
			// Restore focus and cursor if needed
			if ( hasFocus ) {
				context.$textarea.focus();
				context.$textarea.textSelection( 'setSelection', { start: cursorPos[ 0 ], end: cursorPos[ 1 ] } );
			}

			// Get references to some of the newly created containers
			context.$ui = context.$textarea.parent().parent().parent().parent().parent();
			context.$wikitext = context.$textarea.parent().parent().parent().parent();
			// Add in tab and button containers
			context.$wikitext
				.before(
					$( '<div>' ).addClass( 'wikiEditor-ui-controls' )
						.append( $( '<div>' ).addClass( 'wikiEditor-ui-tabs' ).hide() )
						.append( $( '<div>' ).addClass( 'wikiEditor-ui-buttons' ) )
				)
				.before( $( '<div>' ).addClass( 'wikiEditor-ui-clear' ) );
			// Get references to some of the newly created containers
			context.$controls = context.$ui.find( '.wikiEditor-ui-buttons' ).hide();
			context.$buttons = context.$ui.find( '.wikiEditor-ui-buttons' );
			context.$tabs = context.$ui.find( '.wikiEditor-ui-tabs' );
			// Clear all floating after the UI
			context.$ui.after( $( '<div>' ).addClass( 'wikiEditor-ui-clear' ) );
			// Attach a right container
			context.$wikitext.append( $( '<div>' ).addClass( 'wikiEditor-ui-right' ) );
			context.$wikitext.append( $( '<div>' ).addClass( 'wikiEditor-ui-clear' ) );
			// Attach a top container to the left pane
			context.$wikitext.find( '.wikiEditor-ui-left' ).prepend( $( '<div>' ).addClass( 'wikiEditor-ui-top' ) );
			// Setup the initial view
			context.view = 'wikitext';
			// Trigger the "resize" event anytime the window is resized
			$( window ).resize( function ( event ) {
				context.fn.trigger( 'resize', event );
			} );
		}

		/* API Execution */

		// Since javascript gives arguments as an object, we need to convert them so they can be used more easily
		args = $.makeArray( arguments );

		// Dynamically setup core extensions for modules that are required
		if ( args[ 0 ] === 'addModule' && typeof args[ 1 ] !== 'undefined' ) {
			modules = args[ 1 ];
			if ( typeof modules !== 'object' ) {
				modules = {};
				modules[ args[ 1 ] ] = '';
			}
			for ( module in modules ) {
				if ( module in $.wikiEditor.modules ) {
					// Activate all required core extensions on context
					for ( e in $.wikiEditor.extensions ) {
						if (
							$.wikiEditor.isRequired( $.wikiEditor.modules[ module ], e ) &&
							$.inArray( e, context.extensions ) === -1
						) {
							context.extensions[ context.extensions.length ] = e;
							$.wikiEditor.extensions[ e ]( context );
						}
					}
					break;
				}
			}
		}

		// There would need to be some arguments if the API is being called
		if ( args.length > 0 ) {
			// Handle API calls
			call = args.shift();
			if ( call in context.api ) {
				context.api[ call ]( context, typeof args[ 0 ] === 'undefined' ? {} : args[ 0 ] );
			}
		}

		// Store the context for next time, and support chaining
		return $( this ).data( 'wikiEditor-context', context );

	};

}( jQuery, mediaWiki ) );
