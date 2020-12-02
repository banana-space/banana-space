( function () {
	'use strict';

	function invertSelections() {
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
	}

	$( function () {
		var $checkboxes = $( '#powersearch input[id^=mw-search-ns]' );

		$( '#replacetext-invert' )
			.on( 'click', invertSelections )
			.prop( 'disabled', false );

		// Create check all/none button
		$( '#mw-search-togglebox' ).append(
			$( '<label>' )
				.text( mw.msg( 'powersearch-togglelabel' ) )
		).append(
			$( '<input>' ).attr( 'type', 'button' )
				.attr( 'id', 'mw-search-toggleall' )
				.prop( 'value', mw.msg( 'powersearch-toggleall' ) )
				.click( function () {
					$checkboxes.prop( 'checked', true );
				} )
		).append(
			$( '<input>' ).attr( 'type', 'button' )
				.attr( 'id', 'mw-search-togglenone' )
				.prop( 'value', mw.msg( 'powersearch-togglenone' ) )
				.click( function () {
					$checkboxes.prop( 'checked', false );
				} )
		);
	} );
}() );
