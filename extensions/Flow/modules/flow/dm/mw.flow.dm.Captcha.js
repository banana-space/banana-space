( function () {
	/**
	 * Data model for a (potential) CAPTCHA.  This is always used; it will just return false for
	 * isRequired() if no user interaction is required.
	 *
	 * @class
	 *
	 * @constructor
	 */
	mw.flow.dm.Captcha = function mwFlowDmCaptcha() {
		// Mixin constructors
		OO.EventEmitter.call( this );
	};

	/* Setup */

	OO.initClass( mw.flow.dm.Captcha );

	/* Inheritance */

	OO.mixinClass( mw.flow.dm.Captcha, OO.EventEmitter );

	/* Events */
	/**
	 * Update to CAPTCHA information
	 *
	 * @event update
	 * @param {boolean} isRequired Whether CAPTCHA is now required
	 * @param {OO.ui.HtmlSnippet|null} content Content of CAPTCHA, or null
	 */

	/* Methods */

	/**
	 * Updates based on server-provided error information
	 *
	 * @param {string} errorCode Server-provided error code
	 * @param {Object} errorObj Server-provided error object
	 */
	mw.flow.dm.Captcha.prototype.update = function ( errorCode, errorObj ) {
		this.required = /spamfilter$/.test( errorCode ) && errorObj.error.spamfilter.messageKey === 'flow-spam-confirmedit-form';

		this.renderingInformation = null;
		if ( this.required ) {
			this.renderingInformation = errorObj.error.spamfilter.details;
			this.renderingInformation.html = new OO.ui.HtmlSnippet( this.renderingInformation.html );
		}

		this.emit( 'update', this.required, this.renderingInformation );
	};

	/**
	 * Checks whether the CAPTCHA is required
	 *
	 * @return {boolean} True if the user must fill in a CAPTCHA
	 */
	mw.flow.dm.Captcha.prototype.isRequired = function () {
		return this.required;
	};

	/**
	 * Gets the CAPTCHA rendering information.  This is in the data model because it's
	 * server-generated.
	 *
	 * @return {OO.ui.HtmlSnippet} HTML snippet for CAPTCHA, or null if there is none.
	 */
	mw.flow.dm.Captcha.prototype.getRenderingInformation = function () {
		return this.renderingInformation;
	};
}() );
