( function () {
	/**
	 * Flow anonymous editor warning widget.
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @cfg {boolean} [isProbablyEditable=true] Whether the content seems to be editable
	 */
	mw.flow.ui.AnonWarningWidget = function mwFlowUiAnonWarningWidget( config ) {
		var returnTo, labelHtml, isProbablyEditable,
			widget = this,
			shouldDisplay;

		config = config || {};

		if ( config.isProbablyEditable !== undefined ) {
			isProbablyEditable = config.isProbablyEditable;
		} else {
			isProbablyEditable = true;
		}

		// If it's not editable, we'll display CanNotEditWidget instead
		shouldDisplay = isProbablyEditable && mw.user.isAnon();

		// Parent constructor
		mw.flow.ui.AnonWarningWidget.super.call( this, config );

		this.icon = new OO.ui.IconWidget( { icon: 'userAnonymous' } );
		this.label = new OO.ui.LabelWidget();

		// HACK: Theme styles get recalculated after timeout
		setTimeout( ( function () {
			widget.icon.$element.addClass( 'oo-ui-image-invert' );
		} ) );

		if ( shouldDisplay ) {
			returnTo = {
				returntoquery: encodeURIComponent( window.location.search ),
				returnto: mw.config.get( 'wgPageName' )
			};
			labelHtml = mw.message( 'flow-anon-warning' )
				.params( [
					mw.util.getUrl( 'Special:Userlogin', returnTo ),
					mw.util.getUrl( 'Special:Userlogin/signup', returnTo )
				] )
				.parse();
			this.label.setLabel( $( $.parseHTML( labelHtml ) ) );
		}

		// Initialize
		this.$element
			.append(
				this.icon.$element,
				this.label.$element
			)
			.addClass( 'flow-ui-anonWarningWidget' )
			.toggleClass( 'flow-ui-anonWarningWidget-active', shouldDisplay );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.AnonWarningWidget, OO.ui.Widget );

}() );
