/**
 * Configuration of Dialog module for wikiEditor
 */
( function ( $, mw, OO ) {

	var hasOwn = Object.prototype.hasOwnProperty;

	$.wikiEditor.modules.dialogs.config = {

		replaceIcons: function ( $textarea ) {
			$textarea
				.wikiEditor( 'addToToolbar', {
					section: 'main',
					group: 'insert',
					tools: {
						link: {
							labelMsg: 'wikieditor-toolbar-tool-link',
							type: 'button',
							oouiIcon: 'link',
							action: {
								type: 'dialog',
								module: 'insert-link'
							}
						},
						file: {
							labelMsg: 'wikieditor-toolbar-tool-file',
							type: 'button',
							oouiIcon: 'image',
							action: {
								type: 'dialog',
								module: 'insert-file'
							}
						},
						reference: {
							labelMsg: 'wikieditor-toolbar-tool-reference',
							filters: [ 'body.ns-subject' ],
							type: 'button',
							oouiIcon: 'book',
							action: {
								type: 'dialog',
								module: 'insert-reference'
							}
						}
					}
				} )
				.wikiEditor( 'addToToolbar', {
					section: 'advanced',
					group: 'insert',
					tools: {
						table: {
							labelMsg: 'wikieditor-toolbar-tool-table',
							type: 'button',
							oouiIcon: 'table',
							action: {
								type: 'dialog',
								module: 'insert-table'
							}
						}
					}
				} )
				.wikiEditor( 'addToToolbar', {
					section: 'advanced',
					groups: {
						search: {
							tools: {
								replace: {
									labelMsg: 'wikieditor-toolbar-tool-replace',
									type: 'button',
									oouiIcon: 'find',
									action: {
										type: 'dialog',
										module: 'search-and-replace'
									}
								}
							}
						}
					}
				} );
		},

		getDefaultConfig: function () {
			return { dialogs: {
				'insert-link': {
					titleMsg: 'wikieditor-toolbar-tool-link-title',
					id: 'wikieditor-toolbar-link-dialog',
					htmlTemplate: 'dialogInsertLink.html',

					init: function () {
						var api = new mw.Api();

						function isExternalLink( s ) {
							// The following things are considered to be external links:
							// * Starts with a URL protocol
							// * Starts with www.
							// All of these are potentially valid titles, and the latter two categories match about 6300
							// titles in enwiki's ns0. Out of 6.9M titles, that's 0.09%
							/* eslint-disable no-caller */
							if ( typeof arguments.callee.regex === 'undefined' ) {
								// Cache the regex
								arguments.callee.regex =
									new RegExp( '^(' + mw.config.get( 'wgUrlProtocols' ) + '|www\\.)', 'i' );
							}
							return s.match( arguments.callee.regex );
							/* eslint-enable no-caller */
						}

						// Updates the status indicator above the target link
						function updateWidget( status, reason ) {
							$( '#wikieditor-toolbar-link-int-target-status' ).children().hide();
							$( '#wikieditor-toolbar-link-int-target' ).parent()
								.removeClass(
									'status-invalid status-external status-notexists status-exists status-loading'
								);
							if ( status ) {
								$( '#wikieditor-toolbar-link-int-target-status-' + status ).show();
								$( '#wikieditor-toolbar-link-int-target' ).parent().addClass( 'status-' + status );
							}
							if ( status === 'invalid' ) {
								$( '.ui-dialog:visible .ui-dialog-buttonpane button:first' )
									.prop( 'disabled', true )
									.addClass( 'disabled' );
								if ( reason ) {
									$( '#wikieditor-toolbar-link-int-target-status-invalid' ).html( reason );
								} else {
									$( '#wikieditor-toolbar-link-int-target-status-invalid' )
										.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-invalid' ) );
								}

							} else {
								$( '.ui-dialog:visible .ui-dialog-buttonpane button:first' )
									.prop( 'disabled', false )
									.removeClass( 'disabled' );
							}
						}

						// Updates the UI to show if the page title being inputted by the user exists or not
						// accepts parameter internal for bypassing external link detection
						function updateExistence( internal ) {
							// Abort previous request
							var request = $( '#wikieditor-toolbar-link-int-target-status' ).data( 'request' ),
								target = $( '#wikieditor-toolbar-link-int-target' ).val(),
								cache = $( '#wikieditor-toolbar-link-int-target-status' ).data( 'existencecache' ),
								reasoncache = $( '#wikieditor-toolbar-link-int-target-status' ).data( 'reasoncache' );
							// ensure the internal parameter is a boolean
							if ( internal !== true ) {
								internal = false;
							}
							if ( request ) {
								request.abort();
							}
							if ( hasOwn.call( cache, target ) ) {
								updateWidget( cache[ target ], reasoncache[ target ] );
								return;
							}
							if ( target.replace( /^\s+$/, '' ) === '' ) {
								// Hide the widget when the textbox is empty
								updateWidget( false );
								return;
							}
							// If the forced internal parameter was not true, check if the target is an external link
							if ( !internal && isExternalLink( target ) ) {
								updateWidget( 'external' );
								return;
							}
							// Show loading spinner while waiting for the API to respond
							updateWidget( 'loading' );
							// Call the API to check page status, saving the request object so it can be aborted if
							// necessary.
							// This used to request a page that would show whether or not the target exists, but we can
							// also check whether it has the disambiguation property and still get existence information.
							// If the Disambiguator extension is not installed then such a property won't be set.
							$( '#wikieditor-toolbar-link-int-target-status' ).data(
								'request',
								api.get( {
									formatversion: 2,
									action: 'query',
									prop: 'pageprops',
									titles: [ target ],
									ppprop: 'disambiguation',
									errorformat: 'html',
									errorlang: mw.config.get( 'wgUserLanguage' )
								} ).done( function ( data ) {
									var status, page, reason = null;
									if ( !data.query || !data.query.pages ) {
										// This happens in some weird cases like interwiki links
										status = false;
									} else {
										page = data.query.pages[ 0 ];
										status = 'exists';
										if ( page.missing ) {
											status = 'notexists';
										} else if ( page.invalid ) {
											status = 'invalid';
											reason = page.invalidreason && page.invalidreason.html;
										} else if ( page.pageprops ) {
											status = 'disambig';
										}
									}
									// Cache the status of the link target if the force internal
									// parameter was not passed
									if ( !internal ) {
										cache[ target ] = status;
										reasoncache[ target ] = reason;
									}
									updateWidget( status, reason );
								} )
							);
						}
						$( '#wikieditor-toolbar-link-type-int, #wikieditor-toolbar-link-type-ext' ).click( function () {
							var request;
							if ( $( '#wikieditor-toolbar-link-type-ext' ).prop( 'checked' ) ) {
								// Abort previous request
								request = $( '#wikieditor-toolbar-link-int-target-status' ).data( 'request' );
								if ( request ) {
									request.abort();
								}
								updateWidget( 'external' );
							}
							if ( $( '#wikieditor-toolbar-link-type-int' ).prop( 'checked' ) ) {
								updateExistence( true );
							}
						} );
						// Set labels of tabs based on rel values
						$( this ).find( '[rel]' ).each( function () {
							$( this ).text( mw.msg( $( this ).attr( 'rel' ) ) );
						} );
						// Set tabindexes on form fields
						$.wikiEditor.modules.dialogs.fn.setTabindexes( $( this ).find( 'input' ).not( '[tabindex]' ) );
						// Setup the tooltips in the textboxes
						$( '#wikieditor-toolbar-link-int-target' )
							.data( 'tooltip', mw.msg( 'wikieditor-toolbar-tool-link-int-target-tooltip' ) );
						$( '#wikieditor-toolbar-link-int-text' )
							.data( 'tooltip', mw.msg( 'wikieditor-toolbar-tool-link-int-text-tooltip' ) );
						$( '#wikieditor-toolbar-link-int-target, #wikieditor-toolbar-link-int-text' )
							.each( function () {
								if ( $( this ).val() === '' ) {
									$( this )
										.addClass( 'wikieditor-toolbar-dialog-hint' )
										.val( $( this ).data( 'tooltip' ) )
										.data( 'tooltip-mode', true );
								}
							} )
							.on( 'focus', function () {
								if ( $( this ).val() === $( this ).data( 'tooltip' ) ) {
									$( this )
										.val( '' )
										.removeClass( 'wikieditor-toolbar-dialog-hint' )
										.data( 'tooltip-mode', false );
								}
							} )
							.on( 'change', function () {
								if ( $( this ).val() !== $( this ).data( 'tooltip' ) ) {
									$( this )
										.removeClass( 'wikieditor-toolbar-dialog-hint' )
										.data( 'tooltip-mode', false );
								}
							} )
							.on( 'blur', function () {
								if ( $( this ).val() === '' ) {
									$( this )
										.addClass( 'wikieditor-toolbar-dialog-hint' )
										.val( $( this ).data( 'tooltip' ) )
										.data( 'tooltip-mode', true );
								}
							} );

						// Automatically copy the value of the internal link page title field to the link text field unless the
						// user has changed the link text field - this is a convenience thing since most link texts are going to
						// be the the same as the page title - Also change the internal/external radio button accordingly
						$( '#wikieditor-toolbar-link-int-target' ).on( 'change keydown paste cut', function () {
							// $( this ).val() is the old value, before the keypress - Defer this until $( this ).val() has
							// been updated
							setTimeout( function () {
								if ( isExternalLink( $( '#wikieditor-toolbar-link-int-target' ).val() ) ) {
									$( '#wikieditor-toolbar-link-type-ext' ).prop( 'checked', true );
									updateWidget( 'external' );
								} else {
									$( '#wikieditor-toolbar-link-type-int' ).prop( 'checked', true );
									updateExistence();
								}
								if ( $( '#wikieditor-toolbar-link-int-text' ).data( 'untouched' ) ) {
									// eslint-disable-next-line eqeqeq
									if ( $( '#wikieditor-toolbar-link-int-target' ).val() ==
										$( '#wikieditor-toolbar-link-int-target' ).data( 'tooltip' )
									) {
										$( '#wikieditor-toolbar-link-int-text' )
											.addClass( 'wikieditor-toolbar-dialog-hint' )
											.val( $( '#wikieditor-toolbar-link-int-text' ).data( 'tooltip' ) )
											.change();
									} else {
										$( '#wikieditor-toolbar-link-int-text' )
											.val( $( '#wikieditor-toolbar-link-int-target' ).val() )
											.change();
									}
								}
							}, 0 );
						} );
						$( '#wikieditor-toolbar-link-int-text' ).on( 'change keydown paste cut', function () {
							var oldVal = $( this ).val(),
								that = this;
							setTimeout( function () {
								if ( $( that ).val() !== oldVal ) {
									$( that ).data( 'untouched', false );
								}
							}, 0 );
						} );
						// Add images to the page existence widget, which will be shown mutually exclusively to communicate if
						// the page exists, does not exist or the title is invalid (like if it contains a | character)
						$( '#wikieditor-toolbar-link-int-target-status' )
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-exists' )
								.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-exists' ) )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-notexists' )
								.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-notexists' ) )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-invalid' )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-external' )
								.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-external' ) )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-loading' )
								.attr( 'title', mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-loading' ) )
							)
							.append( $( '<div>' )
								.attr( 'id', 'wikieditor-toolbar-link-int-target-status-disambig' )
								.text( mw.msg( 'wikieditor-toolbar-tool-link-int-target-status-disambig' ) )
							)
							.data( 'existencecache', {} )
							.data( 'reasoncache', {} )
							.children().hide();

						$( '#wikieditor-toolbar-link-int-target' )
							.on( 'keyup paste cut', function () {
								var timerID;
								// Cancel the running timer if applicable
								if ( typeof $( this ).data( 'timerID' ) !== 'undefined' ) {
									clearTimeout( $( this ).data( 'timerID' ) );
								}
								// Delay fetch for a while
								// FIXME: Make 120 configurable elsewhere
								timerID = setTimeout( updateExistence, 120 );
								$( this ).data( 'timerID', timerID );
							} )
							.on( 'change', function () {
								// Cancel the running timer if applicable
								if ( typeof $( this ).data( 'timerID' ) !== 'undefined' ) {
									clearTimeout( $( this ).data( 'timerID' ) );
								}
								// Fetch right now
								updateExistence();
							} );

						// Title suggestions
						$( '#wikieditor-toolbar-link-int-target' ).data( 'suggcache', {} ).suggestions( {
							fetch: function () {
								var cache, request,
									that = this,
									title = $( this ).val();

								if ( isExternalLink( title ) || title.indexOf( '|' ) !== -1 || title === '' ) {
									$( this ).suggestions( 'suggestions', [] );
									return;
								}

								cache = $( this ).data( 'suggcache' );
								if ( hasOwn.call( cache, title ) ) {
									$( this ).suggestions( 'suggestions', cache[ title ] );
									return;
								}

								request = api.get( {
									formatversion: 2,
									action: 'opensearch',
									search: title,
									namespace: 0,
									suggest: ''
								} ).done( function ( data ) {
									cache[ title ] = data[ 1 ];
									$( that ).suggestions( 'suggestions', data[ 1 ] );
								} );
								$( this ).data( 'request', request );
							},
							cancel: function () {
								var request = $( this ).data( 'request' );
								if ( request ) {
									request.abort();
								}
							}
						} );
					},
					dialog: {
						width: 500,
						dialogClass: 'wikiEditor-toolbar-dialog',
						buttons: {
							'wikieditor-toolbar-tool-link-insert': function () {
								var match, buttons, escTarget, escText,
									that = this,
									insertText = '',
									whitespace = $( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace' ),
									target = $( '#wikieditor-toolbar-link-int-target' ).val(),
									text = $( '#wikieditor-toolbar-link-int-text' ).val();

								function escapeInternalText( s ) {
									return s.replace( /(\]{2,})/g, '<nowiki>$1</nowiki>' );
								}
								function escapeExternalTarget( s ) {
									return s.replace( / /g, '%20' )
										.replace( /\[/g, '%5B' )
										.replace( /\]/g, '%5D' );
								}
								function escapeExternalText( s ) {
									return s.replace( /(\]+)/g, '<nowiki>$1</nowiki>' );
								}
								// check if the tooltips were passed as target or text
								if ( $( '#wikieditor-toolbar-link-int-target' ).data( 'tooltip-mode' ) ) {
									target = '';
								}
								if ( $( '#wikieditor-toolbar-link-int-text' ).data( 'tooltip-mode' ) ) {
									text = '';
								}
								if ( target === '' ) {
									// eslint-disable-next-line no-alert
									alert( mw.msg( 'wikieditor-toolbar-tool-link-empty' ) );
									return;
								}
								if ( $.trim( text ) === '' ) {
									// [[Foo| ]] creates an invisible link
									// Instead, generate [[Foo|]]
									text = '';
								}
								if ( $( '#wikieditor-toolbar-link-type-int' ).is( ':checked' ) ) {
									// FIXME: Exactly how fragile is this?
									if ( $( '#wikieditor-toolbar-link-int-target-status-invalid' ).is( ':visible' ) ) {
										// Refuse to add links to invalid titles
										// eslint-disable-next-line no-alert
										alert( mw.msg( 'wikieditor-toolbar-tool-link-int-invalid' ) );
										return;
									}

									if ( target === text || !text.length ) {
										insertText = '[[' + target + ']]';
									} else {
										insertText = '[[' + target + '|' + escapeInternalText( text ) + ']]';
									}
								} else {
									target = $.trim( target );
									// Prepend http:// if there is no protocol
									if ( !target.match( /^[a-z]+:\/\/./ ) ) {
										target = 'http://' + target;
									}

									// Detect if this is really an internal link in disguise
									match = target.match( $( this ).data( 'articlePathRegex' ) );
									if ( match && !$( this ).data( 'ignoreLooksInternal' ) ) {
										buttons = {};
										buttons[ mw.msg( 'wikieditor-toolbar-tool-link-lookslikeinternal-int' ) ] =
											function () {
												$( '#wikieditor-toolbar-link-int-target' ).val( match[ 1 ] ).change();
												$( this ).dialog( 'close' );
											};
										buttons[ mw.msg( 'wikieditor-toolbar-tool-link-lookslikeinternal-ext' ) ] =
											function () {
												$( that ).data( 'ignoreLooksInternal', true );
												$( that ).closest( '.ui-dialog' ).find( 'button:first' ).click();
												$( that ).data( 'ignoreLooksInternal', false );
												$( this ).dialog( 'close' );
											};
										$.wikiEditor.modules.dialogs.quickDialog(
											mw.msg( 'wikieditor-toolbar-tool-link-lookslikeinternal', match[ 1 ] ),
											{ buttons: buttons }
										);
										return;
									}

									escTarget = escapeExternalTarget( target );
									escText = escapeExternalText( text );

									if ( escTarget === escText ) {
										insertText = escTarget;
									} else if ( text === '' ) {
										insertText = '[' + escTarget + ']';
									} else {
										insertText = '[' + escTarget + ' ' + escText + ']';
									}
								}
								// Preserve whitespace in selection when replacing
								if ( whitespace ) {
									insertText = whitespace[ 0 ] + insertText + whitespace[ 1 ];
								}
								$( this ).dialog( 'close' );
								$.wikiEditor.modules.toolbar.fn.doAction( $( this ).data( 'context' ), {
									type: 'replace',
									options: {
										pre: insertText
									}
								}, $( this ) );

								// Blank form
								$( '#wikieditor-toolbar-link-int-target, #wikieditor-toolbar-link-int-text' ).val( '' );
								$( '#wikieditor-toolbar-link-type-int, #wikieditor-toolbar-link-type-ext' )
									.prop( 'checked', false );
							},
							'wikieditor-toolbar-tool-link-cancel': function () {
								$( this ).dialog( 'close' );
							}
						},
						open: function () {
							var target, text, type, matches, context, selection,
								// Obtain the server name without the protocol. wgServer may be protocol-relative
								serverName = mw.config.get( 'wgServer' ).replace( /^(https?:)?\/\//, '' );
							// Cache the articlepath regex
							$( this ).data( 'articlePathRegex', new RegExp(
								'^https?://' + mw.RegExp.escape( serverName + mw.config.get( 'wgArticlePath' ) )
									.replace( /\\\$1/g, '(.*)' ) + '$'
							) );
							// Pre-fill the text fields based on the current selection
							context = $( this ).data( 'context' );
							selection = context.$textarea.textSelection( 'getSelection' );
							$( '#wikieditor-toolbar-link-int-target' ).focus();
							// Trigger the change event, so the link status indicator is up to date
							$( '#wikieditor-toolbar-link-int-target' ).change();
							$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [ '', '' ] );
							if ( selection !== '' ) {
								if ( ( matches = selection.match( /^(\s*)\[\[([^\]|]+)(\|([^\]|]*))?\]\](\s*)$/ ) ) ) {
									// [[foo|bar]] or [[foo]]
									target = matches[ 2 ];
									text = ( matches[ 4 ] ? matches[ 4 ] : matches[ 2 ] );
									type = 'int';
									// Preserve whitespace when replacing
									$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [ matches[ 1 ], matches[ 5 ] ] );
								} else if ( ( matches = selection.match( /^(\s*)\[([^\] ]+)( ([^\]]+))?\](\s*)$/ ) ) ) {
									// [http://www.example.com foo] or [http://www.example.com]
									target = matches[ 2 ];
									text = ( matches[ 4 ] || '' );
									type = 'ext';
									// Preserve whitespace when replacing
									$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [ matches[ 1 ], matches[ 5 ] ] );
								} else {
									// Trim any leading and trailing whitespace from the selection,
									// but preserve it when replacing
									target = text = $.trim( selection );
									if ( target.length < selection.length ) {
										$( '#wikieditor-toolbar-link-dialog' ).data( 'whitespace', [
											selection.substr( 0, selection.indexOf( target.charAt( 0 ) ) ),
											selection.substr(
												selection.lastIndexOf( target.charAt( target.length - 1 ) ) + 1
											) ]
										);
									}
								}

								// Change the value by calling val() doesn't trigger the change event, so let's do that
								// ourselves
								if ( typeof text !== 'undefined' ) {
									$( '#wikieditor-toolbar-link-int-text' ).val( text ).change();
								}
								if ( typeof target !== 'undefined' ) {
									$( '#wikieditor-toolbar-link-int-target' ).val( target ).change();
								}
								if ( typeof type !== 'undefined' ) {
									$( '#wikieditor-toolbar-link-' + type ).prop( 'checked', true );
								}
							}
							$( '#wikieditor-toolbar-link-int-text' ).data( 'untouched',
								$( '#wikieditor-toolbar-link-int-text' ).val() ===
										$( '#wikieditor-toolbar-link-int-target' ).val() ||
									$( '#wikieditor-toolbar-link-int-text' ).hasClass( 'wikieditor-toolbar-dialog-hint' )
							);
							$( '#wikieditor-toolbar-link-int-target' ).suggestions();

							// don't overwrite user's text
							if ( selection !== '' ) {
								$( '#wikieditor-toolbar-link-int-text' ).data( 'untouched', false );
							}

							$( '#wikieditor-toolbar-link-int-text, #wikiedit-toolbar-link-int-target' )
								.each( function () {
									if ( $( this ).val() === '' ) {
										$( this ).parent().find( 'label' ).show();
									}
								} );

							if ( !$( this ).data( 'dialogkeypressset' ) ) {
								$( this ).data( 'dialogkeypressset', true );
								// Execute the action associated with the first button
								// when the user presses Enter
								$( this ).closest( '.ui-dialog' ).keypress( function ( e ) {
									var button;
									if ( ( e.keyCode || e.which ) === 13 ) {
										button = $( this ).data( 'dialogaction' ) || $( this ).find( 'button:first' );
										button.click();
										e.preventDefault();
									}
								} );

								// Make tabbing to a button and pressing
								// Enter do what people expect
								$( this ).closest( '.ui-dialog' ).find( 'button' ).focus( function () {
									$( this ).closest( '.ui-dialog' ).data( 'dialogaction', this );
								} );
							}
						}
					}
				},
				'insert-reference': {
					titleMsg: 'wikieditor-toolbar-tool-reference-title',
					id: 'wikieditor-toolbar-reference-dialog',
					htmlTemplate: 'dialogInsertReference.html',
					init: function () {
						// Insert translated strings into labels
						$( this ).find( '[rel]' ).each( function () {
							$( this ).text( mw.msg( $( this ).attr( 'rel' ) ) );
						} );

					},
					dialog: {
						dialogClass: 'wikiEditor-toolbar-dialog',
						width: 590,
						buttons: {
							'wikieditor-toolbar-tool-reference-insert': function () {
								var insertText = $( '#wikieditor-toolbar-reference-text' ).val(),
									whitespace = $( '#wikieditor-toolbar-reference-dialog' ).data( 'whitespace' ),
									attributes = $( '#wikieditor-toolbar-reference-dialog' ).data( 'attributes' );
								// Close the dialog
								$( this ).dialog( 'close' );
								$.wikiEditor.modules.toolbar.fn.doAction(
									$( this ).data( 'context' ),
									{
										type: 'replace',
										options: {
											pre: whitespace[ 0 ] + '<ref' + attributes + '>',
											peri: insertText,
											post: '</ref>' + whitespace[ 1 ]
										}
									},
									$( this )
								);
								// Restore form state
								$( '#wikieditor-toolbar-reference-text' ).val( '' );
							},
							'wikieditor-toolbar-tool-reference-cancel': function () {
								$( this ).dialog( 'close' );
							}
						},
						open: function () {
							// Pre-fill the text fields based on the current selection
							var matches, text,
								context = $( this ).data( 'context' ),
								selection = context.$textarea.textSelection( 'getSelection' );
							// set focus
							$( '#wikieditor-toolbar-reference-text' ).focus();
							$( '#wikieditor-toolbar-reference-dialog' )
								.data( 'whitespace', [ '', '' ] )
								.data( 'attributes', '' );
							if ( selection !== '' ) {
								if ( ( matches = selection.match( /^(\s*)<ref([^>]*)>([^<]*)<\/ref>(\s*)$/ ) ) ) {
									text = matches[ 3 ];
									// Preserve whitespace when replacing
									$( '#wikieditor-toolbar-reference-dialog' )
										.data( 'whitespace', [ matches[ 1 ], matches[ 4 ] ] );
									$( '#wikieditor-toolbar-reference-dialog' ).data( 'attributes', matches[ 2 ] );
								} else {
									text = selection;
								}
								$( '#wikieditor-toolbar-reference-text' ).val( text );
							}
							if ( !( $( this ).data( 'dialogkeypressset' ) ) ) {
								$( this ).data( 'dialogkeypressset', true );
								// Execute the action associated with the first button
								// when the user presses Enter
								$( this ).closest( '.ui-dialog' ).keypress( function ( e ) {
									var button;
									if ( ( e.keyCode || e.which ) === 13 ) {
										button = $( this ).data( 'dialogaction' ) || $( this ).find( 'button:first' );
										button.click();
										e.preventDefault();
									}
								} );
								// Make tabbing to a button and pressing
								// Enter do what people expect
								$( this ).closest( '.ui-dialog' ).find( 'button' ).focus( function () {
									$( this ).closest( '.ui-dialog' ).data( 'dialogaction', this );
								} );
							}
						}
					}
				},
				'insert-file': {
					titleMsg: 'wikieditor-toolbar-tool-file-title',
					id: 'wikieditor-toolbar-file-dialog',
					htmlTemplate: 'dialogInsertFile.html',
					init: function () {
						var magicWordsI18N = mw.config.get( 'wgWikiEditorMagicWords' ),
							defaultMsg = mw.msg( 'wikieditor-toolbar-file-default' );
						$( this ).find( '[data-i18n-magic]' )
							.text( function () {
								return magicWordsI18N[ $( this ).attr( 'data-i18n-magic' ) ];
							} )
							.removeAttr( 'data-i18n-magic' );
						$( this ).find( '#wikieditor-toolbar-file-size' )
							.attr( 'placeholder', defaultMsg )
							// The message may be long in some languages
							.attr( 'size', defaultMsg.length );
						$( this ).find( '[rel]' )
							.text( function () {
								return mw.msg( $( this ).attr( 'rel' ) );
							} )
							.removeAttr( 'rel' );
					},
					dialog: {
						resizable: false,
						dialogClass: 'wikiEditor-toolbar-dialog',
						width: 590,
						buttons: {
							'wikieditor-toolbar-tool-file-insert': function () {
								var fileName, caption, fileFloat, fileFormat, fileSize, fileTitle,
									options, fileUse,
									hasPxRgx = /.+px$/,
									magicWordsI18N = mw.config.get( 'wgWikiEditorMagicWords' );
								fileName = $( '#wikieditor-toolbar-file-target' ).val();
								caption = $( '#wikieditor-toolbar-file-caption' ).val();
								fileFloat = $( '#wikieditor-toolbar-file-float' ).val();
								fileFormat = $( '#wikieditor-toolbar-file-format' ).val();
								fileSize = $( '#wikieditor-toolbar-file-size' ).val();
								// Append px to end to size if not already contains it
								if ( fileSize !== '' && !hasPxRgx.test( fileSize ) ) {
									fileSize += 'px';
								}
								if ( fileName !== '' ) {
									fileTitle = new mw.Title( fileName );
									// Append file namespace prefix to filename if not already contains it
									if ( fileTitle.getNamespaceId() !== 6 ) {
										fileTitle = new mw.Title( fileName, 6 );
									}
									fileName = fileTitle.toText();
								}
								options = [ fileSize, fileFormat, fileFloat ];
								// Filter empty values
								options = $.grep( options, function ( val ) {
									return val.length && val !== 'default';
								} );
								if ( caption.length ) {
									options.push( caption );
								}
								fileUse = options.length === 0 ? fileName : ( fileName + '|' + options.join( '|' ) );
								$( this ).dialog( 'close' );
								$.wikiEditor.modules.toolbar.fn.doAction(
									$( this ).data( 'context' ),
									{
										type: 'replace',
										options: {
											pre: '[[',
											peri: fileUse,
											post: ']]',
											ownline: true
										}
									},
									$( this )
								);

								// Restore form state
								$( [ '#wikieditor-toolbar-file-target',
									'#wikieditor-toolbar-file-caption',
									'#wikieditor-toolbar-file-size' ].join( ',' )
								).val( '' );
								$( '#wikieditor-toolbar-file-float' ).val( 'default' );
								$( '#wikieditor-toolbar-file-format' ).val( magicWordsI18N.img_thumbnail );
							},
							'wikieditor-toolbar-tool-file-cancel': function () {
								$( this ).dialog( 'close' );
							},
							'wikieditor-toolbar-tool-file-upload': function () {
								var windowManager = new OO.ui.WindowManager(),
									uploadDialog = new mw.Upload.Dialog( {
										bookletClass: mw.ForeignStructuredUpload.BookletLayout
									} );

								$( this ).dialog( 'close' );
								$( 'body' ).append( windowManager.$element );
								windowManager.addWindows( [ uploadDialog ] );
								windowManager.openWindow( uploadDialog );

								uploadDialog.uploadBooklet.on( 'fileSaved', function ( imageInfo ) {
									uploadDialog.close();
									windowManager.$element.remove();

									$.wikiEditor.modules.dialogs.api.openDialog( this, 'insert-file' );
									$( '#wikieditor-toolbar-file-target' ).val( imageInfo.canonicaltitle );
								} );
							}
						},
						open: function () {
							$( '#wikieditor-toolbar-file-target' ).focus();
							if ( !( $( this ).data( 'dialogkeypressset' ) ) ) {
								$( this ).data( 'dialogkeypressset', true );
								// Execute the action associated with the first button
								// when the user presses Enter
								$( this ).closest( '.ui-dialog' ).keypress( function ( e ) {
									var button;
									if ( e.which === 13 ) {
										button = $( this ).data( 'dialogaction' ) ||
											$( this ).find( 'button:first' );
										button.click();
										e.preventDefault();
									}
								} );

								// Make tabbing to a button and pressing
								// Enter do what people expect
								$( this ).closest( '.ui-dialog' ).find( 'button' ).focus( function () {
									$( this ).closest( '.ui-dialog' ).data( 'dialogaction', this );
								} );
							}
						}
					}
				},
				'insert-table': {
					titleMsg: 'wikieditor-toolbar-tool-table-title',
					id: 'wikieditor-toolbar-table-dialog',
					htmlTemplate: 'dialogInsertTable.html',
					init: function () {
						$( this ).find( '[rel]' ).each( function () {
							$( this ).text( mw.msg( $( this ).attr( 'rel' ) ) );
						} );
						// Set tabindexes on form fields
						$.wikiEditor.modules.dialogs.fn.setTabindexes( $( this ).find( 'input' ).not( '[tabindex]' ) );

						$( '#wikieditor-toolbar-table-dimensions-rows' ).val( 3 );
						$( '#wikieditor-toolbar-table-dimensions-columns' ).val( 3 );
						$( '#wikieditor-toolbar-table-wikitable' ).click( function () {
							$( '.wikieditor-toolbar-table-preview' ).toggleClass( 'wikitable' );
						} );

						// Hack for sortable preview: dynamically adding
						// sortable class doesn't work, so we use a clone
						$( '#wikieditor-toolbar-table-preview' )
							.clone()
							.attr( 'id', 'wikieditor-toolbar-table-preview2' )
							.addClass( 'sortable' )
							.insertAfter( $( '#wikieditor-toolbar-table-preview' ) )
							.hide();

						mw.loader.using( 'jquery.tablesorter', function () {
							$( '#wikieditor-toolbar-table-preview2' ).tablesorter();
						} );

						$( '#wikieditor-toolbar-table-sortable' ).click( function () {
							// Swap the currently shown one clone with the other one
							$( '#wikieditor-toolbar-table-preview' )
								.hide()
								.attr( 'id', 'wikieditor-toolbar-table-preview3' );
							$( '#wikieditor-toolbar-table-preview2' )
								.attr( 'id', 'wikieditor-toolbar-table-preview' )
								.show();
							$( '#wikieditor-toolbar-table-preview3' ).attr( 'id', 'wikieditor-toolbar-table-preview2' );
						} );

						$( '#wikieditor-toolbar-table-dimensions-header' ).click( function () {
							// Instead of show/hiding, switch the HTML around
							// We do this because the sortable tables script styles the first row,
							// visible or not
							var $sortable,
								headerHTML = $( '.wikieditor-toolbar-table-preview-header' ).html(),
								hiddenHTML = $( '.wikieditor-toolbar-table-preview-hidden' ).html();
							$( '.wikieditor-toolbar-table-preview-header' ).html( hiddenHTML );
							$( '.wikieditor-toolbar-table-preview-hidden' ).html( headerHTML );
							$sortable = $( '#wikieditor-toolbar-table-preview, #wikieditor-toolbar-table-preview2' )
								.filter( '.sortable' );
							mw.loader.using( 'jquery.tablesorter', function () {
								$sortable.tablesorter();
							} );
						} );
					},
					dialog: {
						resizable: false,
						dialogClass: 'wikiEditor-toolbar-dialog',
						width: 590,
						buttons: {
							'wikieditor-toolbar-tool-table-insert': function () {
								var headerText, normalText, table, r, c,
									isHeader, delim, classes, classStr,
									rowsVal = $( '#wikieditor-toolbar-table-dimensions-rows' ).val(),
									colsVal = $( '#wikieditor-toolbar-table-dimensions-columns' ).val(),
									rows = parseInt( rowsVal, 10 ),
									cols = parseInt( colsVal, 10 ),
									header = $( '#wikieditor-toolbar-table-dimensions-header' ).prop( 'checked' ) ? 1 : 0;
								if ( isNaN( rows ) || isNaN( cols ) || String( rows ) !== rowsVal || String( cols ) !== colsVal || rowsVal < 0 || colsVal < 0 ) {
									// eslint-disable-next-line no-alert
									alert( mw.msg( 'wikieditor-toolbar-tool-table-invalidnumber' ) );
									return;
								}
								if ( rows + header === 0 || cols === 0 ) {
									// eslint-disable-next-line no-alert
									alert( mw.msg( 'wikieditor-toolbar-tool-table-zero' ) );
									return;
								}
								if ( ( rows * cols ) > 1000 ) {
									// 1000 is in the English message. The parameter replacement is kept for BC.
									// eslint-disable-next-line no-alert
									alert( mw.msg( 'wikieditor-toolbar-tool-table-toomany', 1000 ) );
									return;
								}
								headerText = mw.msg( 'wikieditor-toolbar-tool-table-example-header' );
								normalText = mw.msg( 'wikieditor-toolbar-tool-table-example' );
								table = '';
								for ( r = 0; r < rows + header; r++ ) {
									table += '|-\n';
									for ( c = 0; c < cols; c++ ) {
										isHeader = ( header && r === 0 );
										delim = isHeader ? '!' : '|';
										if ( c > 0 ) {
											delim += delim;
										}
										table += delim + ' ' + ( isHeader ? headerText : normalText ) + ' ';
									}
									// Replace trailing space by newline
									// table[table.length - 1] is read-only
									table = table.substr( 0, table.length - 1 ) + '\n';
								}
								classes = [];
								if ( $( '#wikieditor-toolbar-table-wikitable' ).is( ':checked' ) ) {
									classes.push( 'wikitable' );
								}
								if ( $( '#wikieditor-toolbar-table-sortable' ).is( ':checked' ) ) {
									classes.push( 'sortable' );
								}
								classStr = classes.length > 0 ? ' class="' + classes.join( ' ' ) + '"' : '';
								$( this ).dialog( 'close' );
								$.wikiEditor.modules.toolbar.fn.doAction(
									$( this ).data( 'context' ),
									{
										type: 'replace',
										options: {
											pre: '{|' + classStr + '\n',
											peri: table,
											post: '|}',
											ownline: true
										}
									},
									$( this )
								);

								// Restore form state
								$( '#wikieditor-toolbar-table-dimensions-rows' ).val( 3 );
								$( '#wikieditor-toolbar-table-dimensions-columns' ).val( 3 );
								// Simulate clicks instead of setting values, so the according
								// actions are performed
								if ( !$( '#wikieditor-toolbar-table-dimensions-header' ).is( ':checked' ) ) {
									$( '#wikieditor-toolbar-table-dimensions-header' ).click();
								}
								if ( !$( '#wikieditor-toolbar-table-wikitable' ).is( ':checked' ) ) {
									$( '#wikieditor-toolbar-table-wikitable' ).click();
								}
								if ( $( '#wikieditor-toolbar-table-sortable' ).is( ':checked' ) ) {
									$( '#wikieditor-toolbar-table-sortable' ).click();
								}
							},
							'wikieditor-toolbar-tool-table-cancel': function () {
								$( this ).dialog( 'close' );
							}
						},
						open: function () {
							$( '#wikieditor-toolbar-table-dimensions-rows' ).focus();
							if ( !( $( this ).data( 'dialogkeypressset' ) ) ) {
								$( this ).data( 'dialogkeypressset', true );
								// Execute the action associated with the first button
								// when the user presses Enter
								$( this ).closest( '.ui-dialog' ).keypress( function ( e ) {
									var button;
									if ( ( e.keyCode || e.which ) === 13 ) {
										button = $( this ).data( 'dialogaction' ) || $( this ).find( 'button:first' );
										button.click();
										e.preventDefault();
									}
								} );

								// Make tabbing to a button and pressing
								// Enter do what people expect
								$( this ).closest( '.ui-dialog' ).find( 'button' ).focus( function () {
									$( this ).closest( '.ui-dialog' ).data( 'dialogaction', this );
								} );
							}
						}
					}
				},
				'search-and-replace': {
					browsers: {
						// Left-to-right languages
						ltr: {
							msie: [ [ '>=', 11 ] ] // Known to work on 11.
						},
						// Right-to-left languages
						rtl: {
							msie: [ [ '>=', 11 ] ] // Works on 11 but dialog positioning is cruddy.
						}
					},
					titleMsg: 'wikieditor-toolbar-tool-replace-title',
					id: 'wikieditor-toolbar-replace-dialog',
					htmlTemplate: 'dialogReplace.html',
					init: function () {
						$( this ).find( '[rel]' ).each( function () {
							$( this ).text( mw.msg( $( this ).attr( 'rel' ) ) );
						} );
						// Set tabindexes on form fields
						$.wikiEditor.modules.dialogs.fn.setTabindexes( $( this ).find( 'input' ).not( '[tabindex]' ) );

						// TODO: Find a cleaner way to share this function
						$( this ).data( 'replaceCallback', function ( mode ) {
							var offset, textRemainder, regex, index, i,
								searchStr, replaceStr, flags, matchCase, isRegex,
								$textarea, text, match,
								matchedText, replace, newEnd,
								actualReplacement,
								start, end;

							$( '#wikieditor-toolbar-replace-nomatch, #wikieditor-toolbar-replace-success, #wikieditor-toolbar-replace-emptysearch, #wikieditor-toolbar-replace-invalidregex' ).hide();

							// Search string cannot be empty
							searchStr = $( '#wikieditor-toolbar-replace-search' ).val();
							if ( searchStr === '' ) {
								$( '#wikieditor-toolbar-replace-emptysearch' ).show();
								return;
							}

							// Replace string can be empty
							replaceStr = $( '#wikieditor-toolbar-replace-replace' ).val();

							// Prepare the regular expression flags
							flags = 'm';
							matchCase = $( '#wikieditor-toolbar-replace-case' ).is( ':checked' );
							if ( !matchCase ) {
								flags += 'i';
							}
							isRegex = $( '#wikieditor-toolbar-replace-regex' ).is( ':checked' );
							if ( !isRegex ) {
								searchStr = mw.RegExp.escape( searchStr );
							}
							if ( mode === 'replaceAll' ) {
								flags += 'g';
							}

							try {
								regex = new RegExp( searchStr, flags );
							} catch ( e ) {
								$( '#wikieditor-toolbar-replace-invalidregex' )
									.text( mw.msg( 'wikieditor-toolbar-tool-replace-invalidregex',
										e.message ) )
									.show();
								return;
							}

							$textarea = $( this ).data( 'context' ).$textarea;
							text = $textarea.textSelection( 'getContents' );
							match = false;
							if ( mode !== 'replaceAll' ) {
								if ( mode === 'replace' ) {
									offset = $( this ).data( 'matchIndex' );
								} else {
									offset = $( this ).data( 'offset' );
								}
								textRemainder = text.substr( offset );
								match = textRemainder.match( regex );
							}
							if ( !match ) {
								// Search hit BOTTOM, continuing at TOP
								// TODO: Add a "Wrap around" option.
								offset = 0;
								textRemainder = text;
								match = textRemainder.match( regex );
							}

							if ( !match ) {
								$( '#wikieditor-toolbar-replace-nomatch' ).show();
							} else if ( mode === 'replaceAll' ) {
								// Instead of using repetitive .match() calls, we use one .match() call with /g
								// and indexOf() followed by substr() to find the offsets. This is actually
								// faster because our indexOf+substr loop is faster than a match loop, and the
								// /g match is so ridiculously fast that it's negligible.
								// FIXME: Repetitively calling encapsulateSelection() is probably the best strategy
								// in Firefox/Webkit, but in IE replacing the entire content once is better.
								for ( i = 0; i < match.length; i++ ) {
									index = textRemainder.indexOf( match[ i ] );
									if ( index === -1 ) {
										// This shouldn't happen
										break;
									}
									matchedText = textRemainder.substr( index, match[ i ].length );
									textRemainder = textRemainder.substr( index + match[ i ].length );

									start = index + offset;
									end = start + match[ i ].length;
									// Make regex placeholder substitution ($1) work
									replace = isRegex ? matchedText.replace( regex, replaceStr ) : replaceStr;
									newEnd = start + replace.length;
									$textarea
										.textSelection( 'setSelection', { start: start, end: end } )
										.textSelection( 'encapsulateSelection', {
											peri: replace,
											replace: true } )
										.textSelection( 'setSelection', { start: start, end: newEnd } );
									offset = newEnd;
								}
								$( '#wikieditor-toolbar-replace-success' )
									.text( mw.msg( 'wikieditor-toolbar-tool-replace-success', match.length ) )
									.show();
								$( this ).data( 'offset', 0 );
							} else {

								if ( mode === 'replace' ) {

									if ( isRegex ) {
										// If backreferences (like $1) are used, the actual actual replacement string will be different
										actualReplacement = match[ 0 ].replace( regex, replaceStr );
									} else {
										actualReplacement = replaceStr;
									}

									if ( match ) {
										// Do the replacement
										$textarea.textSelection( 'encapsulateSelection', {
											peri: actualReplacement,
											replace: true } );
										// Reload the text after replacement
										text = $textarea.textSelection( 'getContents' );
									}

									// Find the next instance
									offset = offset + match[ 0 ].length + actualReplacement.length;
									textRemainder = text.substr( offset );
									match = textRemainder.match( regex );

									if ( match ) {
										start = offset + match.index;
										end = start + match[ 0 ].length;
									} else {
										// If no new string was found, try searching from the beginning.
										// TODO: Add a "Wrap around" option.
										textRemainder = text;
										match = textRemainder.match( regex );
										if ( match ) {
											start = match.index;
											end = start + match[ 0 ].length;
										} else {
											// Give up
											start = 0;
											end = 0;
										}
									}
								} else {
									start = offset + match.index;
									end = start + match[ 0 ].length;
								}

								$( this ).data( 'matchIndex', start );

								$textarea.textSelection( 'setSelection', {
									start: start,
									end: end } );
								$textarea.textSelection( 'scrollToCaretPosition' );
								$( this ).data( 'offset', end );
								$textarea[ 0 ].focus();
							}
						} );
					},
					dialog: {
						width: 500,
						dialogClass: 'wikiEditor-toolbar-dialog',
						modal: false,
						buttons: {
							'wikieditor-toolbar-tool-replace-button-findnext': function ( e ) {
								$( this ).closest( '.ui-dialog' ).data( 'dialogaction', e.target );
								$( this ).data( 'replaceCallback' ).call( this, 'find' );
							},
							'wikieditor-toolbar-tool-replace-button-replace': function ( e ) {
								$( this ).closest( '.ui-dialog' ).data( 'dialogaction', e.target );
								$( this ).data( 'replaceCallback' ).call( this, 'replace' );
							},
							'wikieditor-toolbar-tool-replace-button-replaceall': function ( e ) {
								$( this ).closest( '.ui-dialog' ).data( 'dialogaction', e.target );
								$( this ).data( 'replaceCallback' ).call( this, 'replaceAll' );
							},
							'wikieditor-toolbar-tool-replace-close': function () {
								$( this ).dialog( 'close' );
							}
						},
						open: function () {
							var dialog, context, textbox,
								that = this;
							$( this ).data( 'offset', 0 );
							$( this ).data( 'matchIndex', 0 );

							$( '#wikieditor-toolbar-replace-search' ).focus();
							$( '#wikieditor-toolbar-replace-nomatch, #wikieditor-toolbar-replace-success, #wikieditor-toolbar-replace-emptysearch, #wikieditor-toolbar-replace-invalidregex' ).hide();
							if ( !( $( this ).data( 'onetimeonlystuff' ) ) ) {
								$( this ).data( 'onetimeonlystuff', true );
								// Execute the action associated with the first button
								// when the user presses Enter
								$( this ).closest( '.ui-dialog' ).keypress( function ( e ) {
									var button;
									if ( ( e.keyCode || e.which ) === 13 ) {
										button = $( this ).data( 'dialogaction' ) || $( this ).find( 'button:first' );
										button.click();
										e.preventDefault();
									}
								} );
								// Make tabbing to a button and pressing
								// Enter do what people expect
								$( this ).closest( '.ui-dialog' ).find( 'button' ).focus( function () {
									$( this ).closest( '.ui-dialog' ).data( 'dialogaction', this );
								} );
							}
							dialog = $( this ).closest( '.ui-dialog' );
							that = this;
							context = $( this ).data( 'context' );
							textbox = context.$textarea;

							$( textbox )
								.on( 'keypress.srdialog', function ( e ) {
									var button;
									if ( e.which === 13 ) {
										// Enter
										button = dialog.data( 'dialogaction' ) || dialog.find( 'button:first' );
										button.click();
										e.preventDefault();
									} else if ( e.which === 27 ) {
										// Escape
										$( that ).dialog( 'close' );
									}
								} );
						},
						close: function () {
							var context = $( this ).data( 'context' ),
								textbox = context.$textarea;
							$( textbox ).off( 'keypress.srdialog' );
							$( this ).closest( '.ui-dialog' ).data( 'dialogaction', false );
						}
					}
				}
			} };
		}

	};

}( jQuery, mediaWiki, OO ) );
