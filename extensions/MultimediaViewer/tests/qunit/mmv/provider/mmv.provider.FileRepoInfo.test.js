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

( function ( mw, $ ) {
	QUnit.module( 'mmv.provider.FileRepoInfo', QUnit.newMwEnvironment() );

	QUnit.test( 'FileRepoInfo constructor sanity check', function ( assert ) {
		var api = { get: function () {} },
			fileRepoInfoProvider = new mw.mmv.provider.FileRepoInfo( api );

		assert.ok( fileRepoInfoProvider );
	} );

	QUnit.test( 'FileRepoInfo get test', function ( assert ) {
		var apiCallCount = 0,
			api = { get: function () {
				apiCallCount++;
				return $.Deferred().resolve( {
					query: {
						repos: [
							{
								name: 'shared',
								displayname: 'Wikimedia Commons',
								rootUrl: '//upload.beta.wmflabs.org/wikipedia/commons',
								local: false,
								url: '//upload.beta.wmflabs.org/wikipedia/commons',
								thumbUrl: '//upload.beta.wmflabs.org/wikipedia/commons/thumb',
								initialCapital: true,
								descBaseUrl: '//commons.wikimedia.beta.wmflabs.org/wiki/File:',
								scriptDirUrl: '//commons.wikimedia.beta.wmflabs.org/w',
								fetchDescription: true,
								favicon: 'http://en.wikipedia.org/favicon.ico'
							},
							{
								name: 'wikimediacommons',
								displayname: 'Wikimedia Commons',
								rootUrl: '//upload.beta.wmflabs.org/wikipedia/en',
								local: false,
								url: '//upload.beta.wmflabs.org/wikipedia/en',
								thumbUrl: '//upload.beta.wmflabs.org/wikipedia/en/thumb',
								initialCapital: true,
								scriptDirUrl: 'http://commons.wikimedia.org/w',
								fetchDescription: true,
								descriptionCacheExpiry: 43200,
								apiurl: 'http://commons.wikimedia.org/w/api.php',
								articlepath: '/wiki/$1',
								server: '//commons.wikimedia.org',
								favicon: '//commons.wikimedia.org/favicon.ico'
							},
							{
								name: 'local',
								displayname: null,
								rootUrl: '//upload.beta.wmflabs.org/wikipedia/en',
								local: true,
								url: '//upload.beta.wmflabs.org/wikipedia/en',
								thumbUrl: '//upload.beta.wmflabs.org/wikipedia/en/thumb',
								initialCapital: true,
								scriptDirUrl: '/w',
								favicon: 'http://en.wikipedia.org/favicon.ico'
							}
						]
					}
				} );
			} },
			fileRepoInfoProvider = new mw.mmv.provider.FileRepoInfo( api );

		return fileRepoInfoProvider.get().then( function ( repos ) {
			assert.strictEqual( repos.shared.displayName,
				'Wikimedia Commons', 'displayName is set correctly' );
			assert.strictEqual( repos.shared.favIcon,
				'http://en.wikipedia.org/favicon.ico', 'favIcon is set correctly' );
			assert.strictEqual( repos.shared.isLocal, false, 'isLocal is set correctly' );
			assert.strictEqual( repos.shared.descBaseUrl,
				'//commons.wikimedia.beta.wmflabs.org/wiki/File:', 'descBaseUrl is set correctly' );

			assert.strictEqual( repos.wikimediacommons.displayName,
				'Wikimedia Commons', 'displayName is set correctly' );
			assert.strictEqual( repos.wikimediacommons.favIcon,
				'//commons.wikimedia.org/favicon.ico', 'favIcon is set correctly' );
			assert.strictEqual( repos.wikimediacommons.isLocal, false, 'isLocal is set correctly' );
			assert.strictEqual( repos.wikimediacommons.apiUrl,
				'http://commons.wikimedia.org/w/api.php', 'apiUrl is set correctly' );
			assert.strictEqual( repos.wikimediacommons.server,
				'//commons.wikimedia.org', 'server is set correctly' );
			assert.strictEqual( repos.wikimediacommons.articlePath,
				'/wiki/$1', 'articlePath is set correctly' );

			assert.strictEqual( repos.local.displayName, null, 'displayName is set correctly' );
			assert.strictEqual( repos.local.favIcon,
				'http://en.wikipedia.org/favicon.ico', 'favIcon is set correctly' );
			assert.strictEqual( repos.local.isLocal, true, 'isLocal is set correctly' );
		} ).then( function () {
			// call the data provider a second time to check caching
			return fileRepoInfoProvider.get();
		} ).then( function () {
			assert.strictEqual( apiCallCount, 1 );
		} );
	} );

	QUnit.test( 'FileRepoInfo fail test', function ( assert ) {
		var api = { get: function () {
				return $.Deferred().resolve( {} );
			} },
			done = assert.async(),
			fileRepoInfoProvider = new mw.mmv.provider.FileRepoInfo( api );

		fileRepoInfoProvider.get().fail( function () {
			assert.ok( true, 'promise rejected when no data is returned' );
			done();
		} );
	} );
}( mediaWiki, jQuery ) );
