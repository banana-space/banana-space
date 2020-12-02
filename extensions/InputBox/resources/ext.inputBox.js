/**
 * Disable InputBox submit button when the corresponding text input field is empty.
 *
 * @author Tony Thomas
 * @license http://opensource.org/licenses/MIT MIT License
 */
( function ( $, mw ) {
	'use strict';
	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		var $input = $content.find( '.createboxInput:not([type=hidden])' ),
			onChange = function () {
				var $textbox = $( this ),
					$submit = $textbox.data( 'form-submit' );

				if ( !$submit ) {
					$submit = $textbox.nextAll( 'input.createboxButton' ).first();
					$textbox.data( 'form-submit', $submit );
				}

				$submit.prop( 'disabled', $textbox.val().length < 1 );
			}, i;

		for ( i = 0; i < $input.length; i++ ) {
			onChange.call( $input.get( i ) );
		}

		$input.on( 'keyup input change', $.debounce( 50, onChange ) );
	} );
}( jQuery, mediaWiki ) );
