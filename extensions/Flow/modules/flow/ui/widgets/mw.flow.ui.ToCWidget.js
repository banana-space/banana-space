( function () {
	/**
	 * Flow ToC widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 * @constructor
	 *
	 * @param {mw.flow.dm.System} system System model
	 * @param {Object} [config]
	 * @cfg {number} [tocPostLimit=50] The number of topics in the ToC per API request
	 */
	mw.flow.ui.ToCWidget = function mwFlowUiToCWidget( system, config ) {
		config = config || {};

		// Parent constructor
		mw.flow.ui.ToCWidget.super.call( this, config );

		this.system = system;
		this.board = this.system.getBoard();
		this.originalButtonLabel = mw.msg( 'flow-board-header-browse-topics-link' );

		this.button = new OO.ui.ButtonWidget( {
			framed: false,
			icon: 'listBullet',
			label: this.originalButtonLabel,
			classes: [ 'flow-ui-tocWidget-button' ]
		} );
		this.topicSelect = new mw.flow.ui.TopicMenuSelectWidget( this.system, {
			classes: [ 'flow-ui-tocWidget-menu' ],
			tocPostLimit: config.tocPostLimit,
			widget: this.button
		} );

		// Events
		this.topicSelect.connect( this, { topic: 'onTopicSelectTopic' } );
		this.button.connect( this, { click: 'onButtonClick' } );

		// Initialize
		this.$element
			.addClass( 'flow-ui-tocWidget' )
			.append(
				this.button.$element,
				this.topicSelect.$element
			);
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.ToCWidget, OO.ui.Widget );

	/* Events */

	/**
	 * Load topic from the ToC
	 *
	 * @event loadTopic
	 * @param {string} topicId Topic id
	 */

	/* Methods */

	/**
	 * Respond to button click
	 */
	mw.flow.ui.ToCWidget.prototype.onButtonClick = function () {
		this.topicSelect.toggle();
	};

	/**
	 * Respond to topic select choose event
	 *
	 * @param {string} topicId Topic id
	 */
	mw.flow.ui.ToCWidget.prototype.onTopicSelectTopic = function ( topicId ) {
		// TODO: This should be changed when the board is widgetized
		var $topic = $( document.getElementById( 'flow-topic-' + topicId ) );

		// TODO: Ideally, we should be able to do this by checking whether the
		// topic is a stub or not. Right now that's not possible because when we
		// scroll, the topics do not unstub themselves, so we can't trust that.
		if ( $topic.length > 0 ) {
			// Scroll down to the topic
			// eslint-disable-next-line no-jquery/no-global-selector
			$( 'html, body' ).animate( {
				scrollTop: ( $topic.offset().top - this.$element.height() ) + 'px'
			}, 'fast' );
		} else {
			// TODO: Widgetize board, topic and post so we can do this
			// through OOUI rather than callbacks from the current system
			this.emit( 'loadTopic', topicId );
		}
	};

	/**
	 * Update the ToC selection
	 *
	 * @param {string} topicId Topic Id
	 */
	mw.flow.ui.ToCWidget.prototype.updateSelection = function ( topicId ) {
		var item = this.board.getItemById( topicId ),
			label = item && item.getContent( 'plaintext' );

		this.topicSelect.selectItemByData( item );
		this.updateLabel( label );
	};

	/**
	 * Update the button label. If no label is given, the button will
	 * retain its original label.
	 *
	 * @param {string} [label] New label
	 */
	mw.flow.ui.ToCWidget.prototype.updateLabel = function ( label ) {
		this.button.setLabel( label || this.originalButtonLabel );
	};
}() );
