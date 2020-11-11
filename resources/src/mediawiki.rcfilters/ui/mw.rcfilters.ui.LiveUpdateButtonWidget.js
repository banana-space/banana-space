( function ( mw ) {
	/**
	 * Widget for toggling live updates
	 *
	 * @extends OO.ui.ToggleButtonWidget
	 *
	 * @constructor
	 * @param {mw.rcfilters.Controller} controller
	 * @param {mw.rcfilters.dm.ChangesListViewModel} changesListModel
	 * @param {Object} [config] Configuration object
	 */
	mw.rcfilters.ui.LiveUpdateButtonWidget = function MwRcfiltersUiLiveUpdateButtonWidget( controller, changesListModel, config ) {
		config = config || {};

		// Parent
		mw.rcfilters.ui.LiveUpdateButtonWidget.parent.call( this, $.extend( {
			label: mw.message( 'rcfilters-liveupdates-button' ).text()
		}, config ) );

		this.controller = controller;
		this.model = changesListModel;

		// Events
		this.connect( this, { click: 'onClick' } );
		this.model.connect( this, { liveUpdateChange: 'onLiveUpdateChange' } );

		this.$element.addClass( 'mw-rcfilters-ui-liveUpdateButtonWidget' );

		this.setState( false );
	};

	/* Initialization */

	OO.inheritClass( mw.rcfilters.ui.LiveUpdateButtonWidget, OO.ui.ToggleButtonWidget );

	/* Methods */

	/**
	 * Respond to the button being clicked
	 */
	mw.rcfilters.ui.LiveUpdateButtonWidget.prototype.onClick = function () {
		this.controller.toggleLiveUpdate();
	};

	/**
	 * Set the button's state and change its appearance
	 *
	 * @param {boolean} enable Whether the 'live update' feature is now on/off
	 */
	mw.rcfilters.ui.LiveUpdateButtonWidget.prototype.setState = function ( enable ) {
		this.setValue( enable );
		this.setIcon( enable ? 'stop' : 'play' );
		this.setTitle( mw.message(
			enable ?
				'rcfilters-liveupdates-button-title-on' :
				'rcfilters-liveupdates-button-title-off'
		).text() );
	};

	/**
	 * Respond to the 'live update' feature being turned on/off
	 *
	 * @param {boolean} enable Whether the 'live update' feature is now on/off
	 */
	mw.rcfilters.ui.LiveUpdateButtonWidget.prototype.onLiveUpdateChange = function ( enable ) {
		this.setState( enable );
	};

}( mediaWiki ) );
