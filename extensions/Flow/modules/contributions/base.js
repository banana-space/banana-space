/*!
 * This file provides a shim to load Flow when clicking an interactive
 * Flow link.
 */
( function () {
	function clickedFlowLink( event ) {
		var $container = $( event.delegateTarget ),
			onComplete = function () {
				$( event.target ).trigger( 'click' );
			};

		event.preventDefault();

		$container
			.addClass( 'flow-component' )
			.data( 'flow-component', 'boardHistory' );

		// if successfull, flow will now handle clicking the target
		// If that failed still run the onComplete, it will not trigger
		// our handler and be a normal click this time.
		mw.loader.using(
			[ 'ext.flow', 'mediawiki.ui.input' ],
			onComplete,
			onComplete
		);
	}

	$( function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#bodyContent' ).one( 'click', '.flow-click-interactive', clickedFlowLink );
	} );
}() );
