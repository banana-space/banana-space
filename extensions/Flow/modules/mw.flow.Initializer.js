/* eslint-disable no-jquery/no-global-selector */
( function () {
	/**
	 * Initializer object for flow-initialize
	 *
	 * @class
	 *
	 * @constructor
	 * @param {Object} config Configuration object
	 */
	mw.flow.Initializer = function ( config ) {
		config = config || {};

		this.$component = null;
		this.$board = null;
		this.siderailCollapsed = mw.user.options.get( 'flow-side-rail-state' ) === 'collapsed';
		this.pageTitle = config.pageTitle || mw.Title.newFromText( mw.config.get( 'wgPageName' ) );

		this.system = null;
		this.board = null;
		this.navWidget = null;
	};

	/* Inheritance */

	OO.initClass( mw.flow.Initializer );

	/**
	 * Sets the DOM element that is the flow component
	 *
	 * @param {jQuery} $component The DOM element that is the component
	 * @return {boolean} The component DOM element exists and is set
	 */
	mw.flow.Initializer.prototype.setComponentDom = function ( $component ) {
		if ( !$component || !$component.length ) {
			return false;
		}
		this.$component = $component;
		return true;
	};

	/**
	 * Sets the DOM element that is the flow board
	 *
	 * @param {jQuery} $board The DOM element that is the board
	 * @return {boolean} The board DOM element exists and is set
	 */
	mw.flow.Initializer.prototype.setBoardDom = function ( $board ) {
		if ( !$board || !$board.length ) {
			return false;
		}
		this.$board = $board;
		return true;
	};

	/**
	 * Set the flowBoard object representing the 'old' Flow system board
	 *
	 * @param {Object} board flowBoard _RecursiveConstructor
	 */
	mw.flow.Initializer.prototype.setBoardObject = function ( board ) {
		var self = this;

		this.flowBoard = board;

		this.flowBoard.connect( this, {
			loadmore: function ( topiclist ) {
				// Add to dm board
				if ( self.system ) {
					self.system.populateBoardTopicsFromJson( topiclist );
				}

				// Replace reply forms
				self.replaceReplyForms( self.$board );
				self.reopenPostWidgets( self.$board );
			},
			// HACK: Update the DM when topic is refreshed
			refreshTopic: function ( workflowId, topicData ) {
				var revisionId, revision,
					topic = self.board.getItemById( workflowId ),
					data = topicData.flow[ 'view-topic' ].result.topic;

				if ( !topic ) {
					// New topic
					mw.flow.system.populateBoardTopicsFromJson( data, 0 );
				} else {
					// Topic already exists. Repopulate
					revisionId = data.posts[ workflowId ];
					revision = data.revisions[ revisionId ];

					topic.populate( revision );
				}

				// Replace reply forms
				self.replaceReplyForms( topicData.$topic );
				self.reopenPostWidgets( topicData.$topic );
			}
		} );
	};

	/**
	 * Set up the window manager
	 */
	mw.flow.Initializer.prototype.setupWindowManager = function () {
		$( document.body ).append( mw.flow.ui.windowManager.$element );
	};

	/**
	 * Set up the sidebar widget if needed
	 */
	mw.flow.Initializer.prototype.setupSidebarWidget = function () {
		var sidebarExpandWidget,
			self = this;

		if (
			// eslint-disable-next-line no-jquery/no-class-state
			this.$component.hasClass( 'flow-topic-page' ) &&
			// eslint-disable-next-line no-jquery/no-class-state
			$( document.body ).hasClass( 'action-view' )
		) {
			this.$board.toggleClass( 'flow-board-expanded', this.siderailCollapsed );

			// We are in single-topic view. Initialize the sidebar expand widget
			sidebarExpandWidget = new mw.flow.ui.SidebarExpandWidget( {
				collapsed: this.siderailCollapsed,
				expandedButtonTitle: mw.msg( 'flow-topic-collapse-siderail' ),
				collapsedButtonTitle: mw.msg( 'flow-topic-expand-siderail' )
			} );
			sidebarExpandWidget.$element.insertAfter( this.$board );

			// Events
			sidebarExpandWidget.on( 'toggle', function ( collapsed ) {
				self.$board.toggleClass( 'flow-board-expanded', collapsed );
			} );
		}
	};

	/**
	 * Initialize the UI widgets
	 */
	mw.flow.Initializer.prototype.initializeWidgets = function () {
		// Set up window manager
		this.setupWindowManager();

		// Set up sidebar widget if it needs to be there
		this.setupSidebarWidget();

		// Set up navigation widget
		this.setupNavigationWidget( $( '.flow-board-navigation' ) );

		// Set up new topic widget
		this.setupNewTopicWidget( $( 'form.flow-newtopic-form' ) );

		// Set up description widget
		this.setupDescriptionWidget( $( '.flow-ui-boardDescriptionWidget' ) );

		// Replace reply forms on the board
		this.replaceReplyForms( this.$board );

		/* Take over click actions */
		this.setupReplyLinkActions();
		this.setupEditPostAction();
		this.setupEditTopicSummaryAction();
		this.setupEditTopicTitleAction();

		this.reopenNewTopicWidget();
		this.reopenDescriptionWidget();
		this.reopenPostWidgets();
	};

	/**
	 * Re-open any post widgets with stored data
	 *
	 * @param {jQuery} [$container] Container, defaults to this.$component
	 */
	mw.flow.Initializer.prototype.reopenPostWidgets = function ( $container ) {
		$container = $container || this.$component;

		this.reopenReplyWidgets( $container );
		this.reopenTopicTitleWidgets( $container );
		this.reopenEditPostWidgets( $container );
		this.reopenEditTopicSummaryWidget( $container );
	};

	/**
	 * Initialize the 'old' Flow ui component
	 */
	mw.flow.Initializer.prototype.initOldComponent = function () {
		if ( this.$component ) {
			mw.flow.initComponent( this.$component );
		}
	};

	/**
	 * Initialize the data model objects
	 *
	 * @param {Object} config Configuration options for the mw.flow.dm.System
	 */
	mw.flow.Initializer.prototype.initDataModel = function ( config ) {
		var self = this;

		this.system = new mw.flow.dm.System( config );
		this.board = this.system.getBoard();
		// Initialize the old system to accept the default
		// order for the topic order widget
		this.flowBoard.topicIdSort = this.board.getSortOrder();

		// Events
		this.board.connect( this, {
			add: function ( newItems ) {
				var i, len, item, itemId;

				for ( i = 0, len = newItems.length; i < len; i++ ) {
					item = newItems[ i ];
					itemId = item.getId();

					if ( self.flowBoard.orderedTopicIds.indexOf( itemId ) === -1 ) {
						self.flowBoard.orderedTopicIds.push( itemId );
					}

					self.flowBoard.topicTitlesById[ itemId ] = item.getContent();
					self.flowBoard.updateTimestampsByTopicId[ itemId ] = item.getLastUpdate();
				}
				self.flowBoard.sortTopicIds( self.flowBoard );
			},
			// E.g. on topic re-order, before re-population.
			clear: function () {
				self.flowBoard.orderedTopicIds = [];
				self.flowBoard.topicTitlesById = {};
			}
			// We shouldn't have to worry about 'remove', since by the time we have filtering,
			// orderedTopicIds should be gone.
		} );
	};

	/**
	 * Get the data model system
	 *
	 * @return {mw.flow.dm.System} DM system
	 */
	mw.flow.Initializer.prototype.getDataModelSystem = function () {
		return this.system;
	};

	/**
	 * Populate the data model
	 *
	 * @param {Object} dataBlob Data blob to populate the system with
	 */
	mw.flow.Initializer.prototype.populateDataModel = function ( dataBlob ) {
		var preloadTopic = OO.getProp( dataBlob, 'blocks', 'topiclist', 'submitted', 'topic' ),
			preloadContent = OO.getProp( dataBlob, 'blocks', 'topiclist', 'submitted', 'content' ),
			preloadFormat = OO.getProp( dataBlob, 'blocks', 'topiclist', 'submitted', 'format' );

		if ( dataBlob && dataBlob.blocks ) {
			// Populate the rendered topics or topic (if we are in a single-topic view)
			this.system.populateBoardTopicsFromJson( dataBlob.blocks.topiclist || dataBlob.blocks.topic );
			// Populate header
			this.system.populateBoardDescriptionFromJson( dataBlob.blocks.header || {} );
			// Populate the ToC topics
			if ( dataBlob.toc ) {
				this.system.populateBoardTopicsFromJson( dataBlob.toc );
			}
		} else {
			this.system.populateBoardFromApi();
		}
		if ( preloadTopic || preloadContent ) {
			this.newTopicWidget.preload( preloadTopic, preloadContent, preloadFormat );
		}
	};

	/**
	 * Set up the navigation widget and its events
	 *
	 * @param {jQuery} $navDom Navigation widget DOM element
	 */
	mw.flow.Initializer.prototype.setupNavigationWidget = function ( $navDom ) {
		var self = this;

		if ( !$navDom.length ) {
			return;
		}

		this.navWidget = new mw.flow.ui.NavigationWidget( this.system, {
			defaultSort: this.flowBoard.topicIdSort
		} );
		$navDom.append( this.navWidget.$element );

		// Events
		// Load a topic from the ToC that isn't rendered on
		// the page yet. This will be gone once board, topic
		// and post are widgetized.
		this.navWidget.connect( this, {
			loadTopic: function ( topicId ) {
				self.flowBoard.jumpToTopic( topicId );
			},
			reorderTopics: function ( newOrder ) {
				self.flowBoard.topicIdSort = newOrder;
			}
		} );

		// Connect to system events

		// HACK: These event handlers should be in the prospective widgets
		// they will move once we have Board UI and Topic UI widgets
		this.system.connect( this, {
			resetBoardStart: function () {
				self.$component.addClass( 'flow-api-inprogress' );
				// Before we reinitialize the board we have to detach
				// the navigation widget. This should not be necessary when
				// the board and topics are OOUI widgets
				self.navWidget.$element.detach();
			},
			resetBoardEnd: function ( data ) {
				var $rendered;

				// populateBoardFromApi uses the larger TOC limit so the TOC can
				// be fully populated on re-sort.  To avoid two requests
				// (TOC and full topics) with different limits, we do a single
				// full-topic request with that limit.
				//
				// However, this is inconsistent with the number of topics
				// we show at page load.
				//
				// This could be addressed by either showing the larger number of
				// topics on page load, doing two separate requests (might still be
				// faster considering the backend doesn't have to get full data for
				// many topics), or filtering the topic list on render.
				//
				// The latter (filter on render) could be done when the topic- and
				// board-widget are operational using some sort of computed subset
				// data model.
				$rendered = $(
					mw.flow.TemplateEngine.processTemplateGetFragment(
						'flow_block_loop',
						{ blocks: data }
					)
				).children();
				// Run this on a short timeout so that the other board handler in FlowBoardComponentLoadMoreFeatureMixin can run
				// TODO: Using a timeout doesn't seem like the right way to do this.
				setTimeout( function () {
					var boardEl = $rendered[ 1 ];

					// Since we've replaced the entire board, we need to reinitialize
					// it. This also takes away the original navWidget, so we need to
					// make sure it's reinitialized too
					self.flowBoard.reinitializeContainer( $rendered );
					$( '.flow-board-navigation' ).append( self.navWidget.$element );

					self.setBoardDom( $( boardEl ) );

					self.replaceReplyForms( self.$board );

					self.setupNewTopicWidget( $( 'form.flow-newtopic-form' ) );

					self.$component.removeClass( 'flow-api-inprogress' );
				}, 50 );
			}
		} );
	};

	/**
	 * Set up the new topic widget and its events
	 *
	 * @param {jQuery} $form New topic form DOM element
	 */
	mw.flow.Initializer.prototype.setupNewTopicWidget = function ( $form ) {
		var self = this;

		this.newTopicWidget = new mw.flow.ui.NewTopicWidget( this.pageTitle.getPrefixedDb(), {
			editor: {
				confirmLeave: !!mw.user.options.get( 'useeditwarning' )
			}
		} );

		// Events
		this.newTopicWidget.connect( this, {
			save: function ( newTopicId ) {
				// Display the new topic with the old system
				var $stub = $( '<div>' ).addClass( 'flow-topic' ).append( $( '<div>' ) ).prependTo( self.flowBoard.$container.find( '.flow-topics' ) );
				return this.flowBoard.flowBoardComponentRefreshTopic( $stub.find( 'div' ), newTopicId );
			}
		} ).once( 'save', this.reloadOnCreate ); // Reload page if board is new so we get page actions at top

		$form.replaceWith( this.newTopicWidget.$element );
	};

	/**
	 * Re-open the new topic widget if it has stored data
	 */
	mw.flow.Initializer.prototype.reopenNewTopicWidget = function () {
		if ( this.newTopicWidget.title.getValue() || mw.storage.session.get( this.newTopicWidget.id + '/ve-docstate' ) ) {
			this.newTopicWidget.activate();
		}
	};

	/**
	 * Set up the description widget and its events
	 *
	 * @param {jQuery} $element Description DOM element
	 */
	mw.flow.Initializer.prototype.setupDescriptionWidget = function ( $element ) {
		var initializer = this;
		if ( !$element.length ) {
			return;
		}

		this.descriptionWidget = new mw.flow.ui.BoardDescriptionWidget( this.board, {
			$existing: $( '.flow-ui-boardDescriptionWidget-content' ).contents(),
			$categories: $( '.flow-board-header-category-view-nojs' ).contents(),
			editor: {
				confirmLeave: !!mw.user.options.get( 'useeditwarning' )
			}
		} )
			// Reload page if board is new so we get page actions at top
			.once( 'saveContent', this.reloadOnCreate )
			.on( 'saveContent', function () {
				mw.hook( 'wikipage.content' ).fire( initializer.descriptionWidget.$content );
			} );

		// The category widget is inside the board description widget.
		// Remove it from the nojs version here
		$( '.flow-board-header-category-view-nojs' ).detach();
		// HACK: Remove the MW page categories
		$( '.catlinks:not(.flow-ui-categoriesWidget)' ).detach();

		$element.replaceWith( this.descriptionWidget.$element );
	};

	/**
	 * Re-open the board description widget if it has stored data
	 */
	mw.flow.Initializer.prototype.reopenDescriptionWidget = function () {
		if ( this.descriptionWidget && mw.storage.session.get( this.descriptionWidget.id + '/ve-docstate' ) ) {
			this.descriptionWidget.onEditButtonClick();
		}
	};

	/**
	 * If the board page is being saved for the first time, reload the page
	 * to show actions like History, Move, Protect, etc.
	 */
	mw.flow.Initializer.prototype.reloadOnCreate = function () {
		if ( mw.config.get( 'wgArticleId' ) === 0 ) {
			location.reload();
		}
	};

	/**
	 * Replace the reply forms given by the php version with js editors
	 *
	 * @param {jQuery} $element The element to conduct the replacements in
	 */
	mw.flow.Initializer.prototype.replaceReplyForms = function ( $element ) {
		var self = this;

		if ( !$element || !$element.length ) {
			return;
		}

		$element.find( '.flow-post.flow-reply-form' ).each( function () {
			var $topic = $( this ).parent(),
				placeholder = mw.msg( 'flow-reply-topic-title-placeholder', $topic.find( '.flow-topic-title' ).text().trim() ),
				replyTo = $( this ).find( 'input[name="topic_replyTo"]' ).val(),
				replyWidget = new mw.flow.ui.ReplyWidget( $topic.data( 'flowId' ), replyTo, {
					placeholder: placeholder,
					editor: {
						confirmLeave: !!mw.user.options.get( 'useeditwarning' )
					}
				} );

			replyWidget.on( 'saveContent', function ( workflow ) {
				replyWidget.destroy().then( function () {
					replyWidget.$element.remove();

					// HACK: get the old system to rerender the topic
					self.flowBoard.flowBoardComponentRefreshTopic(
						$topic,
						workflow
					);
				} );
			} );
			replyWidget.$element.data( 'self', replyWidget );

			// Replace the reply form with the new editor widget
			$( this ).replaceWith( replyWidget.$element );
		} );
	};

	/**
	 * Take over the action of the 'edit post' links
	 * This is delegated, so it applies to all future links as well.
	 */
	mw.flow.Initializer.prototype.setupEditPostAction = function () {
		this.$component.on( 'click', '.flow-ui-edit-post-link', function ( event ) {
			var editPostWidget,
				$topic = $( this ).closest( '.flow-topic' ),
				topicId = $topic.data( 'flow-id' ),
				$post = $( this ).closest( '.flow-post' ),
				$postMain = $post.children( '.flow-post-main' ),
				postId = $post.data( 'flow-id' ),
				$board = $( '.flow-board' ),
				flowBoard = mw.flow.getPrototypeMethod( 'component', 'getInstanceByElement' )( $board );

			editPostWidget = new mw.flow.ui.EditPostWidget( topicId, postId, {
				editor: {
					confirmLeave: !!mw.user.options.get( 'useeditwarning' )
				}
			} );
			editPostWidget
				.on( 'saveContent', function ( workflow ) {
					editPostWidget.destroy().then( function () {
						editPostWidget.$element.remove();

						// HACK get the old system to rerender the topic
						flowBoard.flowBoardComponentRefreshTopic(
							$topic,
							workflow
						);
					} );
				} )
				.on( 'cancel', function () {
					editPostWidget.destroy().then( function () {
						editPostWidget.$element.replaceWith( $postMain );
					} );
				} );

			$postMain.replaceWith( editPostWidget.$element );
			editPostWidget.activate();

			event.preventDefault();
		} );
	};

	/**
	 * Re-open any edit post widgets with stored data
	 *
	 * @param {jQuery} $container
	 */
	mw.flow.Initializer.prototype.reopenEditPostWidgets = function ( $container ) {
		// Re-open widgets with stored data
		$container.find( '.flow-ui-edit-post-link' ).each( function () {
			var $post = $( this ).closest( '.flow-post' ),
				postId = $post.data( 'flow-id' );

			if ( mw.storage.session.get( 'edit/' + postId + '/ve-docstate' ) ) {
				$( this ).trigger( 'click' );
			}
		} );
	};

	/**
	 * Take over the action of the 'edit topic summary' links
	 * This is delegated, so it applies to all future links as well.
	 */
	mw.flow.Initializer.prototype.setupEditTopicSummaryAction = function () {
		var self = this;

		this.$component
			// Summarize action
			.on( 'click', '.flow-ui-summarize-topic-link', function ( event ) {
				var $topic = $( this ).closest( '.flow-topic' ),
					topicId = $topic.data( 'flow-id' );

				self.startEditTopicSummary( true, topicId );
				event.preventDefault();
			} )
			// Lock action
			.on( 'click', '.flow-ui-topicmenu-lock', function () {
				var promise,
					action = $( this ).data( 'role' ),
					$topic = $( this ).closest( '.flow-topic' ),
					topicId = $topic.data( 'flow-id' ),
					api = new mw.flow.dm.APIHandler();

				if ( action === 'lock' ) {
					promise = api.resolveTopic( topicId );
				} else {
					promise = api.reopenTopic( topicId );
				}

				promise
					.then( function ( workflow ) {
						return self.flowBoard.flowBoardComponentRefreshTopic(
							$topic,
							workflow
						);
					} )
					.then( function ( data ) {
						var revisionId = data.topic.posts[ topicId ],
							revision = data.topic.revisions[ revisionId ],
							summaryContent = OO.getProp( revision, 'summary', 'revision', 'content', 'content' ),
							skipSummarize = action === 'unlock' && !summaryContent;

						if ( !skipSummarize ) {
							self.startEditTopicSummary( true, topicId, action );
						}
					} )
					.fail( function ( code, result ) {
						var errorMsg = self.flowBoard.constructor.static.getApiErrorMessage( code, result );

						self.flowBoard.emit( 'removeError', $topic );
						self.flowBoard.emit( 'showError', $topic, errorMsg );
					} );

				// Prevent default
				return false;
			} );
	};

	/**
	 * Re-open any edit topic summary widgets with stored data
	 *
	 * @param {jQuery} $container
	 */
	mw.flow.Initializer.prototype.reopenEditTopicSummaryWidget = function ( $container ) {
		$container.find( '.flow-ui-summarize-topic-link' ).each( function () {
			var $topic = $( this ).closest( '.flow-topic' ),
				topicId = $topic.data( 'flow-id' );

			if ( mw.storage.session.get( 'edit-summary/' + topicId + '/ve-docstate' ) ) {
				$( this ).trigger( 'click' );
			}
		} );
	};

	/**
	 * Take over the action of the 'edit topic title' links
	 * This is delegated, so it applies to all future links as well.
	 */
	mw.flow.Initializer.prototype.setupEditTopicTitleAction = function () {
		var self = this;

		this.$component
			.on( 'click', 'a.flow-ui-edit-title-link', function ( event ) {
				var $topic = $( this ).closest( '.flow-topic' ),
					topicId = $topic.data( 'flow-id' ),
					$container = $topic.find( '.flow-topic-titlebar-container' ),
					$topicTitleViewMode = $container.find( 'h2.flow-topic-title' ),
					$editForm = $topic.find( '.flow-ui-topicTitleWidget' ),
					widget;

				if ( $editForm.length ) {
					event.preventDefault();
					return false;
				}

				widget = new mw.flow.ui.TopicTitleWidget( topicId, {
					editor: {
						confirmLeave: !!mw.user.options.get( 'useeditwarning' )
					}
				} );
				widget
					.on( 'saveContent', function ( workflow ) {
						widget.$element.remove();

						return self.flowBoard.flowBoardComponentRefreshTopic(
							$topic,
							workflow
						);
					} )
					.on( 'cancel', function () {
						widget.$element.remove();
						$container.prepend( $topicTitleViewMode );
					} );

				$topicTitleViewMode.remove();
				$container.prepend( widget.$element );

				event.preventDefault();
			} );
	};

	/**
	 * Re-open any edit topic title widgets with stored data
	 *
	 * @param {jQuery} $container
	 */
	mw.flow.Initializer.prototype.reopenTopicTitleWidgets = function ( $container ) {
		$container.find( 'a.flow-ui-edit-title-link' ).each( function () {
			var $topic = $( this ).closest( '.flow-topic' ),
				topicId = $topic.data( 'flow-id' );

			if ( mw.storage.session.get( 'edit-topic/' + topicId + '/title' ) ) {
				$( this ).trigger( 'click' );
			}
		} );
	};

	/**
	 * Take over the action of the 'reply' links.  This is delegated,
	 * so it applies to current and future links.
	 */
	mw.flow.Initializer.prototype.setupReplyLinkActions = function () {
		var self = this;

		// Replace the handler used for reply links.
		this.$component.on( 'click', 'a.flow-reply-link', function () {
			// Store the needed details so we can get rid of the URL in JS mode
			var replyWidget,
				existingWidget,
				href = $( this ).attr( 'href' ),
				uri = new mw.Uri( href ),
				replyTo = uri.query.topic_postId,
				$topic = $( this ).closest( '.flow-topic' ),
				placeholder = mw.msg( 'flow-reply-topic-title-placeholder', $topic.find( '.flow-topic-title' ).text().trim() ),
				// replyTo can refer to a post ID or a topic ID
				// For posts, the ReplyWidget should go in .flow-replies
				// For topics, it's directly inside the topic
				$targetContainer = $( '#flow-post-' + replyTo + ' > .flow-replies, #flow-topic-' + replyTo ),
				$existingWidget = $targetContainer.children( '.flow-ui-replyWidget' );

			// Check that there's not already a reply widget existing in the same place
			if ( $existingWidget.length > 0 ) {
				// Focus the existing reply widget
				existingWidget = $existingWidget.data( 'self' );
				existingWidget.activateEditor();
				existingWidget.focus();
				return false;
			}

			replyWidget = new mw.flow.ui.ReplyWidget( $topic.data( 'flowId' ), replyTo, {
				placeholder: placeholder,
				expandable: false,
				editor: {
					confirmLeave: !!mw.user.options.get( 'useeditwarning' )
				}
			} );
			// Create a reference so we can call it from the DOM above
			replyWidget.$element.data( 'self', replyWidget );

			// Add reply form below the post being replied to (WRT max depth)
			$targetContainer.append( replyWidget.$element );
			replyWidget.activateEditor();

			replyWidget
				.on( 'saveContent', function ( workflow ) {
					replyWidget.destroy().then( function () {
						replyWidget.$element.remove();

						// HACK get the old system to rerender the topic
						self.flowBoard.flowBoardComponentRefreshTopic(
							$topic,
							workflow
						);
					} );
				} )
				.on( 'cancel', function () {
					replyWidget.destroy().then( function () {
						replyWidget.$element.remove();
					} );
				} );

			return false;
		} );
	};

	/**
	 * Re-open any reply widgets with stored data
	 *
	 * @param {jQuery} $container
	 */
	mw.flow.Initializer.prototype.reopenReplyWidgets = function ( $container ) {
		var queuedClicks = {};

		$container.find( 'a.flow-reply-link' ).each( function () {
			var href = $( this ).attr( 'href' ),
				uri = new mw.Uri( href ),
				replyTo = uri.query.topic_postId;

			if ( mw.storage.session.get( 'reply/' + replyTo + '/ve-docstate' ) ) {
				// There can be multiple links to reply to a given topicId. They all behave
				// the same but show the widget in a slightly different place.
				// Assume the user wanted to use the last one.
				queuedClicks[ replyTo ] = this;
			}
		} );

		Object.keys( queuedClicks ).forEach( function ( replyTo ) {
			$( queuedClicks[ replyTo ] ).trigger( 'click' );
		} );
	};

	/**
	 * Initialize the edit topic summary action
	 *
	 * @param {boolean} isFullBoard The page is a full board page
	 * @param {string} topicId Topic id
	 * @param {string} [action] Lock action 'lock' or 'unlock'. If not given, the action
	 *  is assumed as summary only.
	 */
	mw.flow.Initializer.prototype.startEditTopicSummary = function ( isFullBoard, topicId, action ) {
		var editTopicSummaryWidget,
			self = this,
			$topic = $( '#flow-topic-' + topicId ),
			$summaryContainer = $topic.find( '.flow-topic-summary-container' ),
			$topicSummary = $summaryContainer.find( '.flow-topic-summary' ),
			editorOptions = {
				confirmLeave: !!mw.user.options.get( 'useeditwarning' )
			},
			pageName = mw.config.get( 'wgPageName' ),
			title = mw.Title.newFromText( pageName );

		if ( !$summaryContainer.length ) {
			return;
		}

		// Don't launch a summary editor when there is one already
		if ( $summaryContainer.find( '.flow-ui-editTopicSummaryWidget' ).length ) {
			return;
		}

		// TODO: This should be managed by the EditTopicSummary widget
		if ( action === 'lock' || action === 'unlock' ) {
			editorOptions.cancelMsgKey = 'flow-skip-summary';
		}

		editTopicSummaryWidget = new mw.flow.ui.EditTopicSummaryWidget( topicId, { editor: editorOptions } );
		editTopicSummaryWidget
			.on( 'saveContent', function ( workflow ) {
				editTopicSummaryWidget.destroy().then( function () {
					editTopicSummaryWidget.$element.remove();

					if ( isFullBoard ) {
						// HACK get the old system to rerender the topic
						self.flowBoard.flowBoardComponentRefreshTopic(
							$topic,
							workflow
						);
					} else {
						// HACK: redirect to topic view
						window.location.href = title.getUrl();
					}
				} );
			} )
			.on( 'cancel', function () {
				editTopicSummaryWidget.destroy().then( function () {
					editTopicSummaryWidget.$element.remove();
					if ( isFullBoard ) {
						$summaryContainer.append( $topicSummary );
					} else {
						// HACK: redirect to topic view
						window.location.href = title.getUrl();
					}
				} );
			} );

		$topicSummary.remove();
		$summaryContainer.append( editTopicSummaryWidget.$element );
		editTopicSummaryWidget.activate();
	};

	/**
	 * Replace the editor in no-js pages, like editing in a separate window
	 *
	 * @param {jQuery} $element The element to conduct the replacements in
	 */
	mw.flow.Initializer.prototype.replaceNoJSEditor = function ( $element ) {
		var editPostWidget,
			$post = $element.parent(),
			$topic = $post.closest( '.flow-topic' ),
			self = this;

		function saveOrCancelHandler( workflow ) {
			editPostWidget.destroy().then( function () {
				editPostWidget.$element.remove();

				// HACK get the old system to rerender the topic
				self.flowBoard.flowBoardComponentRefreshTopic(
					$topic,
					workflow
				);
			} );
		}

		if ( !$element.length ) {
			return;
		}

		editPostWidget = new mw.flow.ui.EditPostWidget( $topic.data( 'flowId' ), $post.data( 'flowId' ), {
			editor: {
				confirmLeave: !!mw.user.options.get( 'useeditwarning' )
			}
		} );

		editPostWidget
			.on( 'saveContent', saveOrCancelHandler )
			// HACK: In this case, we are in an edge case where the topic already
			// loaded with the editor open. We can't trust the content of the editor
			// for displaying the post in case of a 'cancel' event and we don't have
			// the actual content stored in the DOM anywhere else.
			// We must reload the topic -- just like we do on save -- for a cancel
			// event too.
			.on( 'cancel', saveOrCancelHandler.bind( null, $topic.data( 'flowId' ) ) );

		$element.replaceWith( editPostWidget.$element );
		editPostWidget.activate();
	};

	/**
	 * Create an editor widget
	 *
	 * @param {jQuery} $domToReplace The element, usually a form, that the new editor replaces
	 * @param {string} [content] The content of the editing area
	 * @param {string} [saveMsgKey] The message key for the editor save button
	 * @param {string} [id] Editor ID
	 * @return {mw.flow.ui.EditorWidget}
	 */
	mw.flow.Initializer.prototype.createEditorWidget = function ( $domToReplace, content, saveMsgKey, id ) {
		var $wrapper,
			$messages = $( '<div>' ).addClass( 'flow-ui-editorContainerWidget-messages' ),
			isProbablyEditable = mw.config.get( 'wgIsProbablyEditable' ),
			anonWarning = new mw.flow.ui.AnonWarningWidget( {
				isProbablyEditable: isProbablyEditable
			} ),
			canNotEdit = new mw.flow.ui.CanNotEditWidget( new mw.flow.dm.APIHandler(), {
				userGroups: mw.config.get( 'wgUserGroups' ),
				restrictionEdit: mw.config.get( 'wgRestrictionEdit' ),
				isProbablyEditable: isProbablyEditable
			} ),
			captcha = new mw.flow.dm.Captcha(),
			captchaWidget = new mw.flow.ui.CaptchaWidget( captcha ),
			error = new OO.ui.LabelWidget( {
				classes: [ 'flow-ui-boardDescriptionWidget-error flow-errors errorbox' ]
			} ),
			editor = new mw.flow.ui.EditorWidget( {
				saveMsgKey: saveMsgKey,
				confirmLeave: !!mw.user.options.get( 'useeditwarning' ),
				id: id
			} );

		function handleFailure( errorCode, errorObj ) {
			captchaWidget.model.update( errorCode, errorObj );

			if ( !captchaWidget.model.isRequired() ) {
				error.setLabel( new OO.ui.HtmlSnippet( errorObj.error && errorObj.error.info || errorObj.exception ) );
				error.toggle( true );
			}

			editor.popPending();
		}

		error.toggle( false );
		editor.toggle( true );
		anonWarning.toggle( mw.user.isAnon() );
		canNotEdit.toggle( !isProbablyEditable );

		$wrapper = $( '<div>' )
			.append(
				$messages.append(
					error.$element,
					captchaWidget.$element,
					anonWarning.$element,
					canNotEdit.$element
				),
				editor.$element
			);

		$domToReplace.replaceWith( $wrapper );

		// Prepare the editor
		editor.pushPending();
		editor.activate( { content: content || '', format: 'wikitext' } )
			.then( function () {
				editor.popPending();
			} );

		editor
			.on( 'saveContent', function ( content, contentFormat ) {
				var captchaResponse;

				editor.pushPending();

				captchaResponse = captchaWidget.getResponse();

				error.setLabel( '' );
				error.toggle( false );

				// HACK: This is a cheat so that we can have a single function
				// that creates the editor and a single error-handler, but multiple
				// uses, especially for the APIhandler in different cases
				editor.emit( 'afterSaveContent', content, contentFormat, captchaResponse, handleFailure );
			} )
			.on( 'cancel', function () {
				editor.pushPending();
				editor.emit( 'afterCancel' );
				// returnToBoard();
			} );

		return editor;
	};

	/**
	 * Check whether we are on an undo form page
	 *
	 * @return {boolean} The page is an in-progress undo form
	 */
	mw.flow.Initializer.prototype.isUndoForm = function () {
		return !!$( 'form[data-module="topic"], form[data-module="header"], form[data-module="topicsummary"]' ).length;
	};

	mw.flow.Initializer.prototype.setupUndoPage = function () {
		var $undoForm = $( 'form[data-module="topic"], form[data-module="header"], form[data-module="topicsummary"]' ),
			undoType = $undoForm.attr( 'data-module' ),
			pageName = mw.config.get( 'wgPageName' ),
			title = mw.Title.newFromText( pageName ),
			topicId = title.getNameText(),
			postId = $undoForm.find( 'input[name="topic_postId"]' ).val(),
			prevRevId = $undoForm.find( 'input[name="' + undoType + '_prev_revision"]' ).val(),
			content = $undoForm.find( 'textarea' ).val(),
			returnToTitle = function () {
				var url;
				if ( undoType === 'topic' ) {
					// If we're undoing a post edit, redirect to the topic page with the right parameter
					// and fragment to highlight the post
					url = title.getUrl( { topic_showPostId: postId } ) + '#flow-post-' + postId;
				} else {
					// When undoing a topic summary edit, redirect to the topic;
					// when undoing a board description edit, redirect to the board
					url = title.getUrl();
				}
				window.location.href = url;
			},
			apiHandler = new mw.flow.dm.APIHandler(
				title.getPrefixedDb(),
				{ currentRevision: prevRevId }
			),
			save = ( {
				topic: apiHandler.savePost.bind( apiHandler, topicId, postId ),
				header: apiHandler.saveDescription.bind( apiHandler ),
				topicsummary: apiHandler.saveTopicSummary.bind( apiHandler, topicId )
			} )[ undoType ],
			saveMsgKey = ( {
				topic: [
					'flow-post-action-edit-post-submit-anonymously',
					'flow-post-action-edit-post-submit'
				],
				header: [
					'flow-edit-header-submit-anonymously',
					'flow-edit-header-submit'
				],
				topicsummary: [
					'flow-topic-action-update-topic-summary',
					'flow-topic-action-update-topic-summary'
				]
			} )[ undoType ][ mw.user.isAnon() ? 0 : 1 ],
			editor = this.createEditorWidget( $undoForm, content, saveMsgKey, 'undo/' + prevRevId );

		if (
			mw.config.get( 'wgEditSubmitButtonLabelPublish' ) &&
			undoType !== 'topicsummary'
		) {
			// i18n messages:
			// 'flow-post-action-edit-post-submit-anonymously-publish',
			// 'flow-post-action-edit-post-submit-publish'
			// 'flow-edit-header-submit-anonymously-publish',
			// 'flow-edit-header-submit-publish'
			saveMsgKey += '-publish';
		}
		editor
			.on( 'afterSaveContent', function ( content, contentFormat, captcha, handleFailure ) {
				save( content, contentFormat, captcha )
					.then(
						// Success
						returnToTitle,

						// Failure
						handleFailure
					);
			} )
			.on( 'afterCancel', returnToTitle );
	};

	/**
	 * Finish the loading process
	 */
	mw.flow.Initializer.prototype.finishLoading = function () {
		if ( this.$component ) {
			this.$component.addClass( 'flow-component-ready' );
		}
		$( '.flow-ui-load-overlay' ).addClass( 'oo-ui-element-hidden' );
	};
}() );
