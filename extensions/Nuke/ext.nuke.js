/**
 * JavaScript for the Nuke MediaWiki extension.
 * @see https://www.mediawiki.org/wiki/Extension:Nuke
 *
 * @licence GNU GPL v2 or later
 * @author Jeroen De Dauw <jeroendedauw at gmail dot com>
 */

( function ( $ ) {
	'use strict';

	$( document ).ready( function () {

		function selectPages( check ) {
			$( 'input[type=checkbox]' ).prop( 'checked', check );
		}

		$( '#toggleall' ).click( function () {
			selectPages( true );
		} );
		$( '#togglenone' ).click( function () {
			selectPages( false );
		} );
		$( '#toggleinvert' ).click( function () {
			$( 'input[type="checkbox"]' ).each( function () {
				$( this ).prop( 'checked', !$( this ).is( ':checked' ) );
			} );
		} );
	} );
}( jQuery ) );
