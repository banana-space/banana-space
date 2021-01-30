( function () {
	'use strict';

	/**
	 * Creates an input widget with auto-completion for users to be mentioned
	 *
	 * @class
	 * @extends OO.ui.TextInputWidget
	 * @mixins OO.ui.mixin.LookupElement
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @param {string[]} [config.topicPosters] Array of usernames representing posters to this thread,
	 *   without duplicates.
	 */
	mw.flow.ve.ui.MentionTargetInputWidget = function FlowVeUiMentionTargetInputWidget( config ) {
		// Parent constructor
		mw.flow.ve.ui.MentionTargetInputWidget.super.call(
			this,
			$.extend(
				{ placeholder: mw.msg( 'flow-ve-mention-placeholder' ) },
				config
			)
		);

		// Mixin constructor
		OO.ui.mixin.LookupElement.call( this, $.extend( { allowSuggestionsWhenEmpty: true }, config ) );

		// Properties
		this.username = null;
		// Exclude anonymous users, since they do not receive pings.
		this.loggedInTopicPosters = ( config.topicPosters || [] ).filter( function ( poster ) {
			return !mw.util.isIPAddress( poster );
		} );
		// TODO do this in a more sensible place in the future
		mw.flow.ve.userCache.setAsExisting( this.loggedInTopicPosters );

		// Initialization
		this.$element.addClass( 'flow-ve-ui-mentionTargetInputWidget' );
		this.$input.attr( 'aria-label', mw.msg( 'flow-ve-mention-placeholder' ) );
		this.lookupMenu.$element.addClass( 'flow-ve-ui-mentionTargetInputWidget-menu' );
	};

	OO.inheritClass( mw.flow.ve.ui.MentionTargetInputWidget, OO.ui.TextInputWidget );

	OO.mixinClass( mw.flow.ve.ui.MentionTargetInputWidget, OO.ui.mixin.LookupElement );

	/**
	 * Check if the value of the input corresponds to a username that exists.
	 *
	 * Note that this doesn't just check whether the user name is valid (could possibly exist),
	 * it checks whether the user name actually exists. The user is prevented from creating
	 * a mention that points to a nonexistent user.
	 *
	 * @return {jQuery.Promise} Promise resolved with true or false
	 */
	mw.flow.ve.ui.MentionTargetInputWidget.prototype.isValid = function () {
		var username = this.value,
			userNamespace = mw.config.get( 'wgNamespaceIds' ).user,
			title = mw.Title.newFromText( username, userNamespace );
		// First check username is valid (can possibly exist)
		if ( !title || title.getNamespaceId() !== userNamespace ) {
			return $.Deferred().resolve( false ).promise();
		}
		// Then check the user exists
		return mw.flow.ve.userCache.get( this.value ).then(
			function ( user ) {
				return !user.missing && !user.invalid;
			},
			function () {
				// If the API is down or behaving strangely, we shouldn't prevent
				// people from inserting mentions, so if the existence check fails
				// to produce a result, return true so as to not hold things up.
				// We can't get here due to invalid input, because we already checked
				// for that above.
				return $.Deferred().resolve( true ).promise();
			} );
	};

	/**
	 * Gets a promise representing the auto-complete.
	 *
	 * If the input is empty, we suggest the list of users who have already posted to the topic.
	 * If the input is not empty, we use an API call to do a prefix search.
	 *
	 * @return {jQuery.Promise}
	 */
	mw.flow.ve.ui.MentionTargetInputWidget.prototype.getLookupRequest = function () {
		var xhr,
			widget = this,
			initialUpperValue = this.value.charAt( 0 ).toUpperCase() + this.value.slice( 1 );

		if ( this.value === '' ) {
			return $.Deferred()
				.resolve( this.loggedInTopicPosters.slice() )
				.promise( { abort: function () {} } );
		}

		xhr = new mw.Api().get( {
			action: 'query',
			list: 'allusers',
			auprefix: initialUpperValue,
			aulimit: 5,
			rawcontinue: 1
		} );
		return xhr
			.then( function ( data ) {
				var allUsers = ( OO.getProp( data, 'query', 'allusers' ) || [] ).map( function ( user ) {
					mw.flow.ve.userCache.setFromApiData( user );
					return user.name;
				} );
				// Append prefix-matches from the topic list
				return OO.unique( widget.loggedInTopicPosters.filter( function ( poster ) {
					return poster.indexOf( initialUpperValue ) === 0;
				} ).concat( allUsers ) );
			} )
			.promise( { abort: xhr.abort } );
	};

	mw.flow.ve.ui.MentionTargetInputWidget.prototype.getLookupCacheDataFromResponse = function ( data ) {
		return data;
	};

	/**
	 * Converts the raw data to UI objects
	 *
	 * @param {string[]} data User names
	 * @return {OO.ui.MenuOptionWidget[]} Menu items
	 */
	mw.flow.ve.ui.MentionTargetInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
		return data.map( function ( username ) {
			return new OO.ui.MenuOptionWidget( {
				data: username,
				label: username
			} );
		} );
	};

	// Based on ve.ui.MWLinkTargetInputWidget.prototype.initializeLookupMenuSelection
	mw.flow.ve.ui.MentionTargetInputWidget.prototype.initializeLookupMenuSelection = function () {
		var item;
		if ( this.username ) {
			this.lookupMenu.selectItem( this.lookupMenu.findItemFromData( this.username ) );
		}

		item = this.lookupMenu.findSelectedItem();
		if ( !item ) {
			OO.ui.mixin.LookupElement.prototype.initializeLookupMenuSelection.call( this );
		}

		item = this.lookupMenu.findSelectedItem();
		if ( item ) {
			this.username = item.getData();
		}
	};
}() );
