/*
 * JavaScript for WikiEditor Dialogs
 */
jQuery( function ( $ ) {
	// Replace icons
	$.wikiEditor.modules.dialogs.config.replaceIcons( $( '#wpTextbox1' ) );

	// Add dialogs module
	$( '#wpTextbox1' ).wikiEditor( 'addModule', $.wikiEditor.modules.dialogs.config.getDefaultConfig() );
} );
