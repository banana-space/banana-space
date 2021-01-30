var NOTIFICATIONS_PATH = '/notifications';

/**
 * @fires echo.mobile every time the notifications overlay is opened
 */
function onOpenNotificationsOverlay() {
	mw.hook( 'echo.mobile' ).fire( true );
}

/**
 * @fires echo.mobile every time the notifications overlay is closed
 */
function onCloseNotificationsOverlay() {
	mw.hook( 'echo.mobile' ).fire( false );
}

/*
 * This code loads the necessary modules for the notifications overlay, not to be confused
 * with the Toast notifications defined by common/toast.js.
 */
module.exports = function () {
	var badge,
		notificationsFilterOverlay = require( './notificationsFilterOverlay.js' ),
		notificationsOverlay = require( './overlay.js' ),
		router = require( 'mediawiki.router' ),
		overlayManager = mw.mobileFrontend.require( 'mobile.startup' ).OverlayManager.getSingleton(),
		NotificationBadge = require( './NotificationBadge.js' ),
		initialized = false;

	function showNotificationOverlay() {
		var overlay = notificationsOverlay( badge.setCount.bind( badge ),
			badge.markAsSeen.bind( badge ), function ( exit ) {
				onCloseNotificationsOverlay();
				exit();
			} );
		onOpenNotificationsOverlay();

		return overlay;
	}

	// Once the DOM is loaded hijack the notifications button to display an overlay rather
	// than linking to Special:Notifications.
	$( function () {
		badge = new NotificationBadge( {
			onClick: function ( ev ) {
				router.navigate( '#' + NOTIFICATIONS_PATH );
				// prevent navigation to original Special:Notifications URL
				// DO NOT USE stopPropagation or you'll break click tracking in WikimediaEvents
				ev.preventDefault();
			},
			// eslint-disable-next-line no-jquery/no-global-selector
			el: $( '#user-notifications.user-button' ).parent()
		} );
		overlayManager.add( /^\/notifications$/, showNotificationOverlay );

		/**
		 * Adds a filter button to the UI inside notificationsInboxWidget
		 *
		 * @method
		 * @ignore
		 */
		function addFilterButton() {
			// Create filter button once the notifications overlay has been loaded
			var filterStatusButton = new OO.ui.ButtonWidget(
				{
					href: '#/notifications-filter',
					classes: [ 'mw-echo-ui-notificationsInboxWidget-main-toolbar-nav-filter-placeholder' ],
					icon: 'funnel',
					label: mw.msg( 'echo-mobile-notifications-filter-title' )
				} );

			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.mw-echo-ui-notificationsInboxWidget-cell-placeholder' ).append(
				$( '<div>' )
					.addClass( 'mw-echo-ui-notificationsInboxWidget-main-toolbar-nav-filter' )
					.addClass( 'mw-echo-ui-notificationsInboxWidget-cell' )
					.append( filterStatusButton.$element )
			);
		}

		// This code will currently only be invoked on Special:Notifications
		// The code is bundled here since it makes use of loadModuleScript. This also allows
		// the possibility of invoking the filter from outside the Special page in future.
		// Once the 'ext.echo.special.onInitialize' hook has fired, load notification filter.
		mw.hook( 'ext.echo.special.onInitialize' ).add( function () {
			// eslint-disable-next-line no-jquery/no-global-selector
			var $crossWikiUnreadFilter = $( '.mw-echo-ui-crossWikiUnreadFilterWidget' ),
				// eslint-disable-next-line no-jquery/no-global-selector
				$notifReadState = $( '.mw-echo-ui-notificationsInboxWidget-main-toolbar-readState' );

			// The 'ext.echo.special.onInitialize' hook is fired whenever special page notification
			// changes display on click of a filter.
			// Hence the hook is restricted from firing more than once.
			if ( initialized ) {
				return;
			}

			// setup the filter button (now we have OOjs UI)
			addFilterButton();

			// setup route
			overlayManager.add( /^\/notifications-filter$/, function () {
				onOpenNotificationsOverlay();
				return notificationsFilterOverlay( {
					onBeforeExit: function ( exit ) {
						onCloseNotificationsOverlay();
						exit();
					},
					$notifReadState: $notifReadState,
					$crossWikiUnreadFilter: $crossWikiUnreadFilter
				} );
			} );
			initialized = true;
		} );
	} );

};
