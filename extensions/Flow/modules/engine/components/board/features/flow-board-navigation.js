/*!
 * Contains board navigation header, which affixes to the viewport on scroll.
 */

( function () {
	/**
	 * Binds handlers for the board header itself.
	 *
	 * @class
	 * @constructor
	 * @param {jQuery} $container
	 * @this FlowComponent
	 */
	function FlowBoardComponentBoardHeaderFeatureMixin() {
		// Bind element handlers
		this.bindNodeHandlers( FlowBoardComponentBoardHeaderFeatureMixin.UI.events );

		/** {string} topic ID currently being read in viewport */
		this.readingTopicId = null;

		/** {Object} Map from topic id to its last update timestamp for sorting */
		this.updateTimestampsByTopicId = {};
	}
	OO.initClass( FlowBoardComponentBoardHeaderFeatureMixin );

	FlowBoardComponentBoardHeaderFeatureMixin.UI = {
		events: {
			apiPreHandlers: {},
			apiHandlers: {},
			interactiveHandlers: {},
			loadHandlers: {}
		}
	};

	//
	// Prototype methods
	//

	//
	// API pre-handlers
	//

	//
	// On element-click handlers
	//

	//
	// On element-load handlers
	//

	/**
	 * Bind the navigation header bar to the window.scroll event.
	 *
	 * @param {jQuery} $boardNavigation
	 */
	function flowBoardLoadEventsBoardNavigation( $boardNavigation ) {
		// initialize the board topicId sorting callback.  This expects to be rendered
		// as a sibling of the topiclist component.  The topiclist component includes
		// information about how it is currently sorted, so we can maintain that in the
		// TOC. This is typically either 'newest' or 'updated'.
		this.topicIdSort = $boardNavigation.siblings( '[data-flow-sortby]' ).data( 'flow-sortby' );

	}
	FlowBoardComponentBoardHeaderFeatureMixin.UI.events.loadHandlers.boardNavigation = flowBoardLoadEventsBoardNavigation;

	//
	// Private functions
	//

	// Mixin to FlowComponent
	mw.flow.mixinComponent( 'component', FlowBoardComponentBoardHeaderFeatureMixin );
}() );
