/*
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

( function () {

	/**
	 * Gets file repo information.
	 *
	 * @class mw.mmv.provider.FileRepoInfo
	 * @extends mw.mmv.provider.Api
	 * @constructor
	 * @param {mw.Api} api
	 * @param {Object} [options]
	 * @cfg {number} [maxage] cache expiration time, in seconds
	 *  Will be used for both client-side cache (maxage) and reverse proxies (s-maxage)
	 */
	function FileRepoInfo( api, options ) {
		mw.mmv.provider.Api.call( this, api, options );
	}
	OO.inheritClass( FileRepoInfo, mw.mmv.provider.Api );

	/**
	 * Runs an API GET request to get the repo info.
	 *
	 * @return {jQuery.Promise.<Object.<string, mw.mmv.model.Repo>>} a promise which resolves to
	 *     a hash of mw.mmv.model.Repo objects, indexed by repo names.
	 */
	FileRepoInfo.prototype.get = function () {
		var provider = this;

		return this.getCachedPromise( '*', function () {
			return provider.apiGetWithMaxAge( {
				action: 'query',
				meta: 'filerepoinfo',
				uselang: 'content'
			} ).then( function ( data ) {
				return provider.getQueryField( 'repos', data );
			} ).then( function ( reposArray ) {
				var reposHash = {};
				reposArray.forEach( function ( repo ) {
					reposHash[ repo.name ] = mw.mmv.model.Repo.newFromRepoInfo( repo );
				} );
				return reposHash;
			} );
		} );
	};

	mw.mmv.provider.FileRepoInfo = FileRepoInfo;
}() );
