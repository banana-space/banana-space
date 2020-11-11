/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( mw ) {
	QUnit.module( 'mmv.model.Repo', QUnit.newMwEnvironment() );

	QUnit.test( 'Repo constructor sanity check', function ( assert ) {
		var displayName = 'Wikimedia Commons',
			favicon = '//commons.wikimedia.org/favicon.ico',
			apiUrl = '//commons.wikimedia.org/w/api.php',
			server = '//commons.wikimedia.org',
			articlePath = '//commons.wikimedia.org/wiki/$1',
			descBaseUrl = '//commons.wikimedia.org/wiki/File:',
			localRepo = new mw.mmv.model.Repo( displayName, favicon, true ),
			foreignApiRepo = new mw.mmv.model.ForeignApiRepo( displayName, favicon,
				false, apiUrl, server, articlePath ),
			foreignDbRepo = new mw.mmv.model.ForeignDbRepo( displayName, favicon, false, descBaseUrl );

		assert.ok( localRepo, 'Local repo creation works' );
		assert.ok( foreignApiRepo,
			'Foreign API repo creation works' );
		assert.ok( foreignDbRepo, 'Foreign DB repo creation works' );
	} );

	QUnit.test( 'getArticlePath()', function ( assert ) {
		var displayName = 'Wikimedia Commons',
			favicon = '//commons.wikimedia.org/favicon.ico',
			apiUrl = '//commons.wikimedia.org/w/api.php',
			server = '//commons.wikimedia.org',
			articlePath = '/wiki/$1',
			descBaseUrl = '//commons.wikimedia.org/wiki/File:',
			localRepo = new mw.mmv.model.Repo( displayName, favicon, true ),
			foreignApiRepo = new mw.mmv.model.ForeignApiRepo( displayName, favicon,
				false, apiUrl, server, articlePath ),
			foreignDbRepo = new mw.mmv.model.ForeignDbRepo( displayName, favicon, false, descBaseUrl ),
			expectedLocalArticlePath = '/wiki/$1',
			expectedFullArticlePath = '//commons.wikimedia.org/wiki/$1',
			oldWgArticlePath = mw.config.get( 'wgArticlePath' ),
			oldWgServer = mw.config.get( 'wgServer' );

		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgServer', server );

		assert.strictEqual( localRepo.getArticlePath(), expectedLocalArticlePath,
			'Local repo article path is correct' );
		assert.strictEqual( localRepo.getArticlePath( true ), expectedFullArticlePath,
			'Local repo absolute article path is correct' );
		assert.strictEqual( foreignApiRepo.getArticlePath(), expectedFullArticlePath,
			'Foreign API article path is correct' );
		assert.strictEqual( foreignDbRepo.getArticlePath(), expectedFullArticlePath,
			'Foreign DB article path is correct' );

		mw.config.set( 'wgArticlePath', oldWgArticlePath );
		mw.config.set( 'wgServer', oldWgServer );
	} );

	QUnit.test( 'getSiteLink()', function ( assert ) {
		var displayName = 'Wikimedia Commons',
			favicon = '//commons.wikimedia.org/favicon.ico',
			apiUrl = '//commons.wikimedia.org/w/api.php',
			server = '//commons.wikimedia.org',
			articlePath = '/wiki/$1',
			descBaseUrl = '//commons.wikimedia.org/wiki/File:',
			localRepo = new mw.mmv.model.Repo( displayName, favicon, true ),
			foreignApiRepo = new mw.mmv.model.ForeignApiRepo( displayName, favicon,
				false, apiUrl, server, articlePath ),
			foreignDbRepo = new mw.mmv.model.ForeignDbRepo( displayName, favicon, false, descBaseUrl ),
			expectedSiteLink = '//commons.wikimedia.org/wiki/',
			oldWgArticlePath = mw.config.get( 'wgArticlePath' ),
			oldWgServer = mw.config.get( 'wgServer' );

		mw.config.set( 'wgArticlePath', '/wiki/$1' );
		mw.config.set( 'wgServer', server );

		assert.strictEqual( localRepo.getSiteLink(), expectedSiteLink,
			'Local repo site link is correct' );
		assert.strictEqual( foreignApiRepo.getSiteLink(), expectedSiteLink,
			'Foreign API repo site link is correct' );
		assert.strictEqual( foreignDbRepo.getSiteLink(), expectedSiteLink,
			'Foreign DB repo site link is correct' );

		mw.config.set( 'wgArticlePath', oldWgArticlePath );
		mw.config.set( 'wgServer', oldWgServer );
	} );

}( mediaWiki ) );
