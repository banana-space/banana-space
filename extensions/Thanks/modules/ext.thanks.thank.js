( function () {
	'use strict';

	mw.thanks = {
		// Keep track of which revisions and comments the user has already thanked for
		thanked: {
			maxHistory: 100,
			cookieName: 'thanks-thanked',
			attrName: 'data-revision-id',

			load: function () {
				var cookie = mw.cookie.get( this.cookieName );
				if ( cookie === null ) {
					return [];
				}
				return unescape( cookie ).split( ',' );
			},

			push: function ( $thankLink ) {
				var saved = this.load();
				saved.push( $thankLink.attr( this.attrName ) );
				if ( saved.length > this.maxHistory ) { // prevent forever growing
					saved = saved.slice( saved.length - this.maxHistory );
				}
				mw.cookie.set( this.cookieName, escape( saved.join( ',' ) ) );
			},

			contains: function ( $thankLink ) {
				return this.load().indexOf( $thankLink.attr( this.attrName ) ) !== -1;
			}
		},

		/**
		 * Retrieve user gender
		 *
		 * @param {string} username Requested username
		 * @return {jQuery.Promise} A promise that resolves with the gender string, 'female', 'male', or 'unknown'
		 */
		getUserGender: function ( username ) {
			return new mw.Api().get( {
				action: 'query',
				list: 'users',
				ususers: username,
				usprop: 'gender'
			} )
				.then(
					function ( result ) {
						return (
							result.query.users[ 0 ] &&
							result.query.users[ 0 ].gender
						) || 'unknown';
					},
					function () {
						return 'unknown';
					}
				);
		}
	};

}() );
