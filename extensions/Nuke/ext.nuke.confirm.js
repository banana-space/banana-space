( function ( mw, $ ) {
	$( function () {
		/*global confirm */

		// Confirm nuke
		$( 'form[name="nukelist"]' ).on( 'submit', function () {
			var pages = $( this ).find( 'input[name="pages[]"][type="checkbox"]:checked' );
			if ( pages.length ) {
				return confirm( mw.msg( 'nuke-confirm', pages.length ) );
			}
		} );

	} );
}( mediaWiki, jQuery ) );
