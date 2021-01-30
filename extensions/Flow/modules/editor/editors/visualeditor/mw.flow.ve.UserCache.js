( function () {
	'use strict';

	/**
	 * Cache information about users.
	 *
	 * @class
	 * @extends ve.init.mw.ApiResponseCache
	 * @constructor
	 */
	mw.flow.ve.UserCache = function FlowVeUserCache() {
		// Parent constructor
		mw.flow.ve.UserCache.super.apply( this, arguments );
	};

	/* Inheritance */

	OO.inheritClass( mw.flow.ve.UserCache, ve.init.mw.ApiResponseCache );

	/* Static methods */

	mw.flow.ve.UserCache.static.normalizeTitle = function ( title ) {
		var titleObj = mw.Title.newFromText( title, mw.config.get( 'wgNamespaceIds' ).user );
		if ( !titleObj ) {
			return title;
		}
		return titleObj.getMainText();
	};

	mw.flow.ve.UserCache.static.processPage = function ( page ) {
		return {
			invalid: page.invalid !== undefined,
			missing: page.missing !== undefined,
			userid: page.userid
		};
	};

	/* Methods */

	mw.flow.ve.UserCache.prototype.getRequestPromise = function ( subqueue ) {
		var xhr = new mw.Api().get(
			{
				action: 'query',
				list: 'users',
				ususers: subqueue.join( '|' )
			},
			{ type: 'POST' }
		);
		return xhr
			.then( function ( data ) {
				// The parent class wants data like { query: { pages: { userid: { data } } } }
				var i, len, user, newData = {};

				if ( !data.query || !data.query.users ) {
					return data;
				}

				for ( i = 0, len = data.query.users.length; i < len; i++ ) {
					user = data.query.users[ i ];
					// Parent class needs .title
					user.title = user.name;
					newData[ i ] = user;
				}
				return { query: { pages: newData } };
			} )
			.promise( { abort: xhr.abort } );
	};

	/**
	 * Add data from a partial API response
	 *
	 * @param {Object} apiData API data for a single item
	 */
	mw.flow.ve.UserCache.prototype.setFromApiData = function ( apiData ) {
		var cacheData = {};
		cacheData[ apiData.name ] = this.constructor.static.processPage( apiData );
		this.set( cacheData );
	};

	/**
	 * Mark a list of user names as valid and existing.
	 *
	 * @param {string|string[]} usernames One or more user names
	 */
	mw.flow.ve.UserCache.prototype.setAsExisting = function ( usernames ) {
		var i, len, cacheData = {};
		if ( typeof usernames === 'string' ) {
			usernames = [ usernames ];
		}
		for ( i = 0, len = usernames.length; i < len; i++ ) {
			cacheData[ usernames[ i ] ] = { missing: false, invalid: false };
		}
		this.set( cacheData );
	};

	// TODO we need a platform class or some other place to put this
	mw.flow.ve.userCache = new mw.flow.ve.UserCache();
}() );
