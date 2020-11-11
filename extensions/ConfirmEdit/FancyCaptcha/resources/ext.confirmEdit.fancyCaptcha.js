( function ( $, mw ) {
	$( document ).on( 'click', '.fancycaptcha-reload', function () {
		var $this = $( this ),
			$root = $this.closest( '.fancycaptcha-captcha-container' ),
			$captchaImage = $root.find( '.fancycaptcha-image' );

		$this.addClass( 'fancycaptcha-reload-loading' );

		// AJAX request to get captcha index key
		new mw.Api().post( { action: 'fancycaptchareload' } ).done( function ( data ) {
			var captchaIndex = data.fancycaptchareload.index,
				imgSrc;
			if ( typeof captchaIndex === 'string' ) {
				// replace index key with a new one for captcha image
				imgSrc = $captchaImage.attr( 'src' ).replace( /(wpCaptchaId=)\w+/, '$1' + captchaIndex );
				$captchaImage.attr( 'src', imgSrc );

				// replace index key with a new one for hidden tag
				$( '#mw-input-captchaId' ).val( captchaIndex );
				$( '#mw-input-captchaWord' ).val( '' ).focus();

				// now do the same with a selector that works for pre-1.27 login forms
				$root.find( '[name="wpCaptchaId"]' ).val( captchaIndex );
				$root.find( '[name="wpCaptchaWord"]' ).val( '' ).focus();
			}
		} )
			.always( function () {
				$this.removeClass( 'fancycaptcha-reload-loading' );
			} );

		return false;
	} );
}( jQuery, mediaWiki ) );
