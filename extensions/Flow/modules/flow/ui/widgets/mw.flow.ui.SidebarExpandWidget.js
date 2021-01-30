( function () {
	/**
	 * Flow sidebar expand widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} [config] Configuration object
	 * @cfg {boolean} [collapsed=false] Start as collapsed
	 * @cfg {string} [expandedButtonTitle] Title for the button when expanded
	 * @cfg {string} [collapsedButtonTitle] Title for the button when collapsed
	 */
	mw.flow.ui.SidebarExpandWidget = function mwFlowUiSidebarExpandWidget( config ) {
		config = config || {};

		// Parent constructor
		mw.flow.ui.SidebarExpandWidget.super.apply( this, arguments );

		this.expandedButtonTitle = config.expandedButtonTitle || mw.msg( 'flow-board-collapse-description' );
		this.collapsedButtonTitle = config.collapsedButtonTitle || mw.msg( 'flow-board-expand-description' );

		this.button = new OO.ui.ButtonWidget( {
			framed: false
		} );

		this.toggleCollapsed( !!config.collapsed );

		// Events
		this.button.connect( this, { click: 'onButtonClick' } );

		this.$element
			.addClass( 'flow-ui-sidebarExpandWidget' )
			.append( this.button.$element );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.SidebarExpandWidget, OO.ui.Widget );

	mw.flow.ui.SidebarExpandWidget.prototype.onButtonClick = function () {
		this.toggleCollapsed();
	};

	/**
	 * Toggle collapsed state
	 *
	 * @param {boolean} collapse Widget is collapsed
	 */
	mw.flow.ui.SidebarExpandWidget.prototype.toggleCollapsed = function ( collapse ) {
		var siderailState;

		collapse = collapse !== undefined ? collapse : !this.collapsed;

		if ( this.collapsed !== collapse ) {
			this.collapsed = collapse;

			this.$element.toggleClass( 'flow-ui-sidebarExpandWidget-collapsed', this.collapsed );

			this.button.setIcon( this.collapsed ? 'topicExpand' : 'topicCollapse' );
			this.button.setTitle( this.collapsed ? this.collapsedButtonTitle : this.expandedButtonTitle );

			// Change the preference
			siderailState = this.collapsed ? 'collapsed' : 'expanded';
			if ( !mw.user.isAnon() && mw.user.options.get( 'flow-side-rail-state' ) !== siderailState ) {
				// update the user preferences; no preferences for anons

				new mw.Api().saveOption( 'flow-side-rail-state', siderailState );
				// ensure we also see that preference in the current page
				mw.user.options.set( 'flow-side-rail-state', siderailState );
			}

			this.emit( 'toggle', this.collapsed );
		}
	};

	/**
	 * Get the collapsed state of the widget
	 *
	 * @return {boolean} collapse Widget is collapsed
	 */
	mw.flow.ui.SidebarExpandWidget.prototype.isCollapsed = function () {
		return this.collapsed;
	};
}() );
