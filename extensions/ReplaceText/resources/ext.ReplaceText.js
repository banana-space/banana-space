window.invertSelections = function () {
	'use strict';

	var form = document.getElementById( 'choose_pages' ),
		numElements = form.elements.length,
		i,
		curElement;

	for ( i = 0; i < numElements; i++ ) {
		curElement = form.elements[ i ];

		if ( curElement.type === 'checkbox' && curElement.id !== 'create-redirect' &&
			curElement.id !== 'watch-pages' && curElement.id !== 'doAnnounce' ) {
			form.elements[ i ].checked = form.elements[ i ].checked !== true;
		}
	}
};

( function ( $ ) {
	$( function () {
		$( '#replacetext-invert' )
			.on( 'click', function () {
				window.invertSelections();
			} )
			.prop( 'disabled', false );
	} );
}( jQuery ) );
