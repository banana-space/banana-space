$( function () {
	// sidebar-chunk only applies to desktop-small, but the toggles are hidden at
	// other resolutions regardless and the css overrides any visible effects.
	var $dropdowns = $( '#personal, #p-variants-desktop, .sidebar-chunk' );

	/**
	 * Desktop menu click-toggling
	 *
	 * We're not even checking if it's desktop because the classes in play have no effect
	 * on mobile regardless... this may break things at some point, though.
	 */

	/**
	 * Close all dropdowns
	 */
	function closeOpen() {
		$dropdowns.removeClass( 'dropdown-active' );
	}

	/**
	 * Click behaviour
	 */
	$dropdowns.on( 'click', function ( e ) {
		// Check if it's already open so we don't open it again
		// eslint-disable-next-line no-jquery/no-class-state
		if ( $( this ).hasClass( 'dropdown-active' ) ) {
			if ( $( e.target ).closest( $( 'h2, #p-variants-desktop h3' ) ).length > 0 ) {
				// treat reclick on the header as a toggle
				closeOpen();
			}
			// Clicked inside an open menu; don't do anything
		} else {
			closeOpen();
			e.stopPropagation(); // stop hiding it!
			$( this ).addClass( 'dropdown-active' );
		}
	} );
	$( document ).on( 'click', function ( e ) {
		if ( $( e.target ).closest( $dropdowns ).length > 0 ) {
			// Clicked inside an open menu; don't close anything
		} else {
			closeOpen();
		}
	} );
} );
