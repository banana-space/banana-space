/*!
 * VisualEditor minimal demo
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

( function () {
	/* eslint-disable no-jquery/no-global-selector */
	var $instance = $( '.ve-instance' ),
		$output = $( '.ve-demo-output' );

	/* eslint-enable no-jquery/no-global-selector */
	// Set up the platform and wait for i18n messages to load
	new ve.init.sa.Platform( ve.messagePaths ).getInitializedPromise()
		.fail( function () {
			$instance.text( 'Sorry, this browser is not supported.' );
		} )
		.done( function () {
			var toHtmlButton, fromHtmlButton, convertButtons, htmlInput,
				// Create the target
				target = new ve.init.sa.Target();

			// Append the target to the document
			$instance.append( target.$element );

			function setSurface( html ) {
				// Create a document model for a new surface
				target.clearSurfaces();
				target.addSurface(
					ve.dm.converter.getModelFromDom(
						ve.createDocumentFromHtml( html ),
						// Optional: Document language, directionality (ltr/rtl)
						{ lang: $.i18n().locale, dir: $( document.body ).css( 'direction' ) }
					)
				);
			}

			setSurface( '<p><b>Hello,</b> <i>World!</i></p>' );

			// Button and textarea for showing HTML output
			toHtmlButton = new OO.ui.ButtonWidget( { label: 'Convert to HTML', icon: 'expand' } ).on( 'click', function () {
				// Get the current HTML from the surface and display
				htmlInput.setValue( target.getSurface().getHtml() );
			} );
			fromHtmlButton = new OO.ui.ButtonWidget( { label: 'Convert from HTML', icon: 'collapse' } ).on( 'click', function () {
				setSurface( htmlInput.getValue() );
			} );
			convertButtons = new OO.ui.ButtonGroupWidget( {
				items: [
					toHtmlButton, fromHtmlButton
				]
			} );

			htmlInput = new OO.ui.MultilineTextInputWidget( {
				autosize: true,
				classes: [ 've-demo-html' ]
			} );

			$output.append(
				convertButtons.$element,
				htmlInput.$element
			);
		} );
}() );
