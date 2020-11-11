// Demonstrate JavaScript 'infusion' of PHP-generated widgets.
// Used by widgets.php.

var infuseButton, $demoMenu;

// Helper function to get high resolution profiling data, where available.
function now() {
	return ( window.performance && performance.now ) ? performance.now() :
		Date.now ? Date.now() : new Date().getTime();
}

// Add a button to infuse everything!
// (You wouldn't typically do this: you'd only infuse those objects which you needed to attach
// client-side behaviors to, or where the JS implementation provides additional features over PHP,
// like DropdownInputWidget. We do it here because it's a good overall test.)
function infuseAll() {
	var start, end;
	start = now();
	$( '*[data-ooui]' ).map( function ( _, e ) {
		return OO.ui.infuse( e.id );
	} );
	end = now();
	window.console.log( 'Took ' + ( end - start ) + ' ms to infuse demo page.' );
	infuseButton.setDisabled( true );
}

$demoMenu = $( '.demo-menu' );

OO.ui.getViewportSpacing = function () {
	return {
		top: $demoMenu.outerHeight(),
		right: 0,
		bottom: 0,
		left: 0
	};
};

// More typical usage: we take the existing server-side
// button group and do things to it, here adding a new button.
infuseButton = new OO.ui.ButtonWidget( { label: 'Infuse' } )
	.on( 'click', infuseAll );

OO.ui.ButtonGroupWidget.static.infuse( 'demo-menu-infuse' )
	.addItems( [ infuseButton ] );
