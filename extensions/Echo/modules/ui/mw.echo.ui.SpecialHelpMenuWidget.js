( function () {
	/**
	 * Widget for the settings menu in the Special:Notifications page
	 *
	 * @param {mw.echo.dm.ModelManager} manager Model manager
	 * @param {Object} config Configuration object
	 * @cfg {string} [prefLink] Link to preferences page
	 */
	mw.echo.ui.SpecialHelpMenuWidget = function MwEchoUiSpecialHelpMenuWidget( manager, config ) {
		config = config || {};

		// Parent constructor
		mw.echo.ui.SpecialHelpMenuWidget.super.call( this, $.extend( {
			icon: 'settings',
			label: mw.msg( 'echo-specialpage-special-help-menu-widget-aria-label' ),
			indicator: 'down',
			invisibleLabel: true,
			menu: {
				classes: [ 'mw-echo-ui-specialHelpMenuWidget-menu' ],
				horizontalPosition: 'end',
				width: 'auto'
			}
		}, config ) );

		this.manager = manager;

		this.markAllReadOption = new OO.ui.MenuOptionWidget( {
			icon: 'checkAll',
			label: this.getMarkAllReadOptionLabel(),
			data: 'markAllRead'
		} );
		this.markAllReadOption.toggle( false );

		this.menu.addItems( [ this.markAllReadOption ] );
		if ( config.prefLink ) {
			this.menu.addItems( [
				// Preferences link
				new OO.ui.MenuOptionWidget( {
					// Use link for accessibility
					$element: $( '<a>' ).attr( 'href', config.prefLink ),
					icon: 'settings',
					label: mw.msg( 'mypreferences' ),
					data: { href: config.prefLink }
				} )
			] );
		}

		// Events
		this.manager.connect( this, {
			localCountChange: 'onLocalCountChange'
		} );
		this.manager.getFiltersModel().getSourcePagesModel().connect( this, { update: 'onSourcePageUpdate' } );
		this.menu.connect( this, { choose: 'onMenuChoose' } );

		this.$element.addClass( 'mw-echo-ui-specialHelpMenuWidget' );
	};

	/* Initialization */

	OO.inheritClass( mw.echo.ui.SpecialHelpMenuWidget, OO.ui.ButtonMenuSelectWidget );

	/* Events */

	/**
	 * @event markAllRead
	 *
	 * Mark all notifications as read in the selected wiki
	 */

	/* Methods */

	/**
	 * Respond to source page change
	 */
	mw.echo.ui.SpecialHelpMenuWidget.prototype.onSourcePageUpdate = function () {
		this.markAllReadOption.setLabel( this.getMarkAllReadOptionLabel() );
	};

	/**
	 * Respond to local counter update event
	 *
	 * @param {number} count New count
	 */
	mw.echo.ui.SpecialHelpMenuWidget.prototype.onLocalCountChange = function ( count ) {
		this.markAllReadOption.toggle( count > 0 );
	};

	/**
	 * Handle menu choose events
	 *
	 * @param {OO.ui.MenuOptionWidget} item Chosen item
	 */
	mw.echo.ui.SpecialHelpMenuWidget.prototype.onMenuChoose = function ( item ) {
		var data = item.getData();
		if ( data.href ) {
			location.href = data.href;
		} else if ( data === 'markAllRead' ) {
			// Log this action
			mw.echo.logger.logInteraction(
				mw.echo.Logger.static.actions.markAllReadClick,
				mw.echo.Logger.static.context.archive,
				null, // Notification ID is irrelevant
				this.manager.getTypeString(), // The type of the list in general
				null, // The Logger has logic to decide whether this is mobile or not
				this.manager.getFiltersModel().getSourcePagesModel().getCurrentSource() // Source name
			);
			this.emit( 'markAllRead' );
		}
	};

	/**
	 * Build the button label
	 *
	 * @return {string} Mark all read button label
	 */
	mw.echo.ui.SpecialHelpMenuWidget.prototype.getMarkAllReadOptionLabel = function () {
		var pageModel = this.manager.getFiltersModel().getSourcePagesModel(),
			source = pageModel.getCurrentSource(),
			sourceTitle = pageModel.getSourceTitle( source );

		return sourceTitle ?
			mw.msg( 'echo-mark-wiki-as-read', sourceTitle ) :
			mw.msg( 'echo-mark-all-as-read' );
	};

}() );
