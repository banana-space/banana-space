/*!
 * Implements element on-load callbacks for FlowBoardComponent
 */

( function () {
	/**
	 * Binds element load handlers for FlowBoardComponent
	 *
	 * @param {jQuery} $container
	 * @extends FlowComponent
	 * @constructor
	 */
	function FlowBoardComponentLoadEventsMixin() {
		this.bindNodeHandlers( FlowBoardComponentLoadEventsMixin.UI.events );
	}
	OO.initClass( FlowBoardComponentLoadEventsMixin );

	FlowBoardComponentLoadEventsMixin.UI = {
		events: {
			loadHandlers: {}
		}
	};

	//
	// On element-load handlers
	//

	/**
	 * Replaces $time with a new flow-timestamp element generated by TemplateEngine
	 *
	 * @param {jQuery} $time
	 */
	FlowBoardComponentLoadEventsMixin.UI.events.loadHandlers.timestamp = function ( $time ) {
		$time.replaceWith(
			mw.flow.TemplateEngine.callHelper(
				'timestamp',
				parseInt( $time.attr( 'datetime' ), 10 ) * 1000
			)
		);
	};

	// Mixin to FlowBoardComponent
	mw.flow.mixinComponent( 'board', FlowBoardComponentLoadEventsMixin );
}() );
