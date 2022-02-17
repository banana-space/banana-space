( function () {

	// eslint-disable-next-line no-jquery/no-global-selector
	var $searchResultEls = $( '.mw-search-results > li' );

	// Only run on specialSearch page with default profile
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'Search' &&
		mw.util.getParamValue( 'profile' ) !== 'default'
	) {
		return;
	}

	$.when( mw.loader.using(
		[
			'mediawiki.api',
			'mediawiki.template.mustache',
			'ext.uls.common'
		] ), $.ready )
		.then( function () {
			return new mw.Api().loadMessagesIfMissing( [
				'cirrussearch-explore-similar-related',
				'cirrussearch-explore-similar-categories',
				'cirrussearch-explore-similar-languages',
				'otherlanguages',
				'cirrussearch-explore-similar-related-none',
				'cirrussearch-explore-similar-categories-none',
				'cirrussearch-explore-similar-languages-none' ] );
		} )
		.then( function () {

			/**
			 * CSS classes used in templates
			 */
			var cssClassPrefix = 'ext-cirrus__xplr',
				cssClasses = {
					contentWrapper: cssClassPrefix + '__content-wrapper',
					content: cssClassPrefix + '__content',
					contentTitle: cssClassPrefix + '__content__title',
					contentColumns: cssClassPrefix + '__content__columns',
					buttons: cssClassPrefix + '__buttons',
					button: cssClassPrefix + '__button',
					buttonIcon: cssClassPrefix + '__button__icon',
					relatedContent: cssClassPrefix + '__content--related-pages',
					relatedPage: cssClassPrefix + '__related-page',
					relatedPageTitle: cssClassPrefix + '__related-page__title',
					relatedPageContent: cssClassPrefix + '__related-page__content',
					relatedPageThumb: cssClassPrefix + '__related-page__thumb',
					langContent: cssClassPrefix + '__content--languages',
					langLink: cssClassPrefix + '__content--languages__link',
					catContent: cssClassPrefix + '__categories',
					category: cssClassPrefix + '__category',
					activeButton: cssClassPrefix + '__button--active',
					activeSlowButton: cssClassPrefix + '__button--active-slow',
					active: cssClassPrefix + '--active'
				},
				/**
				 * l10n strings
				 */
				l10n = {
					relatedLink: mw.message( 'cirrussearch-explore-similar-related' ).text(),
					categoriesLink: mw.message( 'cirrussearch-explore-similar-categories' ).text(),
					languagesLink: mw.message( 'cirrussearch-explore-similar-languages' ).text(),
					relatedSectionTitle: mw.message( 'cirrussearch-explore-similar-related' ).text(),
					categoriesSectionTitle: mw.message( 'cirrussearch-explore-similar-categories' ).text(),
					languagesSectionTitle: mw.message( 'otherlanguages' ).text(),
					relatedSectionTitleNone: mw.message( 'cirrussearch-explore-similar-related-none' ).text(),
					categoriesSectionTitleNone: mw.message( 'cirrussearch-explore-similar-categories-none' ).text(),
					languagesSectionTitleNone: mw.message( 'cirrussearch-explore-similar-languages-none' ).text()
				};

			/**
			 * DeferredContentWidget
			 * =====================
			 * This is a factory function that abstracts the process of fetching AJAX content,
			 * processing the return data and populating a mustache template.
			 *
			 * The ajax call is wrapped in a jQuery Deferred object for convenient API usage.
			 * ex:
			 * ```
			 * deferredWidget.getData().done( function ( templateEl ) {
			 *     $('...').append( templateEl );
			 * })
			 * ```
			 *
			 * @param {Object} userConf
			 * @param {Object} userConf.apiConfig - An object containing a url and params property to fetch.
			 * @param {Function} userConf.template - A mustache template string.
			 * @param {Function} userConf.filterApiResponse - function that manipulates AJAX return data and returns data suitable
			 *  for usage in template.
			 * @return {Object} - Returns an object with a single method: getData(). This function returns a promise object
			 *  suitable for chaining. ex: getData().then()...
			 */

			function DeferredContentWidget( userConf ) {
				var apiEndpoint = mw.util.wikiScript( 'api' ),
					conf = $.extend( true, {
						apiConfig: { url: apiEndpoint, params: {} },
						template: function ( templateData ) { return templateData; },
						filterApiResponse: function ( response ) {
							return response;
						}
					}, userConf ),
					ajaxCallRequired = true,
					deferred = $.Deferred();

				/**
				 * @param {Object} templateData - filtered API response data to populat template.
				 * @return {Element} - A compiled mustache template ready for DOM insertion.
				 */
				function compileTemplate( templateData ) {

					var compiledTemplate = mw.template.compile(
						conf.template( templateData ),
						'mustache' );

					if ( $.isEmptyObject( templateData ) ) {
						return '';
					}

					return compiledTemplate.render( templateData );
				}

				/**
				 * Creates and executes AJAX request based on user config.
				 * Opting for $.get instead of mw.Api().get() for possibility of using RESTbase API.
				 *
				 * @return {jQuery.Promise}
				 */
				function getData() {

					if ( ajaxCallRequired ) {

						ajaxCallRequired = false; // makes sure ajax is only called once
						conf.apiConfig.params.origin = '*'; // enables cross-origin requests

						$.get( conf.apiConfig.url, conf.apiConfig.params )
							.then( conf.filterApiResponse )
							.then( compileTemplate )
							.then( function ( compiledTemplate ) {
								deferred.resolve( compiledTemplate );
							} )
							.fail( function () {
								deferred.fail();
							} );
					}
					return deferred.promise();
				}

				/* Public methods */
				return {
					getData: getData
				};
			}

			/**
			 * Extends the DeferredContentWidget function with params
			 * for getting page categories.
			 *
			 * @param {string} articleTitle
			 * @return {Object} - extended DeferredContentWidget object.
			 */
			/*
			function RelatedCategoriesWidget( articleTitle ) {
				var config = {
					apiConfig: {
						params: {
							action: 'query',
							format: 'json',
							prop: 'info',
							titles: articleTitle,
							generator: 'categories',
							inprop: 'url',
							gclshow: '!hidden',
							gcllimit: 10
						}
					},
					filterApiResponse: function ( reqResponse ) {
						var templateData,
						queryPages = ( reqResponse.query && reqResponse.query.pages ) ?
							reqResponse.query.pages : [];
						templateData = {
							sectionTitle: l10n.categoriesSectionTitle,
							cssClasses: cssClasses,
							pageCategories: $.map( queryPages, function ( page ) {
											var humanTitle = page.title.replace( /.*:/, '' ),
												url = page.fullurl;
											return {
												humanTitle: humanTitle,
												url: url
											};
								} )
						};

						if ( !templateData.pageCategories.length ) {
							templateData.sectionTitle = l10n.categoriesSectionTitleNone;
							templateData.noContent = 'no-content';
						}
						return templateData;
					},
					template: function () {
						return '<aside class="{{cssClasses.catContent}} {{noContent}}">' +
									'<strong class="{{cssClasses.contentTitle}}">' +
										'{{sectionTitle}}' +
									'</strong>' +
									'<div class="{{cssClasses.contentColumns}}">' +
										'{{#pageCategories}}' +
											'<a href="{{url}}" class="{{cssClasses.category}}" style="display:block;">' +
												'{{humanTitle}}' +
											'</a>' +
										'{{/pageCategories}}' +
									'</div>' +
								'</aside>';
					}
				};
				return DeferredContentWidget.call( this, config );
			}
			*/

			/**
			 * Extends the DeferredContentWidget function with params
			 * for getting page language links.
			 *
			 * @param {string} articleTitle
			 * @return {Object} - extended DeferredContentWidget object.
			 */
			function LangLinksWidget( articleTitle ) {
				var config = {
					apiConfig: {
						params: {
							format: 'json',
							action: 'query',
							titles: articleTitle,
							prop: 'langlinks',
							llprop: 'url|autonym',
							lllimit: '500'
						}
					},
					filterApiResponse: function ( reqResponse ) {

						var prefLangs = mw.uls.getFrequentLanguageList(),
							templateData = {
								// eslint-disable-next-line no-jquery/no-map-util
								langLinks: $.map( reqResponse.query.pages, function ( page ) {
									if ( page.langlinks ) {
										return page.langlinks.filter( function ( langlink ) {
											return prefLangs.indexOf( langlink.lang ) >= 0;
										} );
									}
								} ),
								sectionTitle: l10n.languagesSectionTitle,
								cssClasses: cssClasses
							};

						if ( !templateData.langLinks.length ) {
							templateData.sectionTitle = l10n.languagesSectionTitleNone;
							templateData.cssNone = 'no-content';
						}

						return templateData;
					},
					template: function () {
						return '<aside class="{{cssClasses.langContent}} {{cssNone}}">' +
								'<strong class="{{cssClasses.contentTitle}}">' +
									'{{sectionTitle}}' +
								'</strong>' +
								'{{#langLinks}}' +
									'<div class="{{cssClasses.langLink}}" data-lang={{lang}}>' +
										'<div>{{autonym}}</div>' +
										'<a href="{{url}}">' +
											'{{*}}' +
										'</a>' +
									'</div>' +
								'{{/langLinks}}' +
							'</aside>';
					}
				};
				return DeferredContentWidget.call( this, config );
			}

			/**
			 * Extends the DeferredContentWidget function with params
			 * for getting related pages based on the 'morelike' API.
			 *
			 * @param {string} articleTitle
			 * @return {Object} - extended DeferredContentWidget object.
			 */
			/*
			function RelatedPagesWidget( articleTitle ) {
				var config = {
					apiConfig: {
						params: {
							action: 'query',
							format: 'json',
							formatversion: 2,
							prop: 'pageimages|pageterms|info',
							piprop: 'thumbnail',
							pithumbsize: 160,
							pilimit: 3,
							wbptterms: 'description',
							generator: 'search',
							gsrsearch: 'morelike:' + articleTitle,
							gsrnamespace: 0,
							gsrlimit: 3,
							gsrqiprofile: 'classic_noboostlinks',
							inprop: 'url',
							uselang: 'content',
							smaxage: 86400,
							maxage: 86400
						}

					},
					filterApiResponse: function ( reqResponse ) {
						var templateData;

						if ( typeof reqResponse.query !== 'undefined' &&
							reqResponse.query.pages.length
						) {
							templateData = {
								cssClasses: cssClasses,
								sectionTitle: l10n.relatedSectionTitle,
								relatedPages: reqResponse.query.pages
							};
						} else {
							templateData = {
								cssClasses: cssClasses,
								sectionTitle: l10n.relatedSectionTitleNone,
								noContent: 'no-content'
							};
						}

						return templateData;
					},
					template: function () {
						return '<aside class="{{cssClasses.relatedContent}} {{noContent}}">' +
								'<strong class="{{cssClasses.contentTitle}}">' +
									'{{sectionTitle}}' +
								'</strong>' +
								'{{#relatedPages}}' +
									'<a href="{{fullurl}}" title="{{title}}" class="{{cssClasses.relatedPage}}">' +
										'{{#thumbnail}}' +
											'<div class="{{cssClasses.relatedPageThumb}}" style="background-image:url({{thumbnail.source}});"></div>' +
										'{{/thumbnail}}' +
										'<strong class={{cssClasses.relatedPageTitle}}> {{title}} </strong>' +
										'{{#terms}}' +
											'<p>' +
												'{{description}}' +
											'</p>' +
										'{{/terms}}' +
									'</a>' +
								'{{/relatedPages}}' +
							'</aside>';
					}
				};
				return DeferredContentWidget.call( this, config );
			}
			*/

			/**
			 * Global array for storing & deleting explore similar buttons
			 * that have been triggered with a delay.
			 */
			window.ExploreSimilarTimeoutQueue = [];

			/**
			 * Create an Explore Similar button and adds necessary behaviour.
			 *
			 * @param {jQuery} $searchResult
			 * @param {string} resultTitle
			 * @return {jQuery}
			 */
			function createExploreSimilarButton( $searchResult, resultTitle ) {

				/**
				 * The ExploreSimilarWidget keys should corresponde to the
				 * 'data-es-content' attributes of the template buttons in order
				 * to map the correct data to the correct element.
				 * This mapping used in openExploreSimilarItem().
				 */
				var contentWidgets = {
						languages: new LangLinksWidget( resultTitle )
					},
					$template = $(
						'<div class="' + cssClasses.buttons + '">' +
							'<a class="' + cssClasses.button + '" data-es-content="languages">' +
								l10n.languagesLink +
								'<span class="' + cssClasses.buttonIcon + '"></span>' +
							'</a>' +
							'<div class="' + cssClasses.contentWrapper + '" style="display:none;"></div>' +
						'</div>'
					),
					$widgetContent = $template.find( ' .' + cssClasses.contentWrapper );

				/**
				 * Sets the template content
				 *
				 * @param {Element} content
				 */
				function replaceTemplateContent( content ) {
					$widgetContent.html( content );
				}

				/**
				 * Makes template content visible while
				 * hiding all other templates on the page.
				 */
				function showContent() {
					$( '.' + cssClasses.contentWrapper ).hide();
					$widgetContent.show();
				}

				/**
				 * adds 'active' class to search result while
				 * removing it from all other search results on the page.
				 */
				function activateSearchResult() {
					$searchResultEls.removeClass( cssClasses.active );
					$searchResult.addClass( cssClasses.active );
				}

				/**
				 * Sets a CSS class to animate the Explore Similar button.
				 * Button can be animated slowly or quickly depending on whether
				 * it's the first button in the set the user hovers over.
				 *
				 * @param {jQuery} $this - button element wrapped in jQuery object
				 * @param {number} delay - delay with which content should appear.
				 */
				function animateButton( $this, delay ) {
					$( '.' + cssClasses.button ).removeClass( cssClasses.activeButton + ' , ' + cssClasses.activeSlowButton );
					if ( delay ) {
						$this.addClass( cssClasses.activeSlowButton );
					} else {
						$this.addClass( cssClasses.activeButton );
					}
				}

				/**
				 * removes all timers from the Explore Similar Queue.
				 * This prevents unwanted items from opening if a new item
				 * has been triggered.
				 */
				function clearExploreSimilarQueue() {
					window.ExploreSimilarTimeoutQueue.forEach( function ( timer ) {
						window.clearTimeout( timer );
					} );
				}

				/**
				 * Quasi UUID generator, for the purpose of matching 'open' & 'close' events
				 *
				 * @return {string} - UUID string based on timestamp and random number.
				 */
				function uniqueHoverId() {
					return Math.random().toString( 36 ).substring( 2 ) + ( new Date() ).getTime().toString( 36 );
				}

				/**
				 * broadcasts a custom jQuery event that can be subscribed
				 * to by other modules like eventlogging. Tailored for the
				 * searchSatisfaction2 schema
				 *
				 * Event data includes:
				 *  - hoverID: A unique identifier that pair hover-on and hover-off events.
				 *  - section: The name of the active section: 'related' || 'categories' || 'languages'
				 *             Defined as the 'es-content' attribute in the template string.
				 *  - results: Number of explore similar results.
				 *
				 * @param {jQuery} $button - Button element wrapped in jQuery.
				 * @param {string} state - 'open' || 'close' || 'click'.
				 * @param {jQuery} [$eventTarget] - $(event.target) passed from event callback.
				 *                                Only passed on click event since $button should
				 *                                the event that triggers the 'open' event.
				 * @param {jQuery} [$clickTarget] - $(this) passed from event callback. Should be
				 *                                one of the explore similar results. Only passed
				 *                                on click event.
				 **/
				function triggerCustomEvent( $button, state, $eventTarget, $clickTarget ) {
					var $templateItems = $template.find(
							'.' + cssClasses.langLink +
							', .' + cssClasses.relatedPage +
							', .' + cssClasses.category ),
						eventParams = {
							hoverId: $button.data( 'hover-id' ),
							section: $button.data( 'es-content' ),
							results: $templateItems.length,
							eventTarget: $eventTarget
						};
					if ( state === 'click' && $clickTarget.is( '.' + cssClasses.langLink ) ) {
						eventParams.result = $clickTarget.data( 'lang' );
					}

					if ( state === 'click' && !$clickTarget.is( '.' + cssClasses.langLink ) ) {
						eventParams.result = $templateItems.index( $clickTarget );
					}
					mw.track( 'ext.CirrusSearch.exploreSimilar.' + state, eventParams );
				}
				/**
				 * Opens the Explore Similar widget based on which button was hovered.
				 * Sets a delay if this was the first item hovered in the set and
				 * clears the Explore Similar queue of any previous items.
				 *
				 * @param {Element} button - button that has been triggered.
				 * @param {*} relatedEl - The last item that was triggered (event.relatedTarget).
				 */
				function openExploreSimilarItem( button, relatedEl ) {
					var $button = $( button ),
						$relatedEl = $( relatedEl ),
						delay;

					clearExploreSimilarQueue();

					if ( $template.find( $relatedEl )[ 0 ] ) {
						delay = 0;
					} else {
						delay = 250;
					}

					$button.data( 'hover-id', uniqueHoverId() );

					animateButton( $button, delay );

					// item is pushed to the timeout queue even if the delay is 0.
					window.ExploreSimilarTimeoutQueue.push(
						window.setTimeout( function () {
							// The keys of the contentWidgets Object should correnspond
							// to the 'data-es-content' attribute of the button template.
							contentWidgets[
								$button.data( 'es-content' )
							]
								.getData()
								.done( replaceTemplateContent )
								.done( showContent )
								.done( activateSearchResult )
								.done( triggerCustomEvent.bind( null, $button, 'open' ) );
						}, delay )
					);
				}

				/**
				 * Closes the Explore Similar item based on the 'active' CSS class associated with the button,
				 * as well as all other Explore Similar items on the page.
				 * Also Triggers the custom Explore Similar event.
				 *
				 * @param {Object} $template - Explore Similar template wrapped in jQuery object.
				 */
				function closeExploreSimilarItem( $template ) {

					var $activeButton = $template.find( '.' + cssClasses.activeButton + ', .' + cssClasses.activeSlowButton ),
						$contentWrappers = $( '.' + cssClasses.contentWrapper );

					clearExploreSimilarQueue();

					// eslint-disable-next-line no-jquery/no-class-state
					if ( $searchResult.hasClass( cssClasses.active ) ) {
						triggerCustomEvent( $activeButton, 'close' );
					}

					$activeButton.removeClass( cssClasses.activeButton + ' ' + cssClasses.activeSlowButton );
					$contentWrappers.hide();
					$searchResultEls.removeClass( cssClasses.active );
				}

				/**
				 * Event Handlers
				 */
				$template
					.find( '.' + cssClasses.button ) // Explore Similar item open is only triggered on button
					.on( 'mouseenter',
						function ( event ) {
							// check if item isn't already opened
							var $activeButtons = $template.find( '.' + cssClasses.activeButton +
											', .' + cssClasses.activeSlowButton ),
								selectedButtonIsActive = $( this ).is( $activeButtons );

							// if a different button is active, trigger the close event
							if ( $activeButtons.length && !selectedButtonIsActive ) {
								triggerCustomEvent( $activeButtons.first(), 'close' );
							}
							if ( !selectedButtonIsActive ) {
								openExploreSimilarItem( this, event.relatedTarget );
							}
						}
					);

				$template // Explore Similar item close is triggered on entire template
					.on( 'mouseout',
						function ( event ) {

							var $relatedTarget = $( event.relatedTarget );

							// don't close the 'active' state when moving across sections,
							// prevents css flickering of 'active' class
							if (
								// eslint-disable-next-line no-jquery/no-class-state
								!$relatedTarget.hasClass( '.mw-search-result-data' ) &&
								!$template.find( $relatedTarget )[ 0 ]
							) {
								closeExploreSimilarItem( $template );
							}
						}
					);

				$widgetContent // Explore Similar item close is triggered on entire template
					.on( 'click', '.' + cssClasses.relatedPage + ', .' + cssClasses.category + ', .' + cssClasses.langLink,
						function ( event ) {
							var $activeButton = $template.find( '.' + cssClasses.activeButton +
												', .' + cssClasses.activeSlowButton ).first();
							triggerCustomEvent( $activeButton, 'click', $( event.target ), $( this ) );
						}
					);

				// Returns Explore Similar template with all behaviours and events attached.
				return $template;

			}

			$searchResultEls.each( function ( index, el ) {
				var $searchResult = $( el ),
					$searchResultMeta = $searchResult.children( '.mw-search-result-data' ),
					resultTitle = $searchResult.find( '.mw-search-result-heading a' ).attr( 'title' ),
					$exploreButton = createExploreSimilarButton( $searchResult, resultTitle );

				$searchResultMeta.append( $exploreButton );
			} );

		} );

}() );
