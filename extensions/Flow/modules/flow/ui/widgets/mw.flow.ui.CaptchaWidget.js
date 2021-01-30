( function () {
	/**
	 * This implements the UI portion of the CAPTCHA.
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {mw.flow.dm.Captcha} model
	 * @param {Object} [config]
	 */
	mw.flow.ui.CaptchaWidget = function mwFlowUiCaptchaWidget( model, config ) {
		// Parent constructor
		mw.flow.ui.CaptchaWidget.super.call( this, config );

		this.toggle( false );

		this.model = model;
		this.model.connect( this, {
			update: 'onUpdate'
		} );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.CaptchaWidget, OO.ui.LabelWidget );

	/* Methods */

	/**
	 * Gets the CAPTCHA information, if any.
	 *
	 * @return {Object|null} captcha CAPTCHA information
	 * @return {string} captcha.id CAPTCHA ID
	 * @return {string} captcha.answer CAPTCHA answer (user-provided)
	 */
	mw.flow.ui.CaptchaWidget.prototype.getResponse = function () {
		var $captchaField = this.$element.find( '[name="wpCaptchaWord"]' ),
			captcha = null;

		if ( $captchaField.length > 0 ) {
			captcha = {
				id: this.$element.find( '[name="wpCaptchaId"]' ).val(),
				answer: $captchaField.val()
			};
		}

		return captcha;
	};

	/**
	 * Updates the widget in response to event
	 *
	 * @param {boolean} isRequired Whether a CAPTCHA is required
	 * @param {Object} renderingInformation Information needed to render CAPTCHA
	 * @param {string} renderingInformation.html Main HTML
	 * @param {Array} [renderingInformation.modules] Array of ResourceLoader module names
	 * @param {Array} [renderingInformation.modulestyles] Array of ResourceLoader module names to be
	 *   included as style-only modules.
	 * @param {Array} [renderingInformation.headitems] Array of head items (see OutputPage::addHeadItems) (raw HTML
	 *   strings)
	 */
	mw.flow.ui.CaptchaWidget.prototype.onUpdate = function ( isRequired, renderingInformation ) {
		var modules, moduleStyles, allModules;

		if ( isRequired ) {
			if ( renderingInformation.headitems ) {
				$( document.head ).append( renderingInformation.headitems.join( '' ) );
			}

			moduleStyles = renderingInformation.modulestyles || [];
			modules = renderingInformation.modules || [];

			allModules = moduleStyles.concat( modules );
			mw.loader.using( allModules ).fail( function () {
				OO.ui.alert( mw.message( 'flow-spam-confirmedit-using-failure' ).text() );
			} ).always( function () {
				this.setLabel( renderingInformation.html );
				this.toggle( true );
			}.bind( this ) );
		} else {
			this.toggle( false );
			this.setLabel( '' );
		}
	};
}() );
