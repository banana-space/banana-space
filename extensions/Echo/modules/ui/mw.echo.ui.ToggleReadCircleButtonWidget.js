( function () {
	/**
	 * A button showing a circle that represents either 'mark as read' or 'mark as unread' states.
	 *
	 * @class
	 * @extends OO.ui.ButtonWidget
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @cfg {boolean} [markAsRead=true] Display mark as read state. If false, the button displays
	 *  mark as unread state.
	 */
	mw.echo.ui.ToggleReadCircleButtonWidget = function MwEchoUiToggleReadCircleButtonWidget( config ) {
		config = config || {};

		// Parent constructor
		mw.echo.ui.ToggleReadCircleButtonWidget.super.call( this, config );

		this.$circle = $( '<div>' )
			.addClass( 'mw-echo-ui-toggleReadCircleButtonWidget-circle' );
		this.$button.append( this.$circle );

		this.toggleState( config.markAsRead === undefined ? true : !!config.markAsRead );

		this.$element
			.addClass( 'mw-echo-ui-toggleReadCircleButtonWidget' );
	};

	/* Initialization */

	OO.inheritClass( mw.echo.ui.ToggleReadCircleButtonWidget, OO.ui.ButtonWidget );

	/* Methods */

	/**
	 * Toggle the state of the button from 'mark as read' to 'mark as unread'
	 * and vice versa.
	 *
	 * @param {boolean} [isMarkAsRead] The state is mark as read
	 */
	mw.echo.ui.ToggleReadCircleButtonWidget.prototype.toggleState = function ( isMarkAsRead ) {
		isMarkAsRead = isMarkAsRead === undefined ? !this.markAsRead : !!isMarkAsRead;

		this.markAsRead = isMarkAsRead;

		this.$circle.toggleClass( 'mw-echo-ui-toggleReadCircleButtonWidget-circle-unread', !this.markAsRead );
		this.setTitle(
			this.markAsRead ?
				mw.msg( 'echo-notification-markasread' ) :
				mw.msg( 'echo-notification-markasunread' )
		);
	};
}() );
