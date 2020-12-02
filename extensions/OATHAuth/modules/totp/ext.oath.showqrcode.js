$( function () {
	// eslint-disable-next-line no-jquery/no-global-selector
	var $elm = $( '.mw-display-qrcode' );
	$elm.qrcode( $elm.data( 'mw-qrcode-url' ) );
} );
