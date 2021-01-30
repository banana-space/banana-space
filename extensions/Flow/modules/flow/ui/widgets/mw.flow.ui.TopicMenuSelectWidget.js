( function () {
	/**
	 * Flow topic list widget
	 *
	 * @class
	 * @extends OO.ui.MenuSelectWidget
	 *
	 * @constructor
	 * @param {mw.flow.dm.System} system System model
	 * @param {Object} [config]
	 * @cfg {number} [tocPostLimit=50] The number of topics in the ToC per API request
	 */
	mw.flow.ui.TopicMenuSelectWidget = function mwFlowUiTopicMenuSelectWidget( system, config ) {
		config = config || {};

		// Parent constructor
		mw.flow.ui.TopicMenuSelectWidget.super.call( this, config );

		// Properties
		this.system = system;
		this.board = this.system.getBoard();
		this.tocPostLimit = config.tocPostLimit || 50;
		// Keep a reference to the topic option widgets by the topic Id
		// so we can call them directly
		this.topics = {};

		// Flags for infinite scroll
		// Mark whether the process of loading is undergoing so we won't trigger it multiple times at once
		this.loadingMoreTopics = false;
		// Mark whether there are no more topics available so we can stop triggering infinite scroll
		this.noMoreTopics = false;

		// Load more option
		this.loadingMoreOptionWidget = new OO.ui.MenuOptionWidget( {
			data: null,
			classes: [ 'flow-ui-topicMenuSelectWidget-loadmore', 'flow-loading' ]
		} );

		// Events
		this.connect( this, { choose: 'onTopicChoose' } );
		this.$element.on( 'scroll', this.onMenuScroll.bind( this ) );
		this.board.connect( this, {
			add: 'addTopics',
			remove: 'removeTopics',
			clear: 'clearTopics',
			topicContentChange: 'onTopicContentChange'
		} );

		// Initialize
		this.$element.addClass( 'flow-ui-topicMenuSelectWidget' );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.TopicMenuSelectWidget, OO.ui.MenuSelectWidget );

	/* Methods */

	mw.flow.ui.TopicMenuSelectWidget.prototype.destroy = function () {
		this.board.disconnect( this );
	};

	/**
	 * Respond to model topic content change and update the ToC content
	 *
	 * @param {mw.flow.dm.Topic} topic Topic
	 */
	mw.flow.ui.TopicMenuSelectWidget.prototype.onTopicContentChange = function ( topic ) {
		var topicWidget = this.topics[ topic.getId() ];

		if ( topicWidget ) {
			topicWidget.setLabel( topic.getContent( 'plaintext' ) );
		}
	};

	/**
	 * Respond to scrolling of the menu. If we are close to the
	 * bottom, call for more topics.
	 *
	 * @return {boolean} False to prevent default event
	 */
	mw.flow.ui.TopicMenuSelectWidget.prototype.onMenuScroll = function () {
		var actualHeight, naturalHeight, scrollTop, isNearBottom;

		// Do nothing if we're already fetching topics
		// or if there are no more topics to fetch
		if ( this.loadingMoreTopics || this.noMoreTopics ) {
			return true;
		}

		actualHeight = this.$element.height();
		naturalHeight = this.$element.prop( 'scrollHeight' );
		scrollTop = this.$element.scrollTop();
		isNearBottom = scrollTop + actualHeight > naturalHeight - 100;

		if ( isNearBottom ) {
			this.getMoreTopics();
		}
	};

	/**
	 * Respond to topic choose
	 *
	 * @param {OO.ui.MenuOptionWidget} item Chosen menu item
	 * @fires topic
	 */
	mw.flow.ui.TopicMenuSelectWidget.prototype.onTopicChoose = function ( item ) {
		var topic = item.getData(),
			topicId = topic && topic.getId();

		if ( topicId ) {
			this.emit( 'topic', topicId );
		}
	};

	/**
	 * Get more topics from the queue
	 *
	 * @return {jQuery.Promise} Promise that is resolved when all
	 *  available topics in the response have been added to the
	 *  flow.dm.Board
	 */
	mw.flow.ui.TopicMenuSelectWidget.prototype.getMoreTopics = function () {
		var widget = this;

		this.loadingMoreTopics = true;
		return this.system.fetchMoreTopics()
			.then( function ( hasMoreTopicsInApi ) {
				widget.noMoreTopics = !hasMoreTopicsInApi;
				if ( widget.noMoreTopics ) {
					// Remove the load more widget
					widget.removeItems( [ widget.loadingMoreOptionWidget ] );
				}
			} )
			.always( function () {
				widget.loadingMoreTopics = false;
			} );
	};

	/**
	 * Add topics to the ToC list
	 *
	 * @param {mw.flow.dm.Topic[]} items Topic data items
	 * @param {number} index Location to add
	 */
	mw.flow.ui.TopicMenuSelectWidget.prototype.addTopics = function ( items, index ) {
		var i, len, optionWidget,
			widgets = [];

		for ( i = 0, len = items.length; i < len; i++ ) {
			optionWidget = this.topics[ items[ i ].getId() ];
			if ( !optionWidget ) {
				optionWidget = new OO.ui.MenuOptionWidget( {
					data: items[ i ],
					label: items[ i ].getContent( 'plaintext' ),
					classes: items[ i ].getModerationState() === 'lock' ?
						[ 'flow-ui-topicMenuSelectWidget-locked' ] :
						[]
				} );
			}
			widgets.push( optionWidget );
		}

		this.addItems( widgets, index );

		// Move the 'load more' to the end
		if ( !this.noMoreTopics ) {
			this.addItems( [ this.loadingMoreOptionWidget ] );
		}
	};

	/**
	 * Clear all topics from the ToC list
	 */
	mw.flow.ui.TopicMenuSelectWidget.prototype.clearTopics = function () {
		this.clearItems();
		this.topics = {};
	};

	/**
	 * Remove topics from the ToC list
	 *
	 * @param {mw.flow.dm.Topic[]} items Topic data items to remove
	 */
	mw.flow.ui.TopicMenuSelectWidget.prototype.removeTopics = function ( items ) {
		var i, len, itemId, optionWidget,
			widgets = [];

		for ( i = 0, len = items.length; i < len; i++ ) {
			itemId = items[ i ].getId();
			optionWidget = this.topics[ itemId ];
			widgets.push( optionWidget );
		}

		this.removeItems( widgets );
	};

	/**
	 * Extend addItems to also add to the topic reference item
	 *
	 * @param {OO.ui.OptionWidget[]} items Items to add
	 * @param {number} [index] Index to insert items after
	 */
	mw.flow.ui.TopicMenuSelectWidget.prototype.addItems = function ( items ) {
		var i, len;

		for ( i = 0, len = items.length; i < len; i++ ) {
			if ( items[ i ].getData() ) {
				this.topics[ items[ i ].getData().getId() ] = items[ i ];
			}
		}

		// Parent method
		mw.flow.ui.TopicMenuSelectWidget.super.prototype.addItems.apply( this, arguments );
	};

	/**
	 * Extend removeItems to also remove to the topic reference item
	 *
	 * @param {OO.ui.OptionWidget[]} items Items to remove
	 */
	mw.flow.ui.TopicMenuSelectWidget.prototype.removeItems = function ( items ) {
		var i, len;

		for ( i = 0, len = items.length; i < len; i++ ) {
			if ( items[ i ].getData() ) {
				delete this.topics[ items[ i ].getData().getId() ];
			}
		}

		// Parent method
		mw.flow.ui.TopicMenuSelectWidget.super.prototype.removeItems.apply( this, arguments );
	};

}() );
