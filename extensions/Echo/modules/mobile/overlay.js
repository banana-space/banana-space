var mobile = mw.mobileFrontend.require( 'mobile.startup' ),
	Overlay = mobile.Overlay,
	list = require( './list.js' ),
	promisedView = mobile.promisedView,
	View = mobile.View,
	Anchor = mobile.Anchor;

/**
 * @param {Overlay} overlay
 * @param {Function} exit
 * @return {void}
 */
function onBeforeExitAnimation( overlay, exit ) {
	if ( 'transition' in overlay.$el[ 0 ].style ) {
		// Manually detach the overlay from DOM once hide animation completes.
		overlay.$el[ 0 ].addEventListener( 'transitionend', exit, { once: true } );

		// Kick off animation.
		overlay.$el[ 0 ].classList.remove( 'visible' );
	} else {
		exit();
	}
}

/**
 * This callback is displayed as a global member.
 *
 * @callback FunctionCountChangeCallback
 * @param {number} count a capped (0-99 or 99+) count
 */

/**
 * Make a notification overlay
 *
 * @param {FunctionCountChangeCallback} onCountChange receives one parameter - a capped (0-99 or 99+) count.
 * @param {Function} onNotificationListRendered a function that is called when the
 *   notifications list has fully rendered (taking no arguments)
 * @param {Function} onBeforeExit
 * @return {Overlay}
 */
function notificationsOverlay( onCountChange, onNotificationListRendered, onBeforeExit ) {
	var markAllReadButton, overlay,
		oouiPromise = mw.loader.using( 'oojs-ui' ).then( function () {
			markAllReadButton = new OO.ui.ButtonWidget( {
				icon: 'checkAll',
				title: mw.msg( 'echo-mark-all-as-read' )
			} );
			return View.make(
				{ class: 'notifications-overlay-header-markAllRead' },
				[ markAllReadButton.$element ]
			);
		} ),
		markAllReadButtonView = promisedView( oouiPromise );
	// hide the button spinner as it is confusing to see in the top right corner
	markAllReadButtonView.$el.hide();

	overlay = Overlay.make(
		{
			heading: '<strong>' + mw.message( 'notifications' ).escaped() + '</strong>',
			footerAnchor: new Anchor( {
				href: mw.util.getUrl( 'Special:Notifications' ),
				progressive: true,
				additionalClassNames: 'footer-link notifications-archive-link',
				label: mw.msg( 'echo-overlay-link' )
			} ).options,
			headerActions: [ markAllReadButtonView ],
			isBorderBox: false,
			className: 'overlay notifications-overlay navigation-drawer',
			onBeforeExit: function ( exit ) {
				onBeforeExit( function () {
					onBeforeExitAnimation( overlay, exit );
				} );
			}
		},
		promisedView(
			oouiPromise.then( function () {
				return list( mw.echo, markAllReadButton, onCountChange,
					onNotificationListRendered );
			} )
		)
	);
	return overlay;
}

module.exports = notificationsOverlay;
