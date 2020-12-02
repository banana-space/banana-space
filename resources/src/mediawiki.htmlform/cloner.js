/*
 * HTMLForm enhancements:
 * Add/remove cloner clones without having to resubmit the form.
 */
( function () {

	var cloneCounter = 0;

	/**
	 * Appends a new row with fields to the cloner.
	 *
	 * @ignore
	 * @param {jQuery} $createButton
	 */
	function appendToCloner( $createButton ) {
		var $li,
			$ul = $createButton.prev( 'ul.mw-htmlform-cloner-ul' ),
			html = $ul.data( 'template' ).replace(
				new RegExp( mw.util.escapeRegExp( $ul.data( 'uniqueId' ) ), 'g' ),
				'clone' + ( ++cloneCounter )
			);

		$li = $( '<li>' )
			.addClass( 'mw-htmlform-cloner-li' )
			.html( html )
			.appendTo( $ul );

		mw.hook( 'htmlform.enhance' ).fire( $li );
	}

	mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
		var $deleteElement = $root.find( '.mw-htmlform-cloner-delete-button' ),
			$createElement = $root.find( '.mw-htmlform-cloner-create-button' ),
			createButton;

		$deleteElement.each( function () {
			var $element = $( this ),
				deleteButton;

			// eslint-disable-next-line no-jquery/no-class-state
			if ( $element.hasClass( 'oo-ui-widget' ) ) {
				deleteButton = OO.ui.infuse( $element );
				deleteButton.on( 'click', function () {
					deleteButton.$element.closest( 'li.mw-htmlform-cloner-li' ).remove();
				} );
			} else {
				// eslint-disable-next-line no-jquery/no-sizzle
				$element.filter( ':input' ).on( 'click', function ( e ) {
					e.preventDefault();
					$( this ).closest( 'li.mw-htmlform-cloner-li' ).remove();
				} );
			}
		} );

		// eslint-disable-next-line no-jquery/no-class-state
		if ( $createElement.hasClass( 'oo-ui-widget' ) ) {
			createButton = OO.ui.infuse( $createElement );
			createButton.on( 'click', function () {
				appendToCloner( createButton.$element );
			} );
		} else {
			// eslint-disable-next-line no-jquery/no-sizzle
			$createElement.filter( ':input' ).on( 'click', function ( e ) {
				e.preventDefault();

				appendToCloner( $( this ) );
			} );
		}
	} );

}() );
