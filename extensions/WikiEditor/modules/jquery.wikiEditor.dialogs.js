/**
 * Dialog Module for wikiEditor
 */
( function ( $, mw ) {

	$.wikiEditor.modules.dialogs = {

		/**
		 * Compatibility map
		 */
		browsers: {
			// Left-to-right languages
			ltr: {
				msie: [ [ '>=', 9 ] ],
				firefox: [ [ '>=', 4 ] ],
				opera: [ [ '>=', '10.5' ] ],
				safari: [ [ '>=', 5 ] ],
				chrome: [ [ '>=', 5 ] ]
			},
			// Right-to-left languages
			rtl: {
				msie: [ [ '>=', 9 ] ],
				firefox: [ [ '>=', 4 ] ],
				opera: [ [ '>=', '10.5' ] ],
				safari: [ [ '>=', 5 ] ],
				chrome: [ [ '>=', 5 ] ]
			}
		},

		/**
		 * API accessible functions
		 */
		api: {
			addDialog: function ( context, data ) {
				$.wikiEditor.modules.dialogs.fn.create( context, data );
			},
			openDialog: function ( context, module ) {
				var mod, $dialog;
				if ( module in $.wikiEditor.modules.dialogs.modules ) {
					mod = $.wikiEditor.modules.dialogs.modules[ module ];
					$dialog = $( '#' + mod.id );
					if ( $dialog.length === 0 ) {
						$.wikiEditor.modules.dialogs.fn.reallyCreate( context, mod, module );
						$dialog = $( '#' + mod.id );
					}

					// Workaround for bug in jQuery UI: close button in top right retains focus
					$dialog.closest( '.ui-dialog' )
						.find( '.ui-dialog-titlebar-close' )
						.removeClass( 'ui-state-focus' );

					$dialog.dialog( 'open' );
				}
			},
			closeDialog: function ( context, module ) {
				if ( module in $.wikiEditor.modules.dialogs.modules ) {
					$( '#' + $.wikiEditor.modules.dialogs.modules[ module ].id ).dialog( 'close' );
				}
			}
		},

		/**
		 * Internally used functions
		 */
		fn: {
			/**
			 * Creates a dialog module within a wikiEditor
			 *
			 * @param {Object} context Context object of editor to create module in
			 * @param {Object} config Configuration object to create module from
			 */
			create: function ( context, config ) {
				var mod, module, filtered, i, $existingDialog;

				// Defer building of modules, unless they require immediate creation
				for ( mod in config ) {
					module = config[ mod ];
					// Only create the dialog if it isn't filtered and doesn't exist yet
					filtered = false;
					if ( typeof module.filters !== 'undefined' ) {
						for ( i = 0; i < module.filters.length; i++ ) {
							if ( $( module.filters[ i ] ).length === 0 ) {
								filtered = true;
								break;
							}
						}
					}
					// If the dialog already exists, but for another textarea, simply remove it
					$existingDialog = $( '#' + module.id );
					if ( $existingDialog.length > 0 && $existingDialog.data( 'context' ).$textarea !== context.$textarea ) {
						$existingDialog.remove();
					}
					// Re-select from the DOM, we might have removed the dialog just now
					$existingDialog = $( '#' + module.id );
					if ( !filtered && $existingDialog.length === 0 ) {
						$.wikiEditor.modules.dialogs.modules[ mod ] = module;
						context.$textarea.trigger( 'wikiEditor-dialogs-setup-' + mod );
						// If this dialog requires immediate creation, create it now
						if ( typeof module.immediateCreate !== 'undefined' && module.immediateCreate ) {
							$.wikiEditor.modules.dialogs.fn.reallyCreate( context, module, mod );
						}
					}
				}
			},

			/**
			 * Build the actual dialog. This done on-demand rather than in create()
			 *
			 * @param {Object} context Context object of editor dialog belongs to
			 * @param {Object} module Dialog module object
			 * @param {string} name Dialog name (key in $.wikiEditor.modules.dialogs.modules)
			 */
			reallyCreate: function ( context, module, name ) {
				var msg, $dialogDiv, $content,
					configuration = module.dialog;
				// Add some stuff to configuration
				configuration.bgiframe = true;
				configuration.autoOpen = false;
				// By default our dialogs are modal, unless explicitly defined in their specific configuration.
				if ( typeof configuration.modal === 'undefined' ) {
					configuration.modal = true;
				}
				configuration.title = $.wikiEditor.autoMsg( module, 'title' );
				// Transform messages in keys
				// Stupid JS won't let us do stuff like
				// foo = { mw.msg( 'bar' ): baz }
				configuration.newButtons = {};
				for ( msg in configuration.buttons ) {
					configuration.newButtons[ mw.msg( msg ) ] = configuration.buttons[ msg ];
				}
				configuration.buttons = configuration.newButtons;
				if ( module.htmlTemplate ) {
					$content = mw.template.get( 'ext.wikiEditor', module.htmlTemplate ).render();
				} else if ( module.html instanceof jQuery ) {
					$content = module.html;
				} else {
					$content = $( $.parseHTML( module.html ) );
				}
				// Create the dialog <div>
				$dialogDiv = $( '<div>' )
					.attr( 'id', module.id )
					.append( $content )
					.data( 'context', context )
					.appendTo( $( 'body' ) )
					.each( module.init )
					.dialog( configuration );
				// Set tabindexes on buttons added by .dialog()
				$.wikiEditor.modules.dialogs.fn.setTabindexes( $dialogDiv.closest( '.ui-dialog' )
					.find( 'button' ).not( '[tabindex]' ) );
				if ( !( 'resizeme' in module ) || module.resizeme ) {
					$dialogDiv
						.on( 'dialogopen', $.wikiEditor.modules.dialogs.fn.resize )
						.find( '.ui-tabs' ).on( 'tabsshow', function () {
							$( this ).closest( '.ui-dialog-content' ).each(
								$.wikiEditor.modules.dialogs.fn.resize );
						} );
				}
				$dialogDiv.on( 'dialogclose', function () {
					context.fn.restoreSelection();
				} );

				// Let the outside world know we set up this dialog
				context.$textarea.trigger( 'wikiEditor-dialogs-loaded-' + name );
			},

			/**
			 * Resize a dialog so its contents fit
			 *
			 * Usage: dialog.each( resize ); or dialog.on( 'blah', resize );
			 * NOTE: This function assumes $.ui.dialog has already been loaded
			 */
			resize: function () {
				var oldWS, thisWidth, wrapperWidth,
					$wrapper = $( this ).closest( '.ui-dialog' ),
					oldWidth = $wrapper.width(),
					// Make sure elements don't wrapped so we get an accurate idea of whether they really fit. Also temporarily show
					// hidden elements. Work around jQuery bug where <div style="display: inline;"/> inside a dialog is both
					// :visible and :hidden
					$oldHidden = $( this ).find( '*' ).not( ':visible' );

				// Save the style attributes of the hidden elements to restore them later. Calling hide() after show() messes up
				// for elements hidden with a class
				$oldHidden.each( function () {
					$( this ).data( 'oldstyle', $( this ).attr( 'style' ) );
				} );
				$oldHidden.show();
				oldWS = $( this ).css( 'white-space' );
				$( this ).css( 'white-space', 'nowrap' );
				if ( $wrapper.width() <= $( this ).get( 0 ).scrollWidth ) {
					thisWidth = $( this ).data( 'thisWidth' ) ? $( this ).data( 'thisWidth' ) : 0;
					thisWidth = Math.max( $( this ).get( 0 ).width, thisWidth );
					$( this ).width( thisWidth );
					$( this ).data( 'thisWidth', thisWidth );
					wrapperWidth = $( this ).data( 'wrapperWidth' ) ? $( this ).data( 'wrapperWidth' ) : 0;
					wrapperWidth = Math.max( $wrapper.get( 0 ).scrollWidth, wrapperWidth );
					$wrapper.width( wrapperWidth );
					$( this ).data( 'wrapperWidth', wrapperWidth );
					$( this ).dialog( { width: $wrapper.width() } );
					$wrapper.css( 'left', parseInt( $wrapper.css( 'left' ), 10 ) - ( $wrapper.width() - oldWidth ) / 2 );
				}
				$( this ).css( 'white-space', oldWS );
				$oldHidden.each( function () {
					$( this ).attr( 'style', $( this ).data( 'oldstyle' ) );
				} );
			},

			/**
			 * Set the right tabindexes on elements in a dialog
			 *
			 * @param {Object} $elements Elements to set tabindexes on. If they already have tabindexes, this function can behave a bit weird
			 */
			setTabindexes: function ( $elements ) {
				// Get the highest tab index
				var tabIndex = $( document ).lastTabIndex() + 1;
				$elements.each( function () {
					$( this ).attr( 'tabindex', tabIndex++ );
				} );
			}
		},

		// This stuff is just hanging here, perhaps we could come up with a better home for this stuff
		modules: {},

		quickDialog: function ( body, settings ) {
			$( '<div>' )
				.text( body )
				.appendTo( $( 'body' ) )
				.dialog( $.extend( {
					bgiframe: true,
					modal: true
				}, settings ) )
				.dialog( 'open' );
		}

	};

}( jQuery, mediaWiki ) );
