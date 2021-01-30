/** @class jQuery.fn */
( function () {
	/**
	 * Scrolls the viewport to fit $el into view only if necessary. Scenarios:
	 * 1. If el starts above viewport, scrolls to put top of el at top of viewport.
	 * 2. If el ends below viewport and fits into viewport, scrolls to put bottom of el at bottom of viewport.
	 * 3. If el ends below viewport but is taller than the viewport, scrolls to put top of el at top of viewport.
	 *
	 * @param {string|number} [speed='fast']
	 * @return {jQuery}
	 */
	$.fn.conditionalScrollIntoView = function ( speed ) {
		speed = speed !== undefined ? speed : 'fast';

		// We queue this to happen on the element, because we need to wait for it to finish performing its own
		// animations (eg. it might be doing a slideDown), even though THIS actual animation occurs on body.
		this.queue( function () {
			var $this = $( this ),
				viewportY = $( window ).scrollTop(),
				viewportHeight = $( window ).height(),
				elOffset = $this.offset(),
				elHeight = $this.outerHeight(),
				scrollTo = -1;

			if ( elOffset.top < viewportY ) {
				// Element starts above viewport; put el top at top
				scrollTo = elOffset.top;
			} else if ( elOffset.top + elHeight > viewportY + viewportHeight ) {
				// Element ends below viewport
				if ( elHeight > viewportHeight ) {
					// Too tall to fit into viewport; put el top at top
					scrollTo = elOffset.top;
				} else {
					// Fits into viewport; put el bottom at bottom
					scrollTo = elOffset.top + elHeight - viewportHeight;
				}
			} // else: element is already in viewport.

			if ( scrollTo > -1 ) {
				// Scroll the viewport to display this element
				// eslint-disable-next-line no-jquery/no-global-selector
				$( 'html, body' ).animate( { scrollTop: scrollTo }, speed, function () {
					// Fire off the next fx queue on the main element when we finish scrolling the window
					$this.dequeue();
				} );
			} else {
				// If we don't have to scroll, continue to the next fx queue item immediately
				$this.dequeue();
			}
		} );

		// Do nothing
		return this;
	};
}() );
