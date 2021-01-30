( function () {
	/**
	 * Flow navigation widget
	 *
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {mw.flow.dm.System} system System model
	 * @param {Object} [config]
	 * @cfg {number} [tocPostLimit=50] The number of topics in the ToC per API request
	 * @cfg {string} [defaultSort='newest'] The current default topic sort order
	 */
	mw.flow.ui.NavigationWidget = function mwFlowUiNavigationWidget( system, config ) {
		config = config || {};

		// Parent constructor
		mw.flow.ui.NavigationWidget.super.call( this, config );

		this.board = system.getBoard();

		this.tocWidget = new mw.flow.ui.ToCWidget( system, {
			classes: [ 'flow-ui-navigationWidget-tocWidget' ],
			tocPostLimit: config.tocPostLimit
		} );

		this.reorderTopicsWidget = new mw.flow.ui.ReorderTopicsWidget( this.board, config );

		// Events
		$( window ).on( 'scroll resize', this.onWindowScroll.bind( this ) );
		this.tocWidget.connect( this, { loadTopic: 'onToCWidgetLoadTopic' } );
		this.reorderTopicsWidget.connect( this, { reorder: 'onReorderTopicsWidgetReorder' } );

		// Initialize
		this.$element
			.append(
				this.tocWidget.$element,
				this.reorderTopicsWidget.$element
			)
			.addClass( 'flow-ui-navigationWidget' );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.NavigationWidget, OO.ui.Widget );

	/* Methods */

	/**
	 * Propagate the scrollto event so the old code can
	 * work on it.
	 *
	 * @param {string} topicId Topic id
	 * @fires loadTopic
	 */
	mw.flow.ui.NavigationWidget.prototype.onToCWidgetLoadTopic = function ( topicId ) {
		this.emit( 'loadTopic', topicId );
	};

	/**
	 * Propagate the reorder event from the reorderTopicsWidget
	 * so the old code can be updated
	 *
	 * @param {string} order New order
	 * @fires reorderTopics
	 */
	mw.flow.ui.NavigationWidget.prototype.onReorderTopicsWidgetReorder = function ( order ) {
		this.emit( 'reorderTopics', order );
	};

	/**
	 * Respond to window scroll
	 */
	mw.flow.ui.NavigationWidget.prototype.onWindowScroll = function () {
		var scrollTop, isScrolledDown, topicId,
			/*!
			 * Check if element is in the viewport.
			 *
			 * @param {jQuery} $el Element to test
			 * @return {boolean} Element is in screen
			 */
			isElementInView = function ( $el ) {
				var scrollTop, containerHeight,
					height = $el.height(),
					top = $el.offset().top,
					bottom = top + height;

				scrollTop = $( window ).scrollTop();
				containerHeight = $( window ).height();

				return (
					// Topic top is visible
					(
						top >= scrollTop &&
						top <= scrollTop + containerHeight
					) ||
					// Topic bottom is visible
					(
						bottom >= scrollTop &&
						bottom <= scrollTop + containerHeight
					) ||
					// Topic is long and we are in the middle of it
					(
						top < scrollTop &&
						bottom > scrollTop + containerHeight
					)
				);
			};

		// HACK: Quit if the widget is unattached. This happens when we are
		// waiting to rebuild the board when reordering the topics
		// This should not be needed when the board is wigdetized
		if ( this.$element.parent().length === 0 ) {
			return;
		}

		scrollTop = $( window ).scrollTop();
		isScrolledDown = scrollTop >= this.$element.parent().offset().top;

		if ( isScrolledDown ) {
			// TODO use binary search
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.flow-topic' ).each( function () {
				if ( isElementInView( $( this ) ) ) {
					topicId = $( this ).data( 'flowId' );
					return false;
				}
			} );
		}
		// Update the toc selection
		this.tocWidget.updateSelection( topicId );

		// Fix the widget to the top when we scroll down below its original
		// location
		this.$element.toggleClass(
			'flow-ui-navigationWidget-affixed',
			isScrolledDown
		);
		if ( isScrolledDown ) {
			// Copy width from parent, width: 100% doesn't do what we want when
			// position: fixed; is set
			this.$element.css( 'width', this.$element.parent().width() );
		} else {
			// Unset width when we no longer have position: fixed;
			this.$element.css( 'width', '' );
		}

		this.reorderTopicsWidget.toggle( !isScrolledDown );
	};
}() );
