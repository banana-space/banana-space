( function () {
	// Should be refined later to handle different scenarios (block/protect/etc.) explicitly.
	/**
	 * Flow error widget for when the user can not edit/post/etc.
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {mw.flow.dm.APIHandler} api API handler
	 * @param {Object} [config] Configuration options
	 * @cfg {Array} [userGroups=[]] Current user's groups
	 * @cfg {Array} [restrictionEdit] List of groups that are allowed to edit, or empty
	 *   array for no restrictions.
	 * @cfg {boolean} [isProbablyEditable=true] Whether the user probably has the right to
	 *   edit this page.  If true, they may be able to post.  If false, they can not.
	 *   For performance reasons to avoid pre-computing with 100% accuracy.
	 */
	mw.flow.ui.CanNotEditWidget = function mwFlowUiCanNotEditWidget( api, config ) {
		var widget = this;

		this.api = api;

		config = config || {};

		if ( config.isProbablyEditable !== undefined ) {
			this.isProbablyEditable = config.isProbablyEditable;
		} else {
			this.isProbablyEditable = true;
		}

		this.userGroups = config.userGroups || [];

		// Empty array means "no protection restrictions on edit", so we'll treat it as a generic permissions
		// error.
		this.restrictionEdit = config.restrictionEdit || [];

		this.icon = new OO.ui.IconWidget( { icon: 'lock' } );
		this.label = new OO.ui.LabelWidget();

		// Parent constructor
		mw.flow.ui.CanNotEditWidget.super.call( this, config );

		// Initialize
		this.$element
			.append(
				this.icon.$element,
				this.label.$element
			)
			.addClass( 'flow-ui-canNotEditWidget' )
			.toggleClass( 'flow-ui-canNotEditWidget-active', !this.isProbablyEditable );

		if ( !this.isProbablyEditable ) {
			// Initial generic message, which the real one loads
			this.label.setLabel( $( $.parseHTML( this.getGenericMessage().parse() ) ) );

			this.getMessage().done( function ( message ) {
				// 'blocked' is never triggered by the quick check, so that is not
				// mentioned in the message.  So it could be 'protected' (which is specially
				// handled), but could also be lack of 'createtalk', etc.
				var labelHtml = message.parse();
				widget.label.setLabel( $( $.parseHTML( labelHtml ) ) );
			} );
		}
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.CanNotEditWidget, OO.ui.Widget );

	/* Static */
	// Cache message per page load, since it may require AJAX
	mw.flow.ui.CanNotEditWidget.static.getMessagePromise = null;

	/* Methods */

	/**
	 * Gets message explaining why the user can not edit the page.  This should only
	 * be called if the page is (probably) not editable.
	 *
	 * @return {jQuery.Promise} Promise resolving with message to use for error
	 * @return {Function} return.done
	 * @return {mw.Message} return.done.message
	 */
	mw.flow.ui.CanNotEditWidget.prototype.getMessage = function () {
		var message, messageKey, isStandardProtection, dfd;

		if ( mw.flow.ui.CanNotEditWidget.static.getMessagePromise !== null ) {
			return mw.flow.ui.CanNotEditWidget.static.getMessagePromise;
		}

		dfd = $.Deferred();
		mw.flow.ui.CanNotEditWidget.static.getMessagePromise = dfd.promise();

		if ( !this.isProbablyEditable ) {
			// Check if there is standard protection

			if ( this.isMissingRequiredGroup( 'autoconfirmed' ) ) {
				messageKey = mw.user.isAnon() ?
					'flow-error-protected-autoconfirmed-logged-out' :
					'flow-error-protected-autoconfirmed-logged-in';

				isStandardProtection = true;
			} else if ( this.isMissingRequiredGroup( 'sysop' ) ) {
				messageKey = mw.user.isAnon() ?
					'flow-error-protected-sysop-logged-out' :
					'flow-error-protected-sysop-logged-in';

				isStandardProtection = true;
			}

			if ( isStandardProtection ) {
				this.api.getProtectionReason().done( function ( reason ) {
					// Includes empty string
					if ( !reason ) {
						reason = mw.message( 'flow-error-protected-unknown-reason' ).text();
					}

					// Message keys are documented above
					// eslint-disable-next-line mediawiki/msg-doc
					message = mw.message( messageKey, reason );

					dfd.resolve( message );
				} ).fail( function () {
					// Message keys are documented above
					// eslint-disable-next-line mediawiki/msg-doc
					message = mw.message( messageKey, mw.message( 'flow-error-protected-unknown-reason' ).text() );

					dfd.resolve( message );
				} );
			} else {
				dfd.resolve( this.getGenericMessage() );
			}
		}

		return mw.flow.ui.CanNotEditWidget.static.getMessagePromise;
	};

	/**
	 * Gets generic message when the user can not edit, but we can not say exactly why
	 *
	 * @return {mw.Message} Message to use for error
	 */
	mw.flow.ui.CanNotEditWidget.prototype.getGenericMessage = function () {
		return mw.message(
			mw.user.isAnon() ?
				'flow-error-can-not-edit-logged-out' :
				'flow-error-can-not-edit-logged-in',
			mw.user
		);
	};

	/**
	 * Check if the specified group is required to edit and they lack it.
	 *
	 * @param {string} groupName
	 * @return {boolean} The group is both required to edit and missing
	 */
	mw.flow.ui.CanNotEditWidget.prototype.isMissingRequiredGroup = function ( groupName ) {
		var isGroupRequired = this.restrictionEdit.indexOf( groupName ) !== -1,
			userGroups = this.userGroups,
			acceptableGroups;

		if ( isGroupRequired ) {
			acceptableGroups = [ groupName ];

			if ( groupName === 'autoconfirmed' ) {
				// Hack: 'confirmed' is equivalent to 'autoconfirmed', except assigned manually.
				// Both groups normally have the 'autoconfirmed' right, but rights are not available without an AJAX request.
				acceptableGroups.push( 'confirmed' );
			}

			return acceptableGroups.every( function ( group ) {
				return userGroups.indexOf( group ) === -1;
			} );
		} else {
			return false;
		}
	};
}() );
