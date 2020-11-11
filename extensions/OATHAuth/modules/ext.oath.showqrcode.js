(function ( $ ) {
	$( function () {
		var $elm = $( '.mw-display-qrcode' );
		$elm.qrcode( $elm.data( 'mw-qrcode-url' ) );
	} );
} )( jQuery );
