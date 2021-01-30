var mobile = mw.mobileFrontend.require( 'mobile.startup' ),
	util = mobile.util,
	View = mobile.View,
	promisedView = mobile.promisedView;

/**
 * This callback is displayed as a global member.
 *
 * @callback FunctionCountChangeCallback
 * @param {number} count a capped (0-99 or 99+) count
 */

/**
 * List of notifications
 *
 * @param {mw.echo} echo class
 * @param {OO.ui.ButtonWidget} markAllReadButton - a button that will be associated with the
 *  read status of the notifications list.
 * @param {FunctionCountChangeCallback} onCountChange callback.
 * @param {Function} onNotificationListRendered a function that is called when the
 *   notifications list has fully rendered (taking no arguments)
 * @return {View}
 */
function notificationsList( echo, markAllReadButton, onCountChange, onNotificationListRendered ) {
	var wrapperWidget,
		maxNotificationCount = mw.config.get( 'wgEchoMaxNotificationCount' ),
		echoApi = new echo.api.EchoApi(),
		unreadCounter = new echo.dm.UnreadNotificationCounter( echoApi, 'all', maxNotificationCount ),
		modelManager = new echo.dm.ModelManager( unreadCounter, { type: [ 'message', 'alert' ] } ),
		controller = new echo.Controller(
			echoApi,
			modelManager,
			{
				type: [ 'message', 'alert' ]
			}
		),
		markAsReadHandler = function () {
			markAllReadButton.toggle(
				controller.manager.hasLocalUnread()
			);
		},
		// Create a container which will be revealed when "more options" (...)
		// is clicked on a notification. Hidden by default.
		$moreOptions = util.parseHTML( '<div>' )
			.addClass( 'notifications-overlay-overlay position-fixed' );

	echo.config.maxPrioritizedActions = 1;

	wrapperWidget = new echo.ui.NotificationsWrapper( controller, modelManager, {
		$overlay: $moreOptions
	} );

	// Events
	unreadCounter.on( 'countChange', function ( count ) {
		onCountChange(
			controller.manager.getUnreadCounter().getCappedNotificationCount( count )
		);
		markAsReadHandler();
	} );
	markAllReadButton.on( 'click', function () {
		var numNotifications = controller.manager.getLocalUnread().length;

		controller.markLocalNotificationsRead()
			.then( function () {
				mw.notify( mw.msg( 'echo-mark-all-as-read-confirmation', numNotifications ) );
				markAllReadButton.toggle( false );
			}, function () {
				markAllReadButton.toggle( false );
			} );
	} );

	return promisedView(
		// Populate notifications
		wrapperWidget.populate().then( function () {
			controller.updateSeenTime();
			onNotificationListRendered();
			markAsReadHandler();
			// Connect event here as we know that everything loaded correctly
			modelManager.on( 'update', markAsReadHandler );
			return View.make( {}, [ wrapperWidget.$element, $moreOptions ] );
		} )
	);
}

module.exports = notificationsList;
