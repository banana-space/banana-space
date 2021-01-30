/*!
 * Implements element interactive handler callbacks for FlowBoardComponent
 */

( function () {
	/**
	 * Binds element interactive (click) handlers for FlowBoardComponent
	 *
	 * @param {jQuery} $container
	 * @extends FlowComponent
	 * @constructor
	 */
	function FlowBoardComponentInteractiveEventsMixin() {
		this.bindNodeHandlers( FlowBoardComponentInteractiveEventsMixin.UI.events );
	}
	OO.initClass( FlowBoardComponentInteractiveEventsMixin );

	FlowBoardComponentInteractiveEventsMixin.UI = {
		events: {
			interactiveHandlers: {}
		}
	};

	//
	// interactive handlers
	//

	/**
	 * Toggles collapse state
	 *
	 * @param {Event} event
	 * @return {jQuery.Promise}
	 */
	FlowBoardComponentInteractiveEventsMixin.UI.events.interactiveHandlers.collapserCollapsibleToggle = function ( event ) {
		var $target = $( this ).closest( '.flow-element-collapsible' ),
			$deferred = $.Deferred(),
			updateTitle = function ( element, state ) {
				var titleDataAttribute = state + '-title',
					$element = $( element ),
					title = $element.data( titleDataAttribute );

				if ( title ) {
					$element.attr( 'title', title );
				}
			};

		// Ignore clicks on links inside of collapsible areas
		if ( this !== event.target && $( event.target ).is( 'a' ) ) {
			return $deferred.resolve().promise();
		}

		// Ignore clicks on the editor
		if ( $( event.target ).is( '.flow-ui-editorWidget *' ) ) {
			return $deferred.resolve().promise();
		}

		if ( $target.is( '.flow-element-collapsed' ) ) {
			$target.removeClass( 'flow-element-collapsed' ).addClass( 'flow-element-expanded' );
			updateTitle( this, 'expanded' );
		} else {
			$target.addClass( 'flow-element-collapsed' ).removeClass( 'flow-element-expanded' );
			updateTitle( this, 'collapsed' );
		}

		return $deferred.resolve().promise();
	};

	// @todo remove these data-flow handler forwarder callbacks when data-mwui handlers are implemented
	$( [ 'close', 'prevOrClose', 'nextOrSubmit', 'prev', 'next' ] ).each( function ( i, fn ) {
		// Assigns each handler with the prefix 'modal', eg. 'close' becomes 'modalClose'
		FlowBoardComponentInteractiveEventsMixin.UI.events.interactiveHandlers[ 'modal' + fn.charAt( 0 ).toUpperCase() + fn.substr( 1 ) ] = function ( event ) {
			event.preventDefault();

			// eg. call mw.Modal.close( this );
			mw.Modal[ fn ]( this );
		};
	} );

	// Mixin to FlowBoardComponent
	mw.flow.mixinComponent( 'board', FlowBoardComponentInteractiveEventsMixin );
}() );
